test:
	vendor/bin/phpunit --bootstrap src/autoload.php tests

doc:
	vendor/bin/apigen generate --source src --destination docs

.PHONY: test doc
