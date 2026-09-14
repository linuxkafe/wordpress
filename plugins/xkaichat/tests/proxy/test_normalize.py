"""Testes de normalize (PT-PT)."""

from proxy import normalize


def test_fold_remove_acentos_e_minusculas():
    assert normalize.fold("Bôla Enchidos") == "bola enchidos"
    assert normalize.fold("MÃE") == "mae"


def test_tokenize():
    words = normalize.tokenize("Quanto custa a Bôla de 1,6 kg?")
    assert "bôla" in words or "bola" in words
    assert "de" not in words  # stopword
    assert "quanto" not in words  # stopword


def test_keywords_min_length():
    kw = normalize.keywords("Quanto custa a bôla de berlim?")
    assert "bôla" in kw or "bola" in kw
    assert len(kw) >= 2


def test_jaccard():
    assert normalize.jaccard({"a", "b"}, {"b", "c"}) == 1 / 3
    assert normalize.jaccard(set(), set()) == 0.0
    assert normalize.jaccard({"a"}, {"a"}) == 1.0


def test_normalize_query():
    assert normalize.normalize_query("  Quanto   Custa? ") == "quanto custa?"


def test_has_no_cache_terms():
    assert normalize.has_no_cache_terms(" está o proxy em manutenção?")
    assert not normalize.has_no_cache_terms("quanto custa a bôla")