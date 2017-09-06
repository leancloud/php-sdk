test:
	vendor/bin/phpunit test

release:
	./release.sh $V

doc:
	vendor/bin/apigen generate --source src --destination docs

test_engine:
	php -S ${LEANCLOUD_APP_HOST}:${LEANCLOUD_APP_PORT} test/engine/index.php

.PHONY: test doc test_engine
