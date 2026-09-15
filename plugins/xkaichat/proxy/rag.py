"""Motor RAG: FAQ (knowledge_base.json) + BM25 sobre chunks de PDF."""

from __future__ import annotations

import json
import math
import re
from dataclasses import dataclass
from pathlib import Path

import normalize

# Prioridade: nunca inventar preços — só usar texto oriundo do PDF de preços/FAQ.
_DATACLASS_SRC = ("preço", "preco", "preços", "precos", "custo", "custos", "€", "euro", "euros")


@dataclass
class Context:
    source: str  # "faq" | "pdf" | "none"
    text: str


class FAQMatcher:
    """Match por triggers: substring de frase ou sobreposição de keywords."""

    def __init__(self, path: str | Path) -> None:
        self.path = Path(path)
        self.items: list[dict] = []
        self._load()

    def _load(self) -> None:
        if not self.path.exists():
            return
        data = json.loads(self.path.read_text(encoding="utf-8"))
        self.items = data if isinstance(data, list) else data.get("faq", [])

    def reload(self) -> int:
        self._load()
        return len(self.items)

    def match(self, message: str) -> dict | None:
        folded = normalize.fold(message)
        nkw = set(normalize.keywords(message))
        best: tuple[float, dict] | None = None
        for item in self.items:
            score = 0.0
            triggers = item.get("triggers", [])
            for tr in triggers:
                tf = normalize.fold(tr)
                if tf and tf in folded:
                    score += 3.0 + (0.5 if len(tf) >= 14 else 0.0)
                else:
                    shared = nkw & set(normalize.keywords(tr))
                    if shared:
                        score += len(shared)
            if score > 0 and (best is None or score > best[0]):
                best = (score, item)
        return best[1] if best else None


class BM25Index:
    """BM25 puro sobre chunks. Persistido como JSON (chunks + estatísticas)."""

    def __init__(self, index_dir: str | Path) -> None:
        self.index_dir = Path(index_dir)
        self.index_dir.mkdir(parents=True, exist_ok=True)
        self.chunks: list[str] = []
        self.doc_freq: dict[str, int] = {}
        self.n_docs = 0
        self.avgdl = 1.0
        self._lengths: list[int] = []
        self.loaded = self._load()

    def _index_path(self) -> Path:
        return self.index_dir / "index.json"

    def _load(self) -> bool:
        p = self._index_path()
        if not p.exists():
            return False
        data = json.loads(p.read_text(encoding="utf-8"))
        self.chunks = data["chunks"]
        self.doc_freq = data["doc_freq"]
        self.n_docs = data["n_docs"]
        self.avgdl = data["avgdl"]
        self._lengths = [len(normalize.tokenize(c)) for c in self.chunks]
        return True

    def build(self, texts: list[str], chunk_size: int = 350, overlap: int = 40) -> None:
        self.chunks = []
        for text in texts:
            self.chunks.extend(_chunk_text(text, chunk_size, overlap))
        self._lengths = [len(normalize.tokenize(c)) for c in self.chunks]
        self.n_docs = len(self.chunks)
        self.avgdl = (sum(self._lengths) / self.n_docs) if self.n_docs else 1.0
        self.doc_freq = {}
        for chunk in self.chunks:
            for w in set(normalize.tokenize(chunk)):
                self.doc_freq[w] = self.doc_freq.get(w, 0) + 1
        self._save()

    def _save(self) -> None:
        data = {
            "chunks": self.chunks,
            "doc_freq": self.doc_freq,
            "n_docs": self.n_docs,
            "avgdl": self.avgdl,
        }
        self._index_path().write_text(json.dumps(data, ensure_ascii=False), encoding="utf-8")

    def _score(self, query_terms: list[str], idx: int) -> float:
        k1, b = 1.2, 0.75
        dl = self._lengths[idx]
        total = 0.0
        counts: dict[str, int] = {}
        for w in normalize.tokenize(self.chunks[idx]):
            counts[w] = counts.get(w, 0) + 1
        for term in query_terms:
            tf = counts.get(term, 0)
            if tf == 0 or term not in self.doc_freq:
                continue
            df = self.doc_freq[term]
            idf = math.log(1 + (self.n_docs - df + 0.5) / (df + 0.5))
            total += idf * (tf * (k1 + 1)) / (tf + k1 * (1 - b + b * dl / self.avgdl))
        return total

    def search(self, message: str, k: int = 3) -> list[str]:
        if not self.chunks:
            return []
        terms = normalize.tokenize(message)
        if not terms:
            return []
        scored = sorted(range(self.n_docs), key=lambda i: self._score(terms, i), reverse=True)
        results: list[str] = []
        for idx in scored[:k]:
            if self._score(terms, idx) > 0:
                results.append(self.chunks[idx])
        return results


class RAG:
    """Combina FAQ + BM25. FAQ tem prioridade; PDF alimenta contexto de preços."""

    def __init__(
        self,
        knowledge_base: str | Path,
        index_dir: str | Path,
        chunk_size: int = 350,
        overlap: int = 40,
    ) -> None:
        self.faq = FAQMatcher(knowledge_base)
        self.bm25 = BM25Index(index_dir)
        self.chunk_size = chunk_size
        self.overlap = overlap

    def reindex(self, pdf_texts: list[str]) -> int:
        self.bm25.build(pdf_texts, self.chunk_size, self.overlap)
        return self.bm25.n_docs

    def context_for(self, message: str, top_k: int = 3) -> Context:
        item = self.faq.match(message)
        if item:
            return Context(
                source="faq",
                text=f'{item.get("question", "")}\n{item.get("answer", "")}',
            )
        hits = self.bm25.search(message, k=top_k)
        if hits:
            return Context(source="pdf", text="\n\n---\n\n".join(hits))
        return Context(source="none", text="")


def _chunk_text(text: str, chunk_size: int, overlap: int) -> list[str]:
    """Divide por parágrafos e junta até chunk_size tokens (overlap de segurança)."""
    paragraphs = [p.strip() for p in re.split(r"\n\s*\n", text) if p.strip()]
    chunks: list[str] = []
    current: list[str] = []
    current_tokens = 0
    overlap_tokens: list[str] = []
    for para in paragraphs:
        para_tokens = normalize.tokenize(para)
        if not para_tokens:
            continue
        if current_tokens + len(para_tokens) > chunk_size and current:
            chunks.append("\n".join(current))
            overlap_tokens = current[-overlap:] if overlap else []
            current = list(overlap_tokens)
            current_tokens = len(overlap_tokens)
        current.append(para)
        current_tokens += len(para_tokens)
    if current:
        chunks.append("\n".join(current))
    return chunks