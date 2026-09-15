# XKaiChat Broker

Camada de autenticação HMAC à frente do Ollama. Valida a identidade do Proxy
via `X-Xkai-Proxy-Key` antes de encaminhar pedidos para `/api/chat` e `/api/tags`.

## Instalação e execução (local, sem Docker)

```bash
make setup               # cria .venv e instala dependências (uma vez)
cd broker && cp .env.example .env   # ajustar ao ambiente
make run-broker          # uvicorn em http://127.0.0.1:5002
```

## Docker

### Build da imagem
```bash
docker build -t xkaichat-broker ./broker
```

### Execução standalone (requer Ollama acessível)
```bash
docker run -d \
  --name xkaichat-broker \
  -p 5002:5002 \
  --env-file broker/.env \
  -e OLLAMA_URL=http://<ollama-host>:11434 \
  xkaichat-broker
```

### Execução com docker-compose (dev local completo)
```bash
# Na raiz do projeto
docker-compose up -d
# Broker em http://localhost:5002
```

### Variáveis de ambiente obrigatórias

| Variável | Descrição | Exemplo |
|----------|-----------|---------|
| `BROKER_KEY` | Chave partilhada com Proxy | `chave-segura-32-chars` |
| `OLLAMA_URL` | URL do Ollama upstream | `http://ollama:11434` |
| `OLLAMA_MODEL` | Modelo a forçar (ignora request) | `qwen3:8b` |

Ver `broker/.env.example` para lista completa.

### Healthcheck
```bash
curl http://localhost:5002/api/health
# {"status":"ok","model":"qwen3:8b","upstream":"ok"}

curl -H "X-Xkai-Proxy-Key: <BROKER_KEY>" http://localhost:5002/api/tags
# {"models":[{"name":"qwen3:8b",...}]}
```

O healthcheck do Docker usa `/api/health` (intervalo 30s, timeout 10s).

### Stateless
O Broker **não tem estado persistente** — rate limit em memória, sem volumes.
Pode ser escalado horizontalmente (com rate limit distribuído via Redis no futuro).

### Deploy em servidor remoto (Broker apenas)

```bash
# No servidor do Broker
scp -r broker/ user@broker-server:/opt/xkaichat/broker/
ssh user@broker-server "cd /opt/xkaichat/broker && docker build -t xkaichat-broker ."

# Configurar .env no servidor
ssh user@broker-server "cd /opt/xkaichat/broker && cp .env.example .env && vim .env"

# Executar (assumindo Ollama noutro host)
ssh user@broker-server "
  docker run -d \
    --name xkaichat-broker \
    --restart unless-stopped \
    -p 5002:5002 \
    --env-file .env \
    -e OLLAMA_URL=http://<ollama-ip>:11434 \
    xkaichat-broker
"
```

## API (compatível com Ollama)

| Método | Rota | Auth | Descrição |
|--------|------|------|-----------|
| GET | `/api/health` | Não | Status do Broker + upstream Ollama |
| GET | `/api/tags` | Sim (`X-Xkai-Proxy-Key`) | Lista modelos (proxy para Ollama) |
| POST | `/api/chat` | Sim (`X-Xkai-Proxy-Key`) | Chat completion (força modelo configurado) |

### Modelo forçado
O Broker **ignora** o campo `model` do request e usa sempre `OLLAMA_MODEL`
do config. Isto previne model confusion attacks.

## Segurança

- HMAC `compare_digest` (constant-time) valida `X-Xkai-Proxy-Key`.
- Rate-limit por IP (configurável: `RATE_LIMIT_PER_IP`, `RATE_WINDOW_SECONDS`).
- Chave separada do Proxy: `BROKER_KEY` ≠ `PROXY_KEY` (blast radius limitado).
- Non-root user no container (UID 1000).

## Testes

```bash
make test-broker   # pytest em tests/broker
```