"""Testes da cache por palavras-chave."""

from pathlib import Path
import time

from proxy.cache import ResponseCache


def _cache(tmp_path: Path, ttl: int = 86400, sim: float = 0.55, minkw: int = 2) -> ResponseCache:
    return ResponseCache(tmp_path / "t.db", ttl=ttl, sim_threshold=sim, min_keywords=minkw)


def test_miss_entao_hit_exato(tmp_path):
    c = _cache(tmp_path)
    assert c.get("Quanto custa a bôla?") is None

    c.set("Quanto custa a bôla?", "A bôla de 1,6 kg custa 18€.", "pdf")
    hit = c.get("Quanto custa a bôla?")
    assert hit is not None
    assert hit["cache_hit"] is True
    assert hit["answer"] == "A bôla de 1,6 kg custa 18€."


def test_nao_começa_sem_minimo_keywords(tmp_path):
    c = _cache(tmp_path, minkw=2)
    c.set("quanto custa a bôla?", "18€", "pdf")
    # apenas 1 keyword (bôla) -> a mesma frase não é chave exata? query exata mas min kw para sim
    assert c.get("quanto custa a bôla?") is not None  # match exato independe de keywords


def test_match_similaridade_jaccard(tmp_path):
    c = _cache(tmp_path, sim=0.5)
    c.set("Quanto custa a bôla de berlim?", "As bolas de berlim são 4€ as 3.", "faq")
    # "preço da bôla de berlim" partilha bôla+berlim -> semelhante
    hit = c.get("O preço da bôla de berlim?")
    assert hit is not None and hit["cache_hit"] is True


def test_termos_no_cache_impedem_gravacao(tmp_path):
    c = _cache(tmp_path)
    c.set("Está o proxy em manutenção?", "Sim.", "none")
    assert c.count() == 0


def test_ttl_expira(tmp_path):
    c = _cache(tmp_path, ttl=1)
    c.set("ola", "oi", "faq")
    assert c.count() == 1
    time.sleep(1.2)
    assert c.get("ola") is None
    assert c.count() == 0


def test_clear_e_count(tmp_path):
    c = _cache(tmp_path)
    c.set("quanto custa a quiche?", "15€", "faq")
    c.set("quanto custa o brownie?", "12€", "pdf")
    assert c.count() == 2
    cleared = c.clear()
    assert cleared == 2
    assert c.count() == 0