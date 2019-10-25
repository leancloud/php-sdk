Hacking
----

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

* `make doc` to build documentation (should running on PHP <= 7.2)

Thanks for your contribution!
