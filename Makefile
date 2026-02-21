# Determine environment first
ENV := $(strip $(if $(filter prod,$(MAKECMDGOALS)),prod,$(if $(filter dev,$(MAKECMDGOALS)),dev,)))

# Executables (local)
ifeq ($(ENV),dev)
    DOCKER_COMP = docker compose --env-file .env.local
else ifeq ($(ENV),prod)
    DOCKER_COMP = SERVER_NAME=${SERVER_NAME} APP_SECRET=${APP_SECRET} CADDY_MERCURE_JWT_SECRET=${CADDY_MERCURE_JWT_SECRET} NEW_RELIC_LICENSE_KEY=${NEW_RELIC_LICENSE_KEY} docker compose
else
    DOCKER_COMP = docker compose --env-file .env.local
endif

DOCKER_COMP_DEBUG = XDEBUG_MODE=debug docker compose --env-file .env.local

# Docker containers
PHP_CONT = $(DOCKER_COMP) exec php

# Executables
PHP      = $(PHP_CONT) php
COMPOSER = $(PHP_CONT) composer
SYMFONY  = $(PHP) bin/console

# Misc
.DEFAULT_GOAL = help
.PHONY        : help build up start down logs sh composer vendor sf cc test dev prod check-env debug ps bash phpstan rector-dry rector ecs-dry ecs lint-twig lint-yaml lint-xliff lint-container lint-doctrine lint quality fix

## —— 🎵 🐳 The Symfony Docker Makefile 🐳 🎵 ——————————————————————————————————
help: ## Outputs this help screen
	@grep -E '(^[a-zA-Z0-9\./_-]+:.*?##.*$$)|(^##)' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}{printf "\033[32m%-30s\033[0m %s\n", $$1, $$2}' | sed -e 's/\[32m##/[33m/'

## —— Docker 🐳 ————————————————————————————————————————————————————————————————
build: check-env ## Builds the Docker images (make build dev|prod)
ifeq ($(ENV),prod)
	@$(DOCKER_COMP) -f compose.yaml -f compose.prod.yaml build --pull --no-cache
else
	@$(DOCKER_COMP) build --pull --no-cache
endif

up: check-env ## Start the docker hub in detached mode (make up dev|prod)
ifeq ($(ENV),prod)
	@$(DOCKER_COMP) -f compose.yaml -f compose.prod.yaml up --wait
else
	@$(DOCKER_COMP) up --detach
endif
start: check-env build up ## Build and start the containers (make start dev|prod)

debug: ## Start the docker hub in detached mode (no logs) with xdebug enabled for step debug
	@$(DOCKER_COMP_DEBUG) up --detach

ps: ## List containers with status
	@$(DOCKER_COMP) ps --format "table {{.Name}}\t{{.Status}}\t{{.Ports}}"

down: ## Stop the docker hub
	@$(DOCKER_COMP) down --remove-orphans

logs: ## Show live logs
	@$(DOCKER_COMP) logs --tail=0 --follow

sh: ## Connect to the FrankenPHP container
	@$(PHP_CONT) sh

bash: ## Connect to the FrankenPHP container via bash so up and down arrows go to previous commands
	@$(PHP_CONT) bash

test: ## Start tests with phpunit, pass the parameter "c=" to add options to phpunit, example: make test c="--group e2e --stop-on-failure"
	@$(eval c ?=)
	@$(DOCKER_COMP) exec -e APP_ENV=test php bin/phpunit $(c)


## —— Composer 🧙 ——————————————————————————————————————————————————————————————
composer: ## Run composer, pass the parameter "c=" to run a given command, example: make composer c='req symfony/orm-pack'
	@$(eval c ?=)
	@$(COMPOSER) $(c)

vendor: ## Install vendors according to the current composer.lock file
vendor: c=install --prefer-dist --no-dev --no-progress --no-scripts --no-interaction
vendor: composer

## —— Symfony 🎵 ———————————————————————————————————————————————————————————————
sf: ## List all Symfony commands or pass the parameter "c=" to run a given command, example: make sf c=about
	@$(eval c ?=)
	@$(SYMFONY) $(c)

cc: c=c:c ## Clear the cache
cc: sf

check-env:
ifeq ($(ENV),)
	$(error Please specify environment: make <target> dev|prod)
endif

dev:
	@:

prod:
	@:

## —— Quality 🔍 ———————————————————————————————————————————————————————————————
phpstan: ## Run PHPStan static analysis at level 9
	@$(PHP_CONT) php -d memory_limit=512M vendor/bin/phpstan analyse --configuration=phpstan.dist.neon --no-progress

rector-dry: ## Run Rector in dry-run mode (CI — fails if changes are detected)
	@$(PHP_CONT) vendor/bin/rector process --dry-run --no-progress-bar

rector: ## Run Rector and apply all automated fixes
	@$(PHP_CONT) vendor/bin/rector process --no-progress-bar

ecs-dry: ## Run ECS in check mode (CI — fails if violations are found)
	@$(PHP_CONT) vendor/bin/ecs check --no-progress-bar

ecs: ## Run ECS and apply all coding standard fixes
	@$(PHP_CONT) vendor/bin/ecs check --fix --no-progress-bar

## —— Linters 🧹 ———————————————————————————————————————————————————————————————
lint-twig: ## Lint all Twig templates
	@$(SYMFONY) lint:twig templates/

lint-yaml: ## Lint all YAML configuration files
	@$(SYMFONY) lint:yaml config/

lint-xliff: ## Lint all XLIFF translation files
	@$(SYMFONY) lint:xliff translations/

lint-container: ## Validate the Symfony DI container
	@$(SYMFONY) lint:container

lint-doctrine: ## Validate Doctrine entity mappings (skip DB sync check)
	@$(SYMFONY) doctrine:schema:validate --skip-sync

lint: lint-twig lint-yaml lint-xliff lint-container lint-doctrine ## Run all Symfony linters in sequence

## —— Combined 🚀 ——————————————————————————————————————————————————————————————
quality: ecs-dry phpstan rector-dry lint ## Run all quality checks (CI mode — no fixes applied)

fix: ecs rector ## Apply all automated fixes (ECS then Rector)
