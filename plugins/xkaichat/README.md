# XKaiChat

Assistente IA originalmente criado para o **Capuchinho Verde** — plugin WordPress (PHP) + proxy intermédio (Python/FastAPI) que conversa com o Ollama local usando RAG sobre o menu/FAQ oficial.

O visitante valida o email com um código temporário (telefone opcional), conversa com o assistente sobre produtos, preços e encomendas, e no final o resumo da conversa é enviado por email ao endereço configurado na administração.

## Arquitetura

```
WordPress (PHP)  ──HTTP──►  proxy/FastAPI  ──Ollama──►  qwen3:8b (local)
                                   │
                                   ├── RAG: knowledge_base.json + BM25 sobre PDF de preços
                                   └── cache por palavras-chave (SQLite, Jaccard)
```

- Rota primária: **Ollama direto** (`http://127.0.0.1:11434`). Fallback opcional: gateway OpenAI-compatível.
- Sem APIs externas de cliente: os dados ficam na máquina (soberania local, GDPR).

## Requisitos

- PHP ≥ 7.4 (WordPress ≥ 5.8), Python 3.12, Ollama com `qwen3:8b` puxado.

## Instalação

```bash
composer  # não usado — sem dependências PHP externas

make setup                # venv + dependências do proxy
cp proxy/.env.example proxy/.env   # ajustar ao ambiente
make run-proxy            # proxy em http://127.0.0.1:5001
```

1. Copiar a pasta do plugin para `wp-content/plugins/xkaichat/`.
2. Ativar o plugin (cria tabelas `xkaichat_codes` e `xkaichat_messages` + opções).
3. Em *Settings → XKaiChat*: ajustar URL/timeout do proxy, email e estética.
4. Colocar `[xkaichat]` numa página ou ativar o widget global.

## Comandos

| Comando | Ação |
|---------|------|
| `make setup` | Cria `.venv` e instala deps do proxy |
| `make run-proxy` | Arranca o proxy (uvicorn, porta 5001) |
| `make test-php` | Testes PHP (runner sem deps WP) |
| `make test-proxy` | Pytest unit (e2e fica para `make qa`) |
| `make lint` | `php -l` em todos os ficheiros |
| `make qa` | E2E real contra o Ollama local |
| `make doctor` | Diagnóstico do ambiente |

## Notas de segurança

- Chave partilhada opcional (`PROXY_KEY` / `proxy_shared_key`) trocada no header `X-Xkai-Proxy-Key` com comparação em tempo constante.
- Códigos de verificação guardados em hash (sha256 + salt WP), TTL 10 min, máx. 5 tentativas, rate-limit por IP (3/10 min) e cooldown por email (60 s).
- Resumo por email paramétrico; mensagens retidas 30 dias por omissão.
- O proxy nunca inventa preços: o prompt só usa FACTOS OFICIAIS do RAG (PDF/FAQ).

## Estrutura

```
xkaichat.php                  # bootstrap do plugin
includes/                     # classes PHP (activator, i18n, messages, verification, proxy, summary)
admin/                        # settings + AJAX (clear cache / reindex)
public/                       # widget de chat (partial + CSS + JS)
proxy/                        # FastAPI + RAG + cache (ver proxy/README.md)
tests/                        # runner PHP + pytest
```