test:
	vendor/bin/phpunit --bootstrap src/autoload.php tests

release:
	./release.sh $V

doc:
	vendor/bin/apigen generate --source src --destination docs

.PHONY: test doc
