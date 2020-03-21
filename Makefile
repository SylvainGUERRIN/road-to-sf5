SHELL := /bin/bash

tests:
	symfony console doctrine:fixtures:load -n
	php bin/phpunit
.PHONY: tests
