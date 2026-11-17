SHELL := /bin/sh
.DEFAULT_GOAL := help
COMPOSE = docker compose --env-file .env -f infra/compose.yaml

.PHONY: help env install up down ps logs migrate setup dev dev-uns api web test typecheck build docs check

help:
	@echo "SCADA Studio commands:"
	@echo "  make setup       Install, start PostgreSQL/MQTT, run migrations"
	@echo "  make dev         Start everything: infra, migrations, API, web, MQTT consumer, simulator"
	@echo "  make dev-uns     Same, with the simulator in UNS (ISA-95) profile"
	@echo "  make api         Start Symfony API (port 8000)"
	@echo "  make web         Start Nuxt frontend"
	@echo "  make up/down     Start/stop Docker infrastructure"
	@echo "  make logs/ps     Inspect infrastructure"
	@echo "  make test/check  Run tests and quality checks"

env:
	@test -f .env || cp .env.example .env

install:
	cd apps/api && composer install
	npm ci

up: env
	$(COMPOSE) up -d --wait

down:
	$(COMPOSE) down

ps:
	$(COMPOSE) ps

logs:
	$(COMPOSE) logs -f --tail=100

migrate:
	cd apps/api && php bin/console doctrine:migrations:migrate --no-interaction

setup: env install up
	@if [ ! -f apps/api/.env.local ]; then printf '%s\n' 'DATABASE_URL="postgresql://scada:replace-with-a-local-development-password@127.0.0.1:5432/scada?serverVersion=17&charset=utf8"' > apps/api/.env.local; echo 'Edit apps/api/.env.local to match POSTGRES_PASSWORD in .env, then run make migrate.'; else $(MAKE) migrate; fi
	@echo "Then run: make dev (or two terminals: make api and make web)"

dev:
	node scripts/dev.mjs $(ARGS)

dev-uns:
	node scripts/dev.mjs --uns $(ARGS)

api:
	cd apps/api && php -S 127.0.0.1:8000 -t public public/index.php

web:
	npm run dev:web

test:
	cd apps/api && APP_ENV=test php bin/phpunit

typecheck:
	npm run typecheck:web

build:
	npm run build:web

docs:
	node scripts/check-docs.mjs

check: typecheck docs test
