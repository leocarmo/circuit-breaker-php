test:
	@echo "---- Running tests ----"
	@./vendor/bin/phpunit --testdox tests

test-coverage:
	@echo "---- Running tests with coverage report ----"
	@./vendor/bin/phpunit --coverage-text
