<?php
namespace LeanCloud\Engine;

use LeanCloud\Client;

/**
 * Cloud functions and hooks repository
 *
 */

class Cloud {

    /**
     * Map of function (or hook) name to callable functions
     *
     * @var array
     */
    private static $repo = array();

    /**
     * Hook map
     */
    private static $hookMap = array(
        "beforeSave"   => "__before_save_for_",
        "afterSave"    => "__after_save_for_",
        "beforeUpdate" => "__before_update_for_",
        "afterUpdate"  => "__after_update_for_",
        "beforeDelete" => "__before_delete_for_",
        "afterDelete"  => "__after_delete_for_",
        "onLogin"      => "__on_login_",
        "onVerified"   => "__on_verified_",
        "onComplete"   => "__on_complete_"
    );

    public static function getKeys() {
        return array_keys(self::$repo);
    }

    /**
     * Get defined function or hook by internal name
     *
     * @param string $funcName Name of function or hook
     * @return callable|null
     */
    private static function getFunc($funcName) {
        return (isset(self::$repo[$funcName]) ? self::$repo[$funcName] : null);
    }

    /**
     * Get internal hook name
     *
     * @param string $hookName
     * @return string
     */
    private static function getHookPrefix($hookName) {
        return (isset(self::$hookMap[$hookName]) ?
                self::$hookMap[$hookName] : null);
    }

    /**
     * Define a cloud function
     *
     * The function shall take two arguments: the first is an array of
     * parameters, the second is user in the session. Example:
     *
     * ```php
     * Cloud::define("sayHello", function($params, $user) {
     *     return "Hello {$params['name']}!";
     * });
     * ```
     *
     * @param string   $funcName
     * @param callable $func
     * @see self::run
     */
    public static function define($funcName, $func) {
        self::$repo[$funcName] = $func;
    }

    /**
     * Define before save hook for a class
     *
     * The function shall take two arguments: the first one is class
     * object, the second is user if available in session. If your $func
     * throws `FunctionError`, the save will be rejected. Example:
     *
     * ```php
     * Cloud::beforeSave("TestObject", function($object, $user) {
     *     $title = $object->get("title");
     *     if (strlen($title) > 140) {
     *         // Throw error and reject the save operation.
     *         throw new FunctionError("Title is too long", 1);
     *     }
     *     // else object will be saved.
     * });
     * ```
     *
     * @param string $className
     * @param callable $func
     * @see FunctionError
     */
    public static function beforeSave($className, $func) {
        $name = self::getHookPrefix("beforeSave") . $className;
        self::define($name, $func);
    }

    /**
     * Define after save hook for a class
     *
     * The function shall take two arguments: the first one is class
     * object, the second is user if available in session. Any error
     * in after hook will be ignored.
     *
     * @param string $className
     * @param callable $func
     * @see FunctionError
     */
    public static function afterSave($className, $func) {
        $name = self::getHookPrefix("afterSave") . $className;
        self::define($name, $func);
    }

    /**
     * Define before update hook for a class
     *
     * The function shall take two arguments: the first one is class
     * object, the second is user if available in session. If your $func
     * throws `FunctionError`, the update will be rejected.
     *
     * @param string $className
     * @param callable $func
     * @see FunctionError
     */
    public static function beforeUpdate($className, $func) {
        $name = self::getHookPrefix("beforeUpdate") . $className;
        self::define($name, $func);
    }

    /**
     * Define after update hook for a class
     *
     * The function shall take two arguments: the first one is class
     * object, the second is user if available in session. Any error
     * in $func will be ignored.
     *
     * @param string $className
     * @param callable $func
     * @see FunctionError
     */
    public static function afterUpdate($className, $func) {
        $name = self::getHookPrefix("afterUpdate") . $className;
        self::define($name, $func);
    }

    /**
     * Define before delete hook for a class
     *
     * The function shall take two arguments: the first one is class
     * object, the second is user if available in session. If your $func
     * throws `FunctionError`, the delete will be rejected.
     *
     * @param string $className
     * @param callable $func
     * @see FunctionError
     */
    public static function beforeDelete($className, $func) {
        $name = self::getHookPrefix("beforeDelete") . $className;
        self::define($name, $func);
    }

    /**
     * Define after delete hook for a class
     *
     * The function shall take two arguments: the first one is class
     * object, the second is user if available in session. Any error
     * in $func will be ignored.
     *
     * @param string $className
     * @param callable $func
     * @see FunctionError
     */
    public static function afterDelete($className, $func) {
        $name = self::getHookPrefix("afterDelete") . $className;
        self::define($name, $func);
    }

    /**
     * Define hook for when user tries to login
     *
     * The function takes one argument, the login user. A `FunctionError`
     * could be thrown in the $func, which will reject the user for login.
     *
     * @param callable $func
     * @see self::runOnLogin
     */
    public static function onLogin($func) {
        self::define("__on_login__User", $func);
    }


    /**
     * Define hook for when user verified sms or email
     *
     * The function takes one argument, the verified user.
     *
     * @param string   $type Either "sms" or "email"
     * @param callable $func
     * @see self::runOnVerified
     */
    public static function onVerified($type, $func) {
        self::define("__on_verified_{$type}", $func);
    }

    /**
     * Define on complete hook for big query
     *
     * The function takes one argument, the big query job info as array:
     *
     * ```php
     * array(
     *     "id" => "job id",
     *     "status" => "OK/ERROR",
     *     "message" => "..."
     * );
     * ```
     *
     * @param callable $func
     * @see self::runOnInsight
     */
    public static function onInsight($func) {
        self::define("__on_complete_bigquery_job", $func);
    }

    /**
     * Run cloud function
     *
     * Example:
     *
     * ```php
     * LeanEngine::run("sayHello", array("name" => "alice"), $user);
     * // sayHello(array("name" => "alice"), $user);
     * ```
     *
     * @param string   $funcName Name of defined function
     * @param array    $data     Array of parameters passed to function
     * @param User $user     Request user
     * @param array    $meta     Optional parameters that will be passed to
     *                           user function
     * @return mixed
     * @throws FunctionError
     * @see self::define
     */
    public static function run($funcName, $params, $user=null, $meta=array()) {
        $func = self::getFunc($funcName);
        if (!$func) {
            throw new FunctionError("Cloud function not found.", 404);
        }
        return call_user_func($func, $params, $user, $meta);
    }

    /**
     * Start cloud function Stand-alone mode, start to process request.
     */
    public static function start() {
        Client::initialize(
            getenv("LEANCLOUD_APP_ID"),
            getenv("LEANCLOUD_APP_KEY"),
            getenv("LEANCLOUD_APP_MASTER_KEY")
        );

        $engine = new LeanEngine();
        $engine->start();
    }

    public static function stop() {

    }

    /**
     * Run cloud hook
     *
     * Example:
     *
     * ```php
     * LeanEngine::runHook("TestObject", "beforeUpdate", $object, $user);
     * // hook($object, $user);
     * ```
     *
     * @param string $className  Classname
     * @param string $hookName   Hook name, e.g. beforeUpdate
     * @param LeanObject $object The object of attached hook
     * @param User   $user   Request user
     * @param array      $meta   Optional parameters that will be passed to
     *                           user function
     * @return mixed
     * @throws FunctionError
     */
    public static function runHook($className, $hookName, $object,
                                   $user=null,
                                   $meta=array()) {
        $name = self::getHookPrefix($hookName) . $className;
        $func = self::getFunc($name);
        if (!$func) {
            throw new FunctionError("Cloud hook `{$name}' not found.",
                                    404);
        }
        return call_user_func($func, $object, $user, $meta);
    }

    /**
     * Run hook when a user logs in
     *
     * @param User $user The user object that tries to login
     * @param array    $meta Optional parameters that will be passed to
     *                       user function
     * @throws FunctionError
     * @see self::onLogin
     */
    public static function runOnLogin($user, $meta=array()) {
        return self::runHook("_User", "onLogin", $user, $meta);
    }

    /**
     * Run hook when user verified by Email or SMS
     *
     * @param string   $type Either "sms" or "email", case-sensitive
     * @param User $user The verifying user
     * @param array    $meta Optional parameters that will be passed to
     *                       user function
     * @throws FunctionError
     * @see self::onVerified
     */
    public static function runOnVerified($type, $user, $meta=array()) {
        $name = "__on_verified_{$type}";
        $func = self::getFunc($name);
        if (!$func) {
            throw new FunctionError("Cloud hook `{$name}' not found.",
                                    404);
        }
        return call_user_func($func, $user, $meta);
    }

    /**
     * Run hook on big query complete
     *
     * @param array $params Big query job info
     * @param array $meta   Optional parameters that will be passed to
     *                      user function
     * @return mixed
     * @throws FunctionError
     * @see self::onInsight
     */
    public static function runOnInsight($params, $meta=array()) {
        $name = "__on_complete_bigquery_job";
        $func = self::getFunc($name);
        if (!$func) {
            throw new FunctionError("Cloud hook `{$name}' not found.",
                                    404);
        }
        return call_user_func($func, $params, $meta);
    }
}
