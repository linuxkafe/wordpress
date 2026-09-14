"""Normalização de texto e extração de palavras-chave (PT-PT)."""

from __future__ import annotations

import re
import unicodedata

# Stopwords PT-PT (pequeno conjunto cirúrgico — não tirar palavras de domínio).
_STOPWORDS: frozenset[str] = frozenset(
    """
    a o as os um uma uns umas de do da dos das em no na nos nas e ou que
    para com por sem sob sobre ao à às pelo pela pelos pelas entre mas
    como se já não é são está estou estás estávamos eu tu ele ela nós
    vocês eles elas meu minha nossos minhas teu tua teus tuas seu sua seus
    suas quem que qual quais quanto quantos qualquers alguém ninguém tudo
    nada algo outro outras outros outros este esta estes estas esse essa
    esses essas aquele aquela aqueles aquelas isto isso aquilo aqui aí ali
    onde quando porque portanto contudo todavia então mesmo assim também
    ainda já muito pouco mais menos muito bastante quero gostaria gostava
    queria saber posso pode podemos poderia podíamos fazer tens tenho tem
    têm será seria seria-se foi eram fosse fora havia haviam ver dizer
    mande obrigado obrigada olá bom boa dia noite bem mal hoje amanhã ontem
    agora depois antes durante também vez vezes coisa coisas caso dias anho
    ano anos horas hojes sim sempre nunca alguma alguns algumas algumas
        """.split()
)

# Não usar estas famílias como *única* chave de cache (transientes/imprecisas).
_NO_CACHE_KEYWORDS: tuple[str, ...] = (
    "manutenção",
    "manutencao",
    "indisponível",
    "indisponivel",
    "falha",
    "avaria",
    "erro",
    "horário",
    "horario",
    "abre",
    "fecha",
    "aberto",
    "fechado",
    "encomenda",
    "hoje",
    "amanha",
    "amanhã",
)


def fold(s: str) -> str:
    """Remove acentos e normaliza para comparar de forma robusta."""
    s = unicodedata.normalize("NFD", s or "")
    s = "".join(c for c in s if unicodedata.category(c) != "Mn")
    return s.lower().strip()


def tokenize(text: str) -> list[str]:
    """Extrai palavras normalizadas (abaixo de fold), sem stopwords."""
    text = fold(text or "")
    keep_letters = re.sub(r"[^a-zà-ÿ0-9'’\-]+", " ", text, flags=re.UNICODE)
    keep_letters = keep_letters.replace("’", "'")
    words = re.findall(r"[a-z0-9]+(?:[-'][a-z0-9]+)*", keep_letters)
    result: list[str] = []
    for w in words:
        if len(w) >= 2 and w not in _STOPWORDS:
            result.append(w)
    return result


def keywords(text: str, min_len: int = 3) -> list[str]:
    """Palavras-chave únicas para cache (min_len >= 3 para evitar falsos positivos)."""
    seen: set[str] = set()
    out: list[str] = []
    for w in tokenize(text):
        if len(w) < min_len or w in seen:
            continue
        seen.add(w)
        out.append(w)
    return out


def jaccard(a: set[str], b: set[str]) -> float:
    if not a and not b:
        return 0.0
    return len(a & b) / float(len(a | b))


def normalize_query(text: str) -> str:
    """Chave canónica de query: fold + espaços simples."""
    return re.sub(r"\s+", " ", fold(text)).strip()


def has_no_cache_terms(text: str) -> bool:
    folded = fold(text)
    for term in _NO_CACHE_KEYWORDS:
        if fold(term) in folded:
            return True
    return False