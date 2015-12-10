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

// define a function
Cloud::define("hello", function() {
    return "hello";
});

// define function with named params
Cloud::define("sayHello", function($params, $user) {
    return "hello {$params['name']}";
});

Cloud::onLogin(function($user) {
    return;
});

Cloud::onInsight(function($job) {
    return;
});

Cloud::onVerified("sms", function($user){
    return;
});

Cloud::beforeSave("TestObject", function($obj, $user) {
    $obj->set("__testKey", 42);
    return $obj;
});

LeanEngine::start();

