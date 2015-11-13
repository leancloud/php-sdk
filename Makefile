test:
	vendor/bin/phpunit

release:
	./release.sh $V

doc:
	vendor/bin/apigen generate --source src --destination docs

.PHONY: test doc
