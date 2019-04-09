.PHONY: ci test phpunit cs stan covers

DEFAULT_GOAL := ci

ci: test cs

test: covers phpunit

cs: phpcs stan

phpunit:
	./vendor/bin/phpunit

phpcs:
	./vendor/bin/phpcs -p -s

stan:
	./vendor/bin/phpstan analyse --level=1 --no-progress src/ tests/

covers:
	./vendor/bin/covers-validator

