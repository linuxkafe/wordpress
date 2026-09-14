"""Configuração do proxy lida de .env / ambiente."""

from __future__ import annotations

import os
from dataclasses import dataclass, field
from pathlib import Path

from dotenv import load_dotenv

_BASE = Path(__file__).parent
load_dotenv(_BASE / ".env")


def _float(name: str, default: float) -> float:
    try:
        return float(os.environ.get(name, default))
    except ValueError:
        return default


@dataclass
class ProxyConfig:
    host: str = os.environ.get("PROXY_HOST", "127.0.0.1")
    port: int = int(os.environ.get("PROXY_PORT", "5001"))
    proxy_key: str = os.environ.get("PROXY_KEY", "")

    ollama_url: str = os.environ.get("OLLAMA_URL", "http://127.0.0.1:11434")
    ollama_model: str = os.environ.get("OLLAMA_MODEL", "qwen3:8b")
    temperature: float = _float("OLLAMA_TEMPERATURE", 0.3)
    llm_timeout: float = _float("LLM_TIMEOUT", 150.0)

    gateway_url: str = os.environ.get("GATEWAY_URL", "")
    gateway_api_key: str = os.environ.get("GATEWAY_API_KEY", "")
    gateway_model: str = os.environ.get("GATEWAY_MODEL", ollama_model)

    cache_db: Path = Path(os.environ.get("CACHE_DB", str(_BASE / "cache" / "cache.db")))
    cache_ttl: int = int(os.environ.get("CACHE_TTL", "86400"))
    cache_sim_threshold: float = _float("CACHE_SIM_THRESHOLD", 0.55)
    cache_min_keywords: int = int(os.environ.get("CACHE_MIN_KEYWORDS", "2"))

    knowledge_base: Path = Path(os.environ.get("KNOWLEDGE_BASE", str(_BASE / "knowledge_base.json")))
    index_dir: Path = Path(os.environ.get("INDEX_DIR", str(_BASE / "index")))
    pdfs_dir: Path = Path(os.environ.get("PDFS_DIR", str(_BASE / "pdfs")))

    rate_limit_per_ip: int = int(os.environ.get("RATE_LIMIT_PER_IP", "20"))
    rate_window_seconds: int = int(os.environ.get("RATE_WINDOW_SECONDS", "60"))

    debug: bool = os.environ.get("PROXY_DEBUG", "0") == "1"

    extra: dict = field(default_factory=dict)