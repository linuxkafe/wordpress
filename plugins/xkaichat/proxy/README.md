# XKaiChat Proxy

Serviço intermédio (FastAPI) entre o plugin WordPress e o Ollama. Faz RAG
(FAQ + BM25 sobre o PDF da tabela de preços), valida o LLM, saneia respostas
e serve um cache por palavras-chave para poupar chamadas ao modelo.

## Instalação e execução (local, sem Docker)

```bash
make setup               # cria .venv e instala dependências (uma vez)
cd proxy && cp .env.example .env   # ajustar ao ambiente
make run-proxy           # uvicorn em http://127.0.0.1:5001
```

## Docker

### Build da imagem
```bash
docker build -t xkaichat-proxy ./proxy
```

### Execução standalone (requer Broker + Ollama acessíveis)
```bash
docker run -d \
  --name xkaichat-proxy \
  -p 5001:5001 \
  --env-file proxy/.env \
  -e BROKER_URL=http://<broker-host>:5002 \
  -e OLLAMA_URL=http://<ollama-host>:11434 \
  -v proxy_cache:/app/cache \
  -v proxy_index:/app/index \
  -v ./proxy/pdfs:/app/pdfs:ro \
  -v ./proxy/knowledge_base.json:/app/knowledge_base.json:ro \
  xkaichat-proxy
```

### Execução com docker-compose (dev local completo)
```bash
# Na raiz do projeto
docker-compose up -d
# Proxy em http://localhost:5001
```

### Variáveis de ambiente obrigatórias

| Variável | Descrição | Exemplo |
|----------|-----------|---------|
| `PROXY_KEY` | Chave partilhada com WordPress Plugin | `chave-segura-32-chars` |
| `BROKER_KEY` | Chave partilhada com Broker | `outra-chave-32-chars` |
| `BROKER_URL` | URL do Broker (obrigatório se usar Broker) | `http://broker:5002` |
| `OLLAMA_URL` | URL do Ollama (fallback se sem Broker) | `http://ollama:11434` |
| `OLLAMA_MODEL` | Modelo a usar | `qwen3:8b` |

Ver `proxy/.env.example` para lista completa.

### Healthcheck
```bash
curl http://localhost:5001/api/health
# {"status":"ok","mode":"broker","model":"qwen3:8b","cache_items":0,"upstream":"ok"}
```

O healthcheck do Docker usa este endpoint (intervalo 30s, timeout 10s).

### Persistência
- **Cache SQLite**: volume `proxy_cache` → `/app/cache`
- **Índice BM25**: volume `proxy_index` → `/app/index`
- **PDFs**: bind mount `./proxy/pdfs` → `/app/pdfs` (read-only)
- **Knowledge base**: bind mount `./proxy/knowledge_base.json` → `/app/knowledge_base.json` (read-only)

### Deploy em servidor remoto (Proxy apenas)

```bash
# No servidor do Proxy
scp -r proxy/ user@proxy-server:/opt/xkaichat/proxy/
ssh user@proxy-server "cd /opt/xkaichat/proxy && docker build -t xkaichat-proxy ."

# Configurar .env no servidor
ssh user@proxy-server "cd /opt/xkaichat/proxy && cp .env.example .env && vim .env"

# Executar (assumindo Broker noutro host)
ssh user@proxy-server "
  docker run -d \
    --name xkaichat-proxy \
    --restart unless-stopped \
    -p 5001:5001 \
    --env-file .env \
    -e BROKER_URL=http://<broker-ip>:5002 \
    -v proxy_cache:/app/cache \
    -v proxy_index:/app/index \
    -v ./pdfs:/app/pdfs:ro \
    -v ./knowledge_base.json:/app/knowledge_base.json:ro \
    xkaichat-proxy
"
```

## API

| Método | Rota | Corpo | Resposta |
|--------|------|-------|----------|
| GET | `/api/health` | – | `{status, mode, model, cache_items, upstream}` |
| POST | `/api/chat` | `{message, lang}` | `{answer, cache_hit, source}` |
| POST | `/api/cache/clear` | – | `{cleared}` |
| POST | `/api/rag/reindex` | – | `{chunks}` |

`_chat` devolve origem `faq` (knowledge_base.json), `pdf` (BM25 sobre o PDF
de preços) ou `none` (conversa livre, nunca cached). Respostas com termos
transientes (horários, encomendas, "hoje") não são cacheadas.

## Segurança

- `PROXY_KEY` opcional: se definida, o plugin envia-a no header `X-Xkai-Proxy-Key`
  e o proxy rejeita pedidos sem a chave (comparação em tempo constante).
- `BROKER_KEY` separada: autentica Proxy → Broker (defesa em profundidade).
- Rate-limit por IP em memória.
- Nunca inventar preços: o prompt apenas usa o contexto RAG como FACTOS OFICIAIS.

## Testes

```bash
make test-proxy   # pytest em tests/proxy
make qa           # E2E contra o Ollama local (regenera docs/QA.md)
```