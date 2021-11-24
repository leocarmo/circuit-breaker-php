test:
	@echo "---- Running tests ----"
	@docker-compose up -d redis
	@docker-compose run --rm composer test
	@docker-compose down

setup:
	@docker-compose build image
	@docker-compose run --rm composer install
