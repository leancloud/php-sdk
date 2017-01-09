<?php

require_once "src/autoload.php";

use LeanCloud\Client;
use LeanCloud\Engine\LeanEngine;
use LeanCloud\Engine\Cloud;
use LeanCloud\Storage\CookieStorage;

Client::initialize(
    getenv("LC_APP_ID"),
    getenv("LC_APP_KEY"),
    getenv("LC_APP_MASTER_KEY")
);
Client::setStorage(new CookieStorage());
Client::useRegion(getenv("LC_API_REGION"));

// define a function
Cloud::define("hello", function() {
    return "hello";
});

// define function with named params
Cloud::define("sayHello", function($params, $user) {
    return "hello {$params['name']}";
});

Cloud::define("_messageReceived", function($params, $user){
    if ($params["convId"]) {
        return array("drop" => false);
    } else {
        return array("drop" => true);
    }
});

Cloud::define("getMeta", function($params, $user, $meta) {
    return array("remoteAddress" => $meta["remoteAddress"]);
});

Cloud::define("updateObject", function($params, $user) {
    $obj = $params["object"];
    $obj->set("__testKey", 42);
    return $obj;
});

Cloud::onLogin(function($user) {
    error_log("Logging a user");
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

Cloud::afterSave("TestObject", function($obj, $user) {
    return;
});

Cloud::beforeDelete("TestObject", function($obj, $user) {
    return;
});

$engine = new LeanEngine();
$engine->start();

