# Hacking

## Pull Request

* Get and install [composer](https://getcomposer.org)
* Fork the SDK from leancloud/php-sdk
* Run `composer install` to get dependencies
* Setup app credential in env variables:

    ```sh
    export LC_APP_ID=...
    export LC_APP_KEY=...
    export LC_APP_MASTER_KEY=...
    export LC_API_REGION=US
    export LEANCLOUD_APP_HOST="127.0.0.1"
    export LEANCLOUD_APP_PORT=8081
    export LEANCLOUD_WILDCARD_DOMAIN="lncldglobal.com"
    ```

* Run tests:

    ```sh
    make test_engine &
    make test
    ```
  
    Run one single test:
    
    ```sh
    vendor/bin/phpunit --filter testInitializeWithString test/QueryTest.php
    ```

* `make doc` to build documentation.
   The make task uses PHP 5.6, to install it on recent versions of macOS,
   see https://github.com/eXolnet/homebrew-deprecated/pull/25

* Send a pull request at leancloud/php-sdk

Thanks for your contribution!

## Prepare a Release

Make sure all tests are passed.

Run `make release V=MAJOR.MINOR.PATCH` (e.g. `make release V=0.11.0`),
and edit `Changelog.md` (git log subjects are for reference only, do not leave them unchanged).

Commit changes and send a pull request at leancloud/php-sdk.

If everything is O.K., the maintainer will merge the pull request, push a new tag, and publish a new release at GitHub.
Then a new version will be published at Packagist automatically.

## Run Tests with Coverage

To run tests with coverage, you need to have xdebug enabled.
In other words, make sure "with Xdebug" is in the output of `php -v`.

```sh
php -v
PHP 7.2.34 (cli) (built: Nov 30 2020 14:07:08) ( NTS )
Copyright (c) 1997-2018 The PHP Group
Zend Engine v3.2.0, Copyright (c) 1998-2018 Zend Technologies
    with Xdebug v3.0.1, Copyright (c) 2002-2020, by Derick Rethans
    with Zend OPcache v7.2.34, Copyright (c) 1999-2018, by Zend Technologies
```

By default, PHP does not enable xdebug.
You can install xdebug with pecl to enable it.
For example, install xdebug for PHP 7.2 on macOS:

```sh
brew install php@7.2
$(brew --prefix php@7.2)/bin/pecl install --force xdebug
```

Once xdebug is enabled, run tests with coverage with the following commands:

```sh
# export environment variables as usual
make test_engine &
XDEBUG_MODE=coverage ./vendor/bin/phpunit --coverage-clover=coverage.xml test
```
