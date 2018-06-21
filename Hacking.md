Hacking
----

* Get and install [composer](https://getcomposer.org)
* Fork the SDK from leancloud/php-sdk
* Run `composer install` to get dependencies
* Setup app credential in env variables:

```
export LC_APP_ID=...
export LC_APP_KEY=...
export LC_APP_MASTER_KEY=...
export LC_API_REGION=US
```

* `make test` to run test
* `make doc` to build documentation (should running on PHP <= 7.2)

Thanks for your contribution!
