.PHONY: help up down build rebuild watch shell bash mysql redis migrate seed fresh test tinker logs npm-dev npm-build composer-install artisan

COMPOSE_PROJECT_NAME ?= ticketing
DB_PASSWORD ?= secret
COMPOSE = docker compose -p $(COMPOSE_PROJECT_NAME)
# Default target
help:
	@echo "Docker Laravel Development Commands"
	@echo ""
	@echo "Usage: make [command]"
	@echo ""
	@echo "Docker Commands:"
	@echo "  up            Start all containers in background"
	@echo "  down          Stop and remove all containers"
	@echo "  build         Build Docker images"
	@echo "  rebuild       Rebuild Docker images (no cache)"
	@echo "  watch         Watch files and rebuild/refresh containers"
	@echo "  logs          Show container logs (follow mode)"
	@echo ""
	@echo "Shell Access:"
	@echo "  shell         Open bash shell in app container"
	@echo "  mysql         Open MySQL CLI"
	@echo "  redis         Open Redis CLI"
	@echo ""
	@echo "Laravel Commands:"
	@echo "  migrate       Run database migrations"
	@echo "  seed          Run database seeders"
	@echo "  fresh         Fresh migrate with seeders"
	@echo "  test          Run PHPUnit tests"
	@echo "  tinker        Open Laravel Tinker"
	@echo ""
	@echo "Dependencies:"
	@echo "  composer-install  Install Composer dependencies"
	@echo "  npm-dev           Run Vite dev server"
	@echo "  npm-build         Build frontend assets"
	@echo ""
	@echo "Utilities:"
	@echo "  artisan           Run any artisan command (usage: make artisan cmd='...')"

# Docker commands
up:
	docker-compose up -d

down:
	docker-compose down

build:
	docker-compose build

rebuild:
	docker-compose build --no-cache

watch:
	$(COMPOSE) watch

logs:
	docker-compose logs -f

# Shell access
shell:
	docker-compose exec app bash

bash: shell

mysql:
	docker-compose exec mysql mysql -u ticketing -psecret ticketing

redis:
	docker-compose exec redis redis-cli

# Laravel commands
migrate:
	docker-compose exec app php artisan migrate

seed:
	docker-compose exec app php artisan db:seed

fresh:
	docker-compose exec app php artisan migrate:fresh --seed

test:
	docker-compose exec app php artisan test

tinker:
	docker-compose exec app php artisan tinker

# Dependencies
composer-install:
	docker-compose exec app composer install

npm-dev:
	npm run dev

npm-build:
	npm run build

# Utility - run any artisan command
# Usage: make artisan cmd="make:model Post"
artisan:
	docker-compose exec app php artisan $(cmd)
