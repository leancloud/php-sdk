<?php

use LeanCloud\LeanClient;
use LeanCloud\LeanUser;

class LeanEngine {

    /**
     * Allowed headers in a cross origin request (CORS)
     *
     * @var array
     */
    private static $allowedHeaders = array(
        'X-LC-Id', 'X-LC-Key', 'X-LC-Session', 'X-LC-Sign', 'X-LC-Prod',
        'X-Uluru-Application-Key',
        'X-Uluru-Application-Id',
        'X-Uluru-Application-Production',
        'X-Uluru-Client-Version',
        'X-Uluru-Session-Token',
        'X-AVOSCloud-Application-Key',
        'X-AVOSCloud-Application-Id',
        'X-AVOSCloud-Application-Production',
        'X-AVOSCloud-Client-Version',
        'X-AVOSCloud-Super-Key',
        'X-AVOSCloud-Session-Token',
        'X-AVOSCloud-Request-sign',
        'X-Requested-With',
        'Content-Type'
    );

    /**
     * Render data as JSON output and end request
     *
     * @param array $data
     */
    private static function renderJSON($data) {
        header("Content-Type: application/json; charset=utf-8;");
        echo LeanClient::encode($data);
        exit;
    }

    /**
     * Render error response and end request
     *
     * @param string $message Error message
     * @param string $code    Error code
     * @param string $status  Http response status code
     */
    private static function renderError($message, $code=1, $status=400) {
        http_response_code($status);
        header("Content-Type: application/json; charset=utf-8;");
        echo json_encode(array(
            "code"  => $code,
            "error" => $message
        ));
        exit;
    }

    /**
     * Authenticate application request
     *
     * @return bool
     */
    private static function authRequest() {
        // The client address is available from:
        // $_SERVER["HTTP_X_Real_Ip"];
        // $_SERVER["HTTP_X_Forwaded_For"];
        $appId = $_SERVER["HTTP_X_LC_Id"];
        $appId = $appId ? $appId : $_SERVER["HTTP_X_Avoscloud_Application_Id"];
        $appId = $appId ? $appId : $_SERVER["HTTP_X_Uluru_Application_Id"];
        if (!$appId) {
            self::renderError("Unauthorized", 401, 401);
        }
        $sign = $_SERVER["HTTP_X_LC_Sign"] ?
              $_SERVER["HTTP_X_LC_Sign"] :
              $_SERVER["HTTP_X_Avoscloud_Request_Sign"];
        if ($sign && LeanClient::verifySign($appId, $sign)) {
            return true;
        }

        $appKey = $_SERVER["HTTP_X_LC_Key"];
        $appKey = $appKey ? $appKey : $_SERVER["HTTP_X_Avoscloud_Application_Key"];
        $appKey = $appKey ? $appKey : $_SERVER["HTTP_X_Uluru_Application_Key"];
        if ($appKey && LeanClient::verifyKey($appId, $appKey)) {
            return true;
        }

        $masterKey = $_SERVER["HTTP_X_Avoscloud_Master_Key"] ?
                   $_SERVER["HTTP_X_Avoscloud_Master_Key"] :
                   $_SERVER["HTTP_X_Uluru_Master_Key"];
        if ($masterKey && LeanClient::verifyMasterKey($appId, $masterKey)) {
            return true;
        }
        return false;
    }

    /**
     * Process request session
     */
    private static function processSession() {
        if (!self::authRequest()) {
            self::renderError("Unauthorized", 401, 401);
        }
        $token = $_SERVER["HTTP_X_LC_Session"] ?
               $_SERVER["HTTP_X_LC_Session"] :
               $_SERVER["HTTP_X_Avoscloud-Session-Token"];
        $token = $token ? $token : $_SERVER["HTTP_X_Uluru_Session_Token"];
        LeanUser::become($token);
    }

    /**
     * Dispatch request
     */
    public static function dispatch() {
        $url      = rtrim($_SERVER["REQUEST_URI"], "/");
        if ($url == "/__engine/1/ping") {
            self::renderJSON(array(
                "runtime" => "PHP:TODO",
                "version" => LeanClient::VERSION;
            ));
        }
        self::processSession();
        $user    = LeanUser::getCurrentUser();
        $matches = array();
        if (preg_match("/\/(1|1\.1)\/(functions|call)(.*)/", $url, $matches) == 1) {
            $origin = $_SERVER["HTTP_Origin"];
            header("Access-Control-Allow-Origin: " . ($origin ? $origin : "*"));
            if ($method == "OPTIONS") {
                header("Access-Control-Max-Age: 86400");
                header("Access-Control-Allow-Methods: ".
                       "PUT, GET, POST, DELETE, OPTIONS");
                header("Access-Control-Allow-Headers: " .
                       implode(", ", self::$allowedHeaders));
                header("Content-Length: 0");
                exit;
            }

            // Get request body from input stream. Note php framework
            // might read and emptied input.
            $body = file_get_contents("php://input");
            $data = LeanClient::decode($body, null);  // Request data
            $params = explode("/", ltrim($matches[3], "/"));
            if ($matches[3] == "/_ops/metadatas") {
                $result = self::renderJSON(array_keys(Cloud::getKeys()));
            }
            try {
                if (count($params) == 1) {
                    $result = self::runFunc($params[0], $data, $user);
                } else if ($params[0] == "onVerified") {
                    // onVerified hook has endpoint: functions/onVerified/sms
                    $result = self::runOnVerified($params[1], $user);
                } else if ($params[0] == "_User" && $params[1] == "onLogin") {
                    $result = self::runOnLogin($data["object"]);
                } else if ($params[0] == "BigQuery" || $params[0] == "Insight") {
                    $result = self::runOnInsight($data);
                } else if (count($params) == 2) {
                    $result = self::runHook($params[0], $params[1],
                                            $data["object"], $user);
                } else {
                    self::renderError("Route not found.", 1, 404);
                }
            } catch (FunctionError $err) {
                self::renderError($err->getMessage(), $err->getCode());
            }
            self::renderJSON(array("result" => $result));
        }
    }

    /**
     * Run cloud function
     *
     * Example:
     *
     * ```php
     * LeanEngine::runFunc("sayHello", array("name" => "alice"), $user);
     * // sayHello(array("name" => "alice"), $user);
     * ```
     *
     * @param string   $funcName Name of defined function
     * @param array    $data     Array of parameters passed to function
     * @param LeanUser $user     Request user
     * @return mixed
     * @throws FunctionError
     */
    public static function runFunc($funcName, $params, $user) {
        $func = Cloud::getFunc($funcName);
        if (!$func) {
            throw new FunctionError("Cloud function not found.", 404);
        }
        return call_user_func($func, $params, $user);
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
     * @param LeanUser   $user   Request user
     * @return mixed
     * @throws FunctionError
     */
    public static function runHook($className, $hookName, $object,
                                   $user=null) {
        $name = Cloud::getHookName($className, $hookName);
        $func = Cloud::getFunc($name);
        if (!$func) {
            throw new FunctionError("Cloud hook `{$name}' not found.",
                                    404);
        }
        return call_user_func($func, $object, $user);
    }

    /**
     * Run hook when a user logs in
     *
     * @param LeanUser $user The user that tries to login
     * @throws FunctionError
     */
    public static function runOnLogin($user) {
        return self::runHook("_User", "onLogin", $user);
    }

    /**
     * Run hook when user verified by Email or SMS
     *
     * @param string   $type Either "sms" or "email", case-sensitive
     * @param LeanUser $user The verifying user
     * @throws FunctionError
     */
    public static function runOnVerified($type, $user) {
        $name = "__on_verified_{$type}";
        $func = Cloud::getFunc($name);
        if (!$func) {
            throw new FunctionError("Cloud hook `{$name}' not found.",
                                    404);
        }
        return call_user_func($func, $user);
    }

    /**
     * Run hook when BigQuery complete
     *
     * @see self::runOnInsight
     */
    public static function runOnBigQuery($params) {
        return self::runOnInsight($params);
    }

    /**
     * Run hook on big query complete
     *
     * @param array $params Job id and 
     * @return mixed
     * @throws FunctionError
     */
    public static function runOnInsight($params) {
        $name = "__on_complete_bigquery_job";
        $func = Cloud::getFunc($name);
        if (!$func) {
            throw new FunctionError("Cloud hook `{$name}' not found.",
                                    404);
        }
        return call_user_func($func, $params);
    }

}

