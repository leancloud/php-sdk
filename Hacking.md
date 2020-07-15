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