"""Configuração comum dos testes do proxy."""

from __future__ import annotations

import os
import socket
import sys
import tempfile
from pathlib import Path

import pytest

# Raiz do projecto (para import proxy.*).
ROOT = Path(__file__).resolve().parents[1]
if str(ROOT) not in sys.path:
    sys.path.insert(0, str(ROOT))

TMP = Path(tempfile.mkdtemp(prefix="xkc-test-"))

# Isola artefactos de testes (cache + índice) do ambiente real.
os.environ.setdefault("CACHE_DB", str(TMP / "cache.db"))
os.environ.setdefault("INDEX_DIR", str(TMP / "index"))
os.environ.setdefault("PROXY_KEY", "test-shared-key")
os.environ.setdefault("OLLAMA_URL", "http://127.0.0.1:11434")


def _ollama_online() -> bool:
    try:
        with socket.create_connection(("127.0.0.1", 11434), timeout=1):
            return True
    except OSError:
        return False


def pytest_collection_modifyitems(session, config, items):
    """Salta testes E2E quando o Ollama está offline (o make qa força o real)."""
    online = _ollama_online()
    if online:
        return
    skip = pytest.mark.skip(reason="Ollama offline em 127.0.0.1:11434 (E2E desativado)")
    for item in items:
        if "e2e" in item.keywords:
            item.add_marker(skip)