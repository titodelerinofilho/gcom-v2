.DEFAULT_GOAL := help
.NOTPARALLEL:

DOCKER_COMPOSE ?= docker compose
ENV_FILE ?= .env
COMPOSE = $(DOCKER_COMPOSE) --env-file "$(ENV_FILE)" -f compose.yml
TOOLS = $(COMPOSE) --profile tools
PHP = $(TOOLS) run --rm --no-deps backend-tools
NODE = $(TOOLS) run --rm --no-deps frontend-tools
export LOCAL_UID := $(shell id -u)
export LOCAL_GID := $(shell id -g)

.PHONY: help env config init build up down restart status logs migrate database-sync admin backup \
	backend-tools-build frontend-tools-build backend-deps frontend-deps deps \
	phpcs phpcs-fix test backend-check frontend-check format check tools-down

help: ## Mostra os comandos disponíveis.
	@awk 'BEGIN {FS = ":.*## "} /^[a-zA-Z_-]+:.*## / {printf "  %-22s %s\n", $$1, $$2}' $(MAKEFILE_LIST)

env: ## Prepara .env e gera segredos locais ausentes, preservando os existentes.
	@sh scripts/init-env.sh "$(ENV_FILE)"

config: env ## Valida a configuração do Compose.
	$(COMPOSE) config --quiet

init: config ## Gera segredos, compila, inicia serviços e aplica migrations.
	$(MAKE) build
	$(COMPOSE) up -d --wait postgres
	$(MAKE) database-sync
	$(COMPOSE) up -d --wait backend
	$(MAKE) migrate
	$(COMPOSE) up -d --wait frontend nginx backup
	@printf '%s\n' 'Aplicação iniciada. Use make admin para criar o primeiro administrador.'

build: config ## Compila as imagens da aplicação.
	$(COMPOSE) build postgres backend frontend nginx backup

up: config ## Inicia todos os serviços e aguarda os healthchecks.
	$(COMPOSE) up -d --wait

down: ## Para os serviços sem apagar os volumes.
	$(COMPOSE) down

restart: config ## Reinicia os serviços.
	$(COMPOSE) restart

status: ## Mostra o estado dos serviços.
	$(COMPOSE) ps

logs: ## Acompanha logs JSON dos serviços; Ctrl+C encerra.
	$(COMPOSE) logs --follow --tail=100

migrate: config ## Aplica migrations no banco da aplicação.
	$(COMPOSE) exec -T backend php bin/console doctrine:migrations:migrate --no-interaction

database-sync: config ## Sincroniza contas PostgreSQL com o .env sem apagar dados.
	$(COMPOSE) exec -T postgres sh /docker-entrypoint-initdb.d/10-app.sh

admin: config ## Cria administrador interativamente, sem senha na linha de comando.
	$(COMPOSE) exec backend php bin/console app:user:create-admin

backup: config ## Executa um backup imediato.
	$(COMPOSE) exec -T backup /usr/local/bin/backup.sh

backend-tools-build: config
	$(TOOLS) build backend-tools

frontend-tools-build: config
	$(TOOLS) build frontend-tools

backend-deps: backend-tools-build ## Instala dependências PHP incluindo pacotes de desenvolvimento.
	$(PHP) composer install --prefer-dist --no-interaction --no-scripts

frontend-deps: frontend-tools-build ## Instala dependências Node a partir do lockfile.
	$(NODE) npm ci

deps: backend-deps frontend-deps ## Instala todas as dependências de desenvolvimento.

phpcs: backend-deps ## Verifica estilo PHP com o PHP-CS-Fixer configurado.
	$(PHP) composer style:check

phpcs-fix: backend-deps ## Corrige estilo PHP incluindo migrations.
	$(PHP) composer style:fix

test: backend-deps ## Executa migrations e PHPUnit em PostgreSQL isolado e descartável.
	@set -eu; trap '$(TOOLS) rm --stop --force --volumes postgres-test >/dev/null' EXIT; \
		$(TOOLS) up -d --wait postgres-test; \
		$(PHP) sh -c 'php bin/console doctrine:migrations:migrate --no-interaction && composer test && php bin/console doctrine:schema:validate'

backend-check: backend-deps ## Valida YAML e injeção de dependências do Symfony.
	$(PHP) php bin/console lint:yaml config
	$(PHP) php bin/console lint:container

frontend-check: frontend-deps ## Verifica formatação, tipos, lint e build do frontend.
	$(NODE) npm run format:check
	$(NODE) npm run typecheck
	$(NODE) npm run lint
	$(NODE) npm run build

format: backend-deps frontend-deps ## Aplica PHP-CS-Fixer e Prettier nos arquivos locais.
	$(PHP) composer style:fix
	$(NODE) npm run format

check: phpcs test backend-check frontend-check ## Executa todas as verificações de qualidade.

tools-down: ## Remove containers de ferramentas e testes, preservando a aplicação.
	$(TOOLS) rm --stop --force --volumes backend-tools frontend-tools postgres-test
