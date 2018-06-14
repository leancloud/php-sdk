test:
	vendor/bin/phpunit test
	php -r 'exit(PHP_VERSION_ID >= 70200 ? 0 : 1);' || vendor/bin/phpunit test/Php72ObjectDeprecated.php

release:
	./release.sh $V

doc:
	vendor/bin/apigen generate --source src --destination docs

test_engine:
	php -S ${LEANCLOUD_APP_HOST}:${LEANCLOUD_APP_PORT} test/engine/index.php

.PHONY: test doc test_engine
