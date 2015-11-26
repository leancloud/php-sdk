<?php

/**
 * Define functions and hooks on cloud
 *
 *```php
 * LeanEngine::define("sayHello", function($params, $user) {
 * });
 *
 * LeanEngine::afterSave("TestObject", function($object, $user) {
 * });
 *
 * LeanEngine::onLogin(function($user) {
 * });
 *
 * LeanEngine::onVerified("sms", function($user) {
 * });
 *
 * LeanEngine::onInsight(function($params) {
 * });
 *```
 */
class Cloud {

    /**
     * Map of function (or hook) name to callable functions
     *
     * @var array
     */
    private static $repo = array();

    public static function getFunc($funcName) {}
    public static function getHookName($subject, $hookName) {}

    /**
     * Define a cloud function
     *
     * The POST body will be json decoded into array, which will be passed
     * callable as first argument. The current user, if available, will be
     * passed as second arg. Example:
     *
     * ```php
     * Cloud::define("sayHello", function($params, $user) {
     *     return "Hello $params['name']!"
     * });
     * ```
     *
     * @param string   $funcName
     * @param callable $func The function accepts two arguments
     */
    public static function define($funcName, $func) {
        self::$repo[$funcName] = $func;
    }

    /**
     * Define after save hook for a class
     *
     *
     * @param string   $className
     * @param callable $func
     */
    public static function afterSave($className, $func) {
        self::define("__afterSave_{$className}", $func);
    }

    /**
     * Register hook on user verified email or sms
     *
     * @param string   $type Either sms or email
     * @param callable $func
     */
    public static function onVerified($type, $func) {}
    public static function onLogin($func) {}
    public static function onBigQuery($event, $func) {}
    public static function onInsight($event, $func) {}
}

