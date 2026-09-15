# XKaiChat — Makefile
# Comandos essenciais: setup, test, lint, format, check, doctor, clean

SHELL := /bin/bash
PY := .venv/bin/python
PIP := .venv/bin/pip
PHP_FILES := $(shell find . -type f -name '*.php' -not -path './.venv/*' -not -path './vendor/*')
REFERENCE_IGNORE := .venv vendor node_modules

.PHONY: setup run-proxy run-broker test test-php test-proxy test-broker test-aes lint format check docs-check qa doctor clean docker-build docker-run docker-stop docker-logs docker-clean

## Configuração
setup: ## Prepara ambiente (venv + deps proxy + broker)
	python3 -m venv .venv
	$(PIP) install -q -r proxy/requirements.txt
	$(PIP) install -q -r broker/requirements.txt
	$(PIP) install -q pytest
	@echo "✅ setup complete"

run-proxy: ## Arranca o proxy (uvicorn)
	cd proxy && ../.venv/bin/uvicorn proxy:app --host 127.0.0.1 --port 5001

run-broker: ## Arranca o broker (uvicorn)
	cd broker && ../.venv/bin/uvicorn broker:app --host 127.0.0.1 --port 5002

## Testes
test: test-php test-proxy test-broker ## Corre todos os testes

test-php: ## Testes PHP (runner sem dependências WP)
	php tests/php/run-all.php

test-proxy: ## Testes pytest do proxy (unit — e2e fica para `make qa`)
	$(PY) -m pytest tests/proxy -m 'not e2e' -q

test-broker: ## Testes pytest do broker
	$(PY) -m pytest tests/broker -q

test-aes: ## Smoke test do gate pre-commit (fecho de tickets)
	@if [ -x tests/aes/test-pre-commit-close-gate.sh ]; then \
		bash tests/aes/test-pre-commit-close-gate.sh; \
	else \
		echo "⚠️  sem tests/aes — skip"; \
	fi

## Lint
lint: ## php -l em todos os ficheiros PHP
	@failed=0; \
	for f in $(PHP_FILES); do \
		out=$$(php -l "$$f" 2>&1) || { echo "$$out"; failed=1; }; \
	done; \
	if [ "$$failed" = "1" ]; then echo "❌ lint FAILED"; exit 1; fi; \
	echo "✅ lint ok ($(shell echo $(PHP_FILES) | wc -w) files)"

## Formato (proxy Python — autopep8/ruff se disponível; senão só verifica)
format:
	@if $(PY) -c "import ruff" 2>/dev/null; then $(PY) -m ruff format proxy tests/proxy broker tests/broker; else echo "ruff não instalado — usar: $(PIP) install ruff"; fi

## Gates
check: lint test test-aes docs-check ## Gates completos AES
	@echo "✅ Todos os gates passaram"

docs-check: ## Verifica docs exigidas
	@for d in docs/VISION.md docs/REQUIREMENTS.md docs/ROADMAP.md docs/QUALITY_GATES.md docs/CHECKLIST.md docs/PERSONAS.md CLAUDE.md; do \
		[ -f "$$d" ] || { echo "❌ falta $$d"; exit 1; }; \
	done; \
	echo "✅ docs ok"

## QA end-to-end (Ollama local)
qa: ## E2E contra Ollama (regenera docs/QA.md)
	$(PY) -m pytest tests/proxy/test_e2e.py -q 2>&1 | tee docs/QA.md

## Diagnóstico
doctor: ## Verifica ambiente
	@php -v | head -1
	@python3 --version
	@curl -s --max-time 3 http://127.0.0.1:11434/api/tags >/dev/null && echo "✅ ollama running" || echo "⚠️  ollama offline (E2E indisponível)"

## Docker
docker-build: ## Build das imagens Docker (proxy + broker)
	docker build -t xkaichat-proxy ./proxy
	docker build -t xkaichat-broker ./broker

docker-run: ## Arranca stack completo com docker-compose (dev local)
	docker-compose up -d

docker-stop: ## Para stack docker-compose
	docker-compose down

docker-logs: ## Logs do stack docker-compose
	docker-compose logs -f

docker-clean: ## Remove imagens e volumes Docker do projeto
	docker-compose down -v --rmi all 2>/dev/null || true
	docker rmi xkaichat-proxy xkaichat-broker 2>/dev/null || true

## Limpeza
clean: ## Remove artefactos
	rm -rf .venv __pycache__ .pytest_cache proxy/__pycache__ broker/__pycache__
	find . -name '*.pyc' -delete
	find proxy -name '*.idx' -delete
	find proxy -name 'cache_store' -type d -exec rm -rf {} + 2>/dev/null || true
	find broker -name '*.idx' -delete 2>/dev/null || true