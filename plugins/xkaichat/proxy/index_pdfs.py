"""Extrai texto dos PDFs em proxy/pdfs/ e reconstrói o índice BM25.

Uso:
    python -m proxy.index_pdfs
"""

from __future__ import annotations

import sys
from pathlib import Path

from pypdf import PdfReader

from .rag import RAG

PDFS_DIR = Path(__file__).parent / "pdfs"


def extract_pdfs(pdfs_dir: str | Path = PDFS_DIR) -> list[str]:
    """Lê todos os .pdf e devolve lista de textos extraídos (página por documento)."""
    texts: list[str] = []
    for pdf_path in sorted(Path(pdfs_dir).glob("*.pdf")):
        try:
            reader = PdfReader(str(pdf_path))
            pages = "\n".join(page.extract_text() or "" for page in reader.pages)
            if pages.strip():
                texts.append(pages)
                print(f"[ok] {pdf_path.name} ({len(reader.pages)} pág., {len(pages)} ch.)")
            else:
                print(f"[aviso] {pdf_path.name}: sem texto extraível")
        except Exception as exc:  # noqa: BLE001 — um PDF não deve bloquear o resto
            print(f"[erro] {pdf_path.name}: {exc}")
    return texts


def main() -> int:
    rag = RAG(knowledge_base=Path(__file__).parent / "knowledge_base.json", index_dir=Path(__file__).parent / "index")
    texts = extract_pdfs()
    if not texts:
        print("Nenhum PDF com texto encontrado em proxy/pdfs/.")
        return 1
    n = rag.reindex(texts)
    print(f"Índice BM25 reconstruído com {n} chunks.")
    return 0


if __name__ == "__main__":
    sys.exit(main())