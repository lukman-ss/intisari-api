.PHONY: install serve test migrate fresh seed check

install:
	composer install
	cp -n .env.example .env || true
	touch database/database.sqlite || true

serve:
	composer serve

test:
	composer test

migrate:
	composer migrate

fresh:
	composer fresh

seed:
	composer seed

check:
	composer source:check
	composer docs:check
