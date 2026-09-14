"""Testes do motor RAG (FAQ + BM25 sobre PDF)."""

import json
from pathlib import Path

from proxy.rag import RAG, _chunk_text


def _kb(tmp_path: Path) -> Path:
    p = tmp_path / "kb.json"
    p.write_text(
        json.dumps(
            {
                "faq": [
                    {
                        "triggers": ["bolas de berlim", "berlim"],
                        "question": "Preço das Bolas de Berlim",
                        "answer": "Bolas de Berlim: 3 unidades por 4€ (2026).",
                    }
                ]
            }
        ),
        encoding="utf-8",
    )
    return p


def test_faq_match_por_frase(tmp_path):
    rag = RAG(_kb(tmp_path), tmp_path / "index")
    ctx = rag.context_for("Quanto custam as bolas de berlim?")
    assert ctx.source == "faq"
    assert "4€" in ctx.text


def test_bm25_sobre_pdf(tmp_path):
    rag = RAG(tmp_path / "kbm.json", tmp_path / "index")
    rag.reindex(
        [
            "Tabela de preços 2026.\nBôla de enchidos ou mista: 18€ por 1,6 kg.\n"
            "Pastéis de nata: 6 unidades 7€, 12 unidades 12€.\n"
            "Quiche: 15€. Cheesecake Oreo: 17€."
        ]
    )
    ctx = rag.context_for("Quanto custa a quiche?")
    assert ctx.source == "pdf"
    assert "Quiche" in ctx.text


def test_sem_contexto(tmp_path):
    rag = RAG(_kb(tmp_path), tmp_path / "index")
    ctx = rag.context_for("obrigado pela ajuda")
    assert ctx.source == "none"
    assert ctx.text == ""


def test_chunking_respeita_tamanho():
    paragrafos = ["A" * 20 for _ in range(200)]
    chunks = _chunk_text("\n\n".join(paragrafos), chunk_size=30, overlap=5)
    assert len(chunks) > 1