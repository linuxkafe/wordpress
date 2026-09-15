"""Proxy intermédio do XKaiChat — FastAPI.

Valida no Ollama (rota primária) ou, em fallback opcional, num gateway
OpenAI-compatível. Injeta contexto RAG (FAQ + BM25 sobre PDF de preços),
saneia respostas e serve o cache por palavras-chave.

Executar:
    make run-proxy           # ou:
    cd proxy && ../.venv/bin/uvicorn proxy:app --host 127.0.0.1 --port 5001
"""

from __future__ import annotations

import hmac
import re
import time
from typing import Any

import httpx
from fastapi import FastAPI, Header, HTTPException, Request
from fastapi.responses import JSONResponse
from pydantic import BaseModel, Field

import normalize
from cache import ResponseCache
from config import ProxyConfig
from rag import Context, RAG

config = ProxyConfig()

# Lazy initialization for cache and RAG to allow test overrides
_cache = None
_rag = None


def get_cache() -> ResponseCache:
    global _cache
    if _cache is None:
        _cache = ResponseCache(
            config.cache_db,
            ttl=config.cache_ttl,
            sim_threshold=config.cache_sim_threshold,
            min_keywords=config.cache_min_keywords,
        )
    return _cache


def get_rag() -> RAG:
    global _rag
    if _rag is None:
        _rag = RAG(config.knowledge_base, config.index_dir)
    return _rag


# Lazy getters for backward compatibility (tests can override via monkeypatch)
def _get_cache() -> ResponseCache:
    return get_cache()


def _get_rag() -> RAG:
    return get_rag()

# Limites simples por IP (em memória).
_rate: dict[str, list[float]] = {}

_HTML_TAG = re.compile(r"<[^>]*>")
_CONTENT_TAGS = re.compile(r"</?(chat_input|input|system|human|assistant)>", flags=re.I)
_CTRL = re.compile(r"[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]")

_PERSONA = (
    "És o assistente virtual da Capuchinho Verde, uma pastelaria artesanal 100% vegan "
    "no Porto (912423483). Responde SEMPRE em português de Portugal, de forma curta, "
    "simpática e factual. Nunca inventes preços, nomes ou factos: se a informação "
    "não estiver no contexto fornecido, diz que não tens a certeza e sugere contactar "
    "por telefone 912423483 ou consultar capuchinhoverde.com. Usa texto simples, "
    "sem markdown e sem HTML."
)

app = FastAPI(title="XKaiChat Proxy", version="1.0.0")


class ChatRequest(BaseModel):
    message: str = Field(..., max_length=2000)
    lang: str = "pt"


class ChatResponse(BaseModel):
    answer: str
    cache_hit: bool
    source: str


def _check_proxy_key(x_xkai_proxy_key: str | None) -> None:
    if not config.proxy_key:
        return
    if not x_xkai_proxy_key or not hmac.compare_digest(x_xkai_proxy_key, config.proxy_key):
        raise HTTPException(status_code=401, detail="proxy_key_invalida")


def _upstream_online() -> str:
    """Probe curto ao LLM (Broker/Ollama ou gateway) para o indicador de estado.

    O health do proxy não é suficiente para o widget: o proxy pode estar de pé
    e o LLM em baixo. Um GET aos tags (timeout curto) resolve.
    Devolve sempre "ok"/"down" — nunca levanta exceções.
    """
    timeout = httpx.Timeout(1.5, connect=1.5)
    try:
        with httpx.Client(timeout=timeout) as client:
            if config.gateway_url:
                resp = client.get(f"{config.gateway_url}/models")
            elif config.broker_url:
                headers = {"X-Xkai-Proxy-Key": config.broker_key} if config.broker_key else {}
                resp = client.get(f"{config.broker_url}/api/tags", headers=headers)
            else:
                resp = client.get(f"{config.ollama_url}/api/tags")
        return "ok" if resp.status_code < 400 else "down"
    except httpx.HTTPError:
        return "down"


def _rate_limit(request: Request) -> None:
    ip = request.client.host if request.client else "0.0.0.0"
    now = time.monotonic()
    hits = [t for t in _rate.get(ip, []) if now - t < config.rate_window_seconds]
    if len(hits) >= config.rate_limit_per_ip:
        raise HTTPException(status_code=429, detail="rate_limit_atingido")
    hits.append(now)
    _rate[ip] = hits


def _clean(message: str) -> str:
    message = _CONTENT_TAGS.sub(" ", message)
    message = _CTRL.sub("", message)
    return message.strip()[:2000]


def _system_prompt(ctx: Context) -> str:
    if ctx.source == "none":
        return _PERSONA
    origem = "a tabela de preços oficial" if ctx.source == "pdf" else "as informações oficiais"
    return (
        _PERSONA
        + f"\n\nSó respondas usando FACTOS OFICIAIS ({origem}) incluídos no contexto. "
        + "Não adiciones preços que não apareçam no contexto.\n\n"
        + "FACTOS OFICIAIS (contexto):\n"
        + ctx.text
    )


def _llm_ollama(messages: list[dict[str, str]]) -> str:
    payload = {
        "model": config.ollama_model,
        "messages": messages,
        "stream": False,
        "options": {"temperature": config.temperature},
    }
    headers = {}
    if config.broker_key:
        headers["X-Xkai-Proxy-Key"] = config.broker_key

    target_url = config.broker_url or config.ollama_url
    endpoint = "/api/chat"

    with httpx.Client(timeout=config.llm_timeout) as client:
        resp = client.post(f"{target_url}{endpoint}", json=payload, headers=headers)
    resp.raise_for_status()
    data = resp.json()
    message = data.get("message", {})
    return str(message.get("content", "")).strip()


def _llm_gateway(messages: list[dict[str, str]]) -> str:
    if not config.gateway_url:
        raise RuntimeError("gateway_sem_url")
    headers = {"Authorization": f"Bearer {config.gateway_api_key}"} if config.gateway_api_key else {}
    payload = {"model": config.gateway_model, "messages": messages, "temperature": config.temperature}
    with httpx.Client(timeout=config.llm_timeout) as client:
        resp = client.post(f"{config.gateway_url}/chat/completions", json=payload, headers=headers)
    resp.raise_for_status()
    data = resp.json()
    try:
        return str(data["choices"][0]["message"]["content"]).strip()
    except (KeyError, IndexError, TypeError) as exc:
        raise RuntimeError("gateway_resposta_invalida") from exc


def _generate(message: str) -> tuple[Context, str]:
    ctx = _get_rag().context_for(message)
    system = _system_prompt(ctx)
    messages = [{"role": "system", "content": system}, {"role": "user", "content": message}]
    try:
        answer = _llm_ollama(messages)
        return ctx, answer
    except Exception as ollama_exc:  # noqa: BLE001
        if config.gateway_url:
            try:
                return ctx, _llm_gateway(messages)
            except Exception as gateway_exc:  # noqa: BLE001
                raise HTTPException(status_code=502, detail="llm_indisponivel") from gateway_exc
        raise HTTPException(status_code=502, detail="llm_indisponivel") from ollama_exc


@app.get("/api/health")
def health() -> dict:
    if config.broker_url:
        mode = "broker" + ("+gateway" if config.gateway_url else "")
    else:
        mode = "ollama" + ("+gateway" if config.gateway_url else "")
    return {
        "status": "ok",
        "mode": mode,
        "model": config.ollama_model,
        "cache_items": _get_cache().count(),
        "upstream": _upstream_online(),
    }


@app.post("/api/chat", response_model=ChatResponse)
def chat(
    body: ChatRequest,
    request: Request,
    x_xkai_proxy_key: str | None = Header(default=None),
) -> dict:
    _check_proxy_key(x_xkai_proxy_key)
    _rate_limit(request)

    message = _clean(body.message)
    if not message:
        raise HTTPException(status_code=400, detail="mensagem_vazia")

    cached = _get_cache().get(message)
    if cached:
        return cached

    ctx, answer = _generate(message)
    answer = _clean(answer) or "Desculpa, não consegui responder. Tenta de novo por favor."

    payload = {"answer": answer, "cache_hit": False, "source": ctx.source}
    if ctx.source != "none" and not normalize.has_no_cache_terms(message):
        _get_cache().set(message, answer, ctx.source)
    return payload


@app.post("/api/cache/clear", status_code=200)
def cache_clear() -> dict:
    cleared = _get_cache().clear()
    return {"cleared": cleared}


@app.post("/api/rag/reindex", status_code=200)
def rag_reindex() -> dict:
    pdfs = sorted(config.pdfs_dir.glob("*.pdf"))
    if not pdfs:
        raise HTTPException(status_code=400, detail="sem_pdfs")
    from .index_pdfs import extract_pdfs

    texts = extract_pdfs(config.pdfs_dir)
    if not texts:
        raise HTTPException(status_code=400, detail="sem_texto_pdf")
    chunks = _get_rag().reindex(texts)
    return {"chunks": chunks}


@app.exception_handler(HTTPException)
async def _http_exc_handler(_request: Request, exc: HTTPException) -> JSONResponse:
    return JSONResponse(status_code=exc.status_code, content={"detail": exc.detail})


if __name__ == "__main__":
    import uvicorn

    uvicorn.run(app, host=config.host, port=config.port)