# XKaiChat — ferramentas de desenvolvimento
SHELL := /bin/bash
PY := .venv/bin/python
PIP := .venv/bin/pip
PHP_FILES := $(shell find . -type f -name '*.php' -not -path './.venv/*' -not -path './vendor/*')

.PHONY: setup run-proxy test test-php test-proxy lint qa format doctor clean

setup: ## Prepara ambiente (venv + deps do proxy)
	python3 -m venv .venv
	$(PIP) install -q -r proxy/requirements.txt
	$(PIP) install -q pytest
	@echo "setup complete"

run-proxy: ## Arranca o proxy (uvicorn)
	cd proxy && ../.venv/bin/uvicorn proxy:app --host 127.0.0.1 --port 5001

test: test-php test-proxy ## Corre todos os testes

test-php: ## Testes PHP (runner sem dependências WP)
	php tests/php/run-all.php

test-proxy: ## Pytest unit do proxy (e2e fica para `make qa`)
	$(PY) -m pytest tests/proxy -m 'not e2e' -q

lint: ## php -l em todos os ficheiros PHP
	@failed=0; \
	for f in $(PHP_FILES); do \
		out=$$(php -l "$$f" 2>&1) || { echo "$$out"; failed=1; }; \
	done; \
	if [ "$$failed" = "1" ]; then echo "lint FAILED"; exit 1; fi; \
	echo "lint ok ($(shell echo $(PHP_FILES) | wc -w) files)"

format: ## Formata o proxy Python (ruff se disponível)
	@if $(PY) -c "import ruff" 2>/dev/null; then $(PY) -m ruff format proxy tests/proxy; else echo "ruff não instalado — usar: $(PIP) install ruff"; fi

qa: ## E2E real contra o Ollama local
	$(PY) -m pytest tests/proxy/test_e2e.py -q

doctor: ## Verifica ambiente
	@php -v | head -1
	@python3 --version
	@curl -s --max-time 3 http://127.0.0.1:11434/api/tags >/dev/null && echo "ollama running" || echo "ollama offline (E2E indisponível)"

clean: ## Remove artefactos
	rm -rf .venv __pycache__ .pytest_cache proxy/__pycache__
	find . -name '*.pyc' -delete
	find proxy -name '*.idx' -delete
	find proxy -name 'cache_store' -type d -exec rm -rf {} + 2>/dev/null || true