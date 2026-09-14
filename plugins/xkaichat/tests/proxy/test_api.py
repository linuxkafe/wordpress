"""Testes da API FastAPI (LLM mockado)."""

import pytest
from fastapi.testclient import TestClient

from proxy import proxy as appmod
from proxy.rag import Context

SHARED_KEY = "test-shared-key"
HEADERS = {"X-Xkai-Proxy-Key": SHARED_KEY}


@pytest.fixture()
def client():
    with TestClient(appmod.app) as c:
        yield c


@pytest.fixture(autouse=True)
def _limpa_estado():
    yield
    appmod.cache.clear()
    appmod._rate.clear()


def test_health(client):
    r = client.get("/api/health")
    assert r.status_code == 200
    data = r.json()
    assert data["status"] == "ok"
    assert "cache_items" in data


def test_chat_sem_chave_partilhada(client):
    r = client.post("/api/chat", json={"message": "oi"})
    assert r.status_code == 401


def test_chat_mensagem_vazia(client):
    r = client.post("/api/chat", json={"message": "   "}, headers=HEADERS)
    assert r.status_code == 400


def test_chat_llm_e_cache(monkeypatch, client):
    chamadas = []

    def fake_generate(message):
        chamadas.append(message)
        return Context(source="faq", text="OFFICIAL"), "As bolas de berlim são 4€ as 3 unidades."

    monkeypatch.setattr(appmod, "_generate", fake_generate)

    r1 = client.post("/api/chat", json={"message": "Quanto custam as bolas de berlim?"}, headers=HEADERS)
    assert r1.status_code == 200
    d1 = r1.json()
    assert d1["answer"]
    assert d1["cache_hit"] is False
    assert d1["source"] == "faq"

    r2 = client.post("/api/chat", json={"message": "Quanto custam as bolas de berlim?"}, headers=HEADERS)
    assert r2.status_code == 200
    assert r2.json()["cache_hit"] is True

    assert len(chamadas) == 1, "segunda chamada veio da cache"


def test_termos_no_cache_nao_sao_guardados(monkeypatch, client):
    chamadas = []

    def fake_generate(message):
        chamadas.append(message)
        return Context(source="faq", text="OFFICIAL"), "A loja está em manutenção."

    monkeypatch.setattr(appmod, "_generate", fake_generate)

    for _ in range(2):
        r = client.post("/api/chat", json={"message": "o proxy está em manutenção?"}, headers=HEADERS)
        assert r.status_code == 200

    assert len(chamadas) == 2, "pergunta com termo no-cache não devia ser cacheadas"


def test_rate_limit(monkeypatch, client):
    monkeypatch.setattr(appmod.config, "rate_limit_per_ip", 2)

    def fake_generate(message):
        return Context(source="none", text=""), "oi"

    monkeypatch.setattr(appmod, "_generate", fake_generate)

    assert client.post("/api/chat", json={"message": "a"}, headers=HEADERS).status_code == 200
    assert client.post("/api/chat", json={"message": "b"}, headers=HEADERS).status_code == 200
    assert client.post("/api/chat", json={"message": "c"}, headers=HEADERS).status_code == 429


def test_cache_clear(client):
    appmod.cache.set("quanto custa a quiche?", "15€", "faq")
    r = client.post("/api/cache/clear", headers=HEADERS)
    assert r.status_code == 200
    assert r.json()["cleared"] == 1


def test_reindex_sem_pdfs(monkeypatch, client, tmp_path):
    vazio = tmp_path / "empty"
    vazio.mkdir()
    monkeypatch.setattr(appmod.config, "pdfs_dir", vazio)
    r = client.post("/api/rag/reindex", headers=HEADERS)
    assert r.status_code == 400