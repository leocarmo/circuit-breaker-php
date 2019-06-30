test:
	@echo "---- Running tests ----"
	@./vendor/bin/phpunit

test-coverage:
	@echo "---- Running tests with coverage report ----"
	@./vendor/bin/phpunit --coverage-text
