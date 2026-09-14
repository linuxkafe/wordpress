# XKaiChat Proxy

Serviço intermédio (FastAPI) entre o plugin WordPress e o Ollama. Faz RAG
(FAQ + BM25 sobre o PDF da tabela de preços), valida o LLM, saneia respostas
e serve um cache por palavras-chave para poupar chamadas ao modelo.

## Instalação e execução

```bash
make setup               # cria .venv e instala dependências (uma vez)
cd proxy && cp .env.example .env   # ajustar ao ambiente
make run-proxy           # uvicorn em http://127.0.0.1:5001
```

## API

| Método | Rota             | Corpo            | Resposta                                |
|--------|------------------|------------------|-----------------------------------------|
| GET    | `/api/health`    | –                | `{status, mode, model, cache_items}`    |
| POST   | `/api/chat`      | `{message, lang}`| `{answer, cache_hit, source}`           |
| POST   | `/api/cache/clear` | –              | `{cleared}`                             |
| POST   | `/api/rag/reindex` | –              | `{chunks}`                              |

`_chat` devolve origem `faq` (knowledge_base.json), `pdf` (BM25 sobre o PDF
de preços) ou `none` (conversa livre, nunca cached). Respostas com termos
transientes (horários, encomendas, "hoje") não são cacheadas.

## Segurança

- `PROXY_KEY` opcional: se definida, o plugin envia-a no header `X-Xkai-Proxy-Key`
  e o proxy rejeita pedidos sem a chave (comparação em tempo constante).
- Rate-limit por IP em memória.
- Nunca inventar preços: o prompt apenas usa o contexto RAG como FACTOS OFICIAIS.

## Testes

```bash
make test-proxy   # pytest em tests/proxy
make qa           # E2E contra o Ollama local (regenera docs/QA.md)
```