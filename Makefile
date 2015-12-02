test:
	vendor/bin/phpunit

release:
	./release.sh $V

doc:
	vendor/bin/apigen generate --source src --destination docs

engine:
	php -t tests/engine -S $(LC_APP_HOST):$(LC_APP_PORT)

.PHONY: test doc engine
