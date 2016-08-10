test:
	vendor/bin/phpunit test

release:
	./release.sh $V

doc:
	vendor/bin/apigen generate --source src --destination docs

test_engine:
	php -S ${LC_APP_HOST}:${LC_APP_PORT} test/engine/index.php

.PHONY: test doc test_engine
