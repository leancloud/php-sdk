<?php

require_once "../../src/autoload.php";

use LeanCloud\LeanClient;
use LeanCloud\Engine\LeanEngine;
use LeanCloud\Engine\Cloud;

LeanClient::initialize(
    getenv("LC_APP_ID"),
    getenv("LC_APP_KEY"),
    getenv("LC_APP_MASTER_KEY")
);

Cloud::define("hello", function() {
    return "hello";
});

Cloud::define("sayHello", function($params, $user) {
    return "hello {$params['name']}";
});

$engine = new LeanEngine();
$engine->start();

