"""Cache por palavras-chave (SQLite, WAL, lock de escrita)."""

from __future__ import annotations

import json
import sqlite3
import threading
import time
from pathlib import Path

from . import normalize

_SCHEMA = """
CREATE TABLE IF NOT EXISTS cache_entries (
    query_key    TEXT PRIMARY KEY,
    keywords     TEXT NOT NULL,
    answer       TEXT NOT NULL,
    source       TEXT NOT NULL,
    created_at   INTEGER NOT NULL,
    expires_at   INTEGER NOT NULL,
    hits         INTEGER NOT NULL DEFAULT 0
);
CREATE INDEX IF NOT EXISTS idx_cache_expires ON cache_entries(expires_at);
"""


class ResponseCache:
    """Cache de respostas geradas, chaveada por palavras-chave da pergunta."""

    def __init__(
        self,
        db_path: str | Path,
        ttl: int = 86400,
        sim_threshold: float = 0.55,
        min_keywords: int = 2,
    ) -> None:
        self.db_path = Path(db_path)
        self.db_path.parent.mkdir(parents=True, exist_ok=True)
        self.ttl = ttl
        self.sim_threshold = sim_threshold
        self.min_keywords = min_keywords
        self._lock = threading.Lock()
        self._conn = sqlite3.connect(str(self.db_path), check_same_thread=False)
        self._conn.row_factory = sqlite3.Row
        self._conn.execute("PRAGMA journal_mode=WAL")
        self._conn.execute("PRAGMA busy_timeout=5000")
        self._conn.executescript(_SCHEMA)
        self._conn.commit()

    def get(self, query: str) -> dict | None:
        """Devolve resposta em cache (match exato ou por semelhança) e atualiza hits.

        Critérios:
          - query exata (normalizada) → hit imediato;
          - senão, semelhança de Jaccard sobre keywords ≥ threshold e mínimo de keywords;
          - expirado → não devolve (próximo set() substitui).
        """
        qk = normalize.normalize_query(query)
        now = int(time.time())

        with self._lock:
            self._purge(now)
            row = self._conn.execute(
                "SELECT answer, source, keywords, expires_at FROM cache_entries WHERE query_key=?",
                (qk,),
            ).fetchone()
            if row is not None and row["expires_at"] > now:
                self._conn.execute(
                    "UPDATE cache_entries SET hits=hits+1 WHERE query_key=?", (qk,)
                )
                self._conn.commit()
                return {"answer": row["answer"], "source": row["source"], "cache_hit": True}

        nkw = set(normalize.keywords(query))
        if len(nkw) < self.min_keywords:
            return None

        with self._lock:
            rows = self._conn.execute(
                "SELECT query_key, keywords, answer, source FROM cache_entries WHERE expires_at>?",
                (now,),
            ).fetchall()

        best: tuple[float, str, str, str] | None = None
        for r in rows:
            stored = set(json.loads(r["keywords"]))
            sim = normalize.jaccard(nkw, stored)
            if sim >= self.sim_threshold and (best is None or sim > best[0]):
                best = (sim, r["query_key"], r["answer"], r["source"])

        if best is None:
            return None

        with self._lock:
            self._conn.execute(
                "UPDATE cache_entries SET hits=hits+1 WHERE query_key=?", (best[1],)
            )
            self._conn.commit()
        return {"answer": best[2], "source": best[3], "cache_hit": True}

    def set(self, query: str, answer: str, source: str) -> None:
        """Guarda resposta. Nunca guarda respostas que contenham termos no-cache."""
        if normalize.has_no_cache_terms("\n".join((query, answer))):
            return
        qk = normalize.normalize_query(query)
        now = int(time.time())
        kws = json.dumps(normalize.keywords(query))
        if not normalize.keywords(query):
            return
        with self._lock:
            self._conn.execute(
                "INSERT INTO cache_entries (query_key, keywords, answer, source, created_at, expires_at) "
                "VALUES (?,?,?,?,?,?) "
                "ON CONFLICT(query_key) DO UPDATE SET "
                "keywords=excluded.keywords, answer=excluded.answer, source=excluded.source, "
                "created_at=excluded.created_at, expires_at=excluded.expires_at",
                (qk, kws, answer, source, now, now + self.ttl),
            )
            self._conn.commit()

    def clear(self) -> int:
        with self._lock:
            cur = self._conn.execute("DELETE FROM cache_entries")
            self._conn.commit()
            return cur.rowcount

    def count(self) -> int:
        with self._lock:
            row = self._conn.execute(
                "SELECT COUNT(*) AS c FROM cache_entries WHERE expires_at>?",
                (int(time.time()),),
            ).fetchone()
            return int(row["c"])

    def _purge(self, now: int) -> None:
        self._conn.execute("DELETE FROM cache_entries WHERE expires_at<=?", (now,))
        self._conn.commit()

    def close(self) -> None:
        with self._lock:
            self._conn.close()