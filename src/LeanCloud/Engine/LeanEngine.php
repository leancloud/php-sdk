<?php

namespace LeanCloud\Engine;

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
     * Parsed env variables
     *
     * @var array
     */
    public static $ENV = array();

    /**
     * Retrieve value by multiple keys in array
     *
     * @param array $arr  The array to search in
     * @param array $keys Keys in order
     * @retrun mixed
     */
    private static function retrieveVal($arr, $keys) {
        $val = null;
        forEach($keys as $k) {
            if (isset($arr[$k])) {
                $val = $arr[$k];
            }
            if ($val) {
                return $val;
            }
        }
        return $val;
    }

    /**
     * Parse plain text body
     *
     * The CORS request might be sent as POST request with text/plain
     * header, whence the app key info is attached in the body as
     * JSON.
     *
     * @param string $body
     * @return array Decoded body array
     */
    private static function parsePlainBody($body) {
        $data = json_decode($body, true);
        if (!empty($data)) {
            self::$ENV["LC_ID"]         = isset($data["_ApplicationId"]) ?
                                          $data["_ApplicationId"] : null;
            self::$ENV["LC_KEY"]        = isset($data["_ApplicationKey"]) ?
                                          $data["_ApplicationKey"] : null;
            self::$ENV["LC_MASTER_KEY"] = isset($data["_MasterKey"]) ?
                                          $data["_MasterKey"] : null;
            self::$ENV["LC_SESSION"]    = isset($data["_SessionToken"]) ?
                                          $data["_SessionToken"] : null;
            self::$ENV["LC_SIGN"]       = null;
            self::$ENV["useProd"] = isset($data["_ApplicationProduction"]) ?
                                  (true && $data["_ApplicationProduction"]) :
                                  true;
            self::$ENV["useMaster"] = false;
            // remove internal fields set by API
            forEach($data as $key) {
                if ($key[0] === "_") {
                    unset($data[$key]);
                }
            }
        }
        return $data;
    }

    /**
     * Parse variant headers into standard names
     *
     * The headers shall be an associative array that contains raw
     * header keys. For example, in Laravel they are available at
     * `$request->header()`. It will default to `$_SERVER` if not
     * provided.
     *
     * @param array $headers
     */
    private static function parseHeaders($headers=null) {
        if (empty($headers)) {
            $headers = $_SERVER;
        }
        self::$ENV["ORIGIN"] = static::retrieveVal($headers, array(
            "HTTP_ORIGIN"
        ));
        self::$ENV["CONTENT_TYPE"] = static::retrieveVal($headers, array(
            "CONTENT_TYPE",
            "HTTP_CONTENT_TYPE"
        ));

        self::$ENV["LC_ID"] = static::retrieveVal($headers, array(
            "HTTP_X_LC_ID",
            "HTTP_X_AVOSCLOUD_APPLICATION_ID",
            "HTTP_X_ULURU_APPLICATION_ID"
        ));
        self::$ENV["LC_KEY"] = static::retrieveVal($headers, array(
            "HTTP_X_LC_KEY",
            "HTTP_X_AVOSCLOUD_APPLICATION_KEY",
            "HTTP_X_ULURU_APPLICATION_KEY"
        ));
        self::$ENV["LC_MASTER_KEY"] = static::retrieveVal($headers, array(
            "HTTP_X_AVOSCLOUD_MASTER_KEY",
            "HTTP_X_ULURU_MASTER_KEY"
        ));
        self::$ENV["LC_SESSION"] = static::retrieveVal($headers, array(
            "HTTP_X_LC_SESSION",
            "HTTP_X_AVOSCLOUD_SESSION_TOKEN",
            "HTTP_X_ULURU_SESSION_TOKEN"
        ));
        self::$ENV["LC_SIGN"] = static::retrieveVal($headers, array(
            "HTTP_X_LC_SIGN",
            "HTTP_X_AVOSCLOUD_REQUEST_SIGN"
        ));
        $prod = static::retrieveVal($headers, array(
            "HTTP_X_LC_PROD",
            "HTTP_X_AVOSCLOUD_APPLICATION_PRODUCTION",
            "HTTP_X_ULURU_APPLICATION_PRODUCTION"
        ));
        self::$ENV["useProd"] = true;
        if ($prod === 0 || $prod === false) {
            self::$ENV["useProd"] = false;
        }
        self::$ENV["useMaster"] = false;
    }

    /**
     * Authenticate request by app ID and key
     */
    private static function authRequest() {
        $appId = self::$ENV["LC_ID"];
        $sign  = self::$ENV["LC_SIGN"];
        if ($sign && LeanClient::verifySign($appId, $sign)) {
            if (strpos($sign, "master") !== false) {
                self::$ENV["useMaster"] = true;
            }
            return true;
        }

        $appKey = self::$ENV["LC_KEY"];
        if ($appKey && LeanClient::verifyKey($appId, $appKey)) {
            if (strpos($appKey, "master") !== false) {
                self::$ENV["useMaster"] = true;
            }
            return true;
        }

        $masterKey = self::$ENV["LC_MASTER_KEY"];
        $key = "{$masterKey}, master";
        if ($masterKey && LeanClient::verifyKey($appId, $key)) {
            self::$ENV["useMaster"] = true;
            return true;
        }

        static::renderError("Unauthorized", 401, 401);
    }

    /**
     * Set user session if sessionToken present
     *
     */
    private static function processSession() {
        static::authRequest();
        $token = self::$ENV["LC_SESSION"];
        if ($token) {
            LeanUser::become($token);
        }
    }

    /**
     * Dispatch request
     *
     * Following routes are processed and returned by LeanEngine:
     *
     * ```
     * OPTIONS {1,1.1}/{functions,call}.*
     * *       __engine/1/ping
     * *      {1,1.1}/{functions,call}/_ops/metadatas
     * *      {1,1.1}/{functions,call}/onVerified/{sms,email}
     * *      {1,1.1}/{functions,call}/BigQuery/onComplete
     * *      {1,1.1}/{functions,call}/{className}/{hookName}
     * *      {1,1.1}/{functions,call}/{funcName}
     * ```
     *
     * others may be added in future.
     *
     * @param string $method Request method
     * @param string $url    Request url
     * @param array  $body   Request body
     */
    private static function dispatch($method, $url, $body=null) {
        $path = parse_url($url, PHP_URL_PATH);
        $path = rtrim($path, "/");
        if (strpos($path, "/__engine/1/ping") === 0) {
            static::renderJSON(array(
                "runtime" => "PHP:TODO",
                "version" => LeanClient::VERSION
            ));
        }

        $pathParts = array(); // matched path components
        if (preg_match("/^\/(1|1\.1)\/(functions|call)(.*)/",
                       $path,
                       $pathParts) === 1) {
            $pathParts["version"]  = $pathParts[1]; // 1 or 1.1
            $pathParts["endpoint"] = $pathParts[2]; // functions or call
            $pathParts["extra"]    = $pathParts[3]; // extra part after endpoint
            $origin = self::$ENV["ORIGIN"];
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
            if (($method == "POST" || $method == "PUT") && empty($body)) {
                // Note input can be read only once prior to php 5.6.
                $body = file_get_contents("php://input");
            }
            if (preg_match("/text\/plain/", self::$ENV["CONTENT_TYPE"])) {
                $json = static::parsePlainBody($body);
            } else {
                $json = json_decode($body, true);
            }

            static::processSession();
            if (strpos($pathParts["extra"], "/_ops/metadatas") === 0) {
                if (self::$ENV["useMaster"]) {
                    static::renderJSON(Cloud::getKeys());
                } else {
                    static::renderError("Unauthorized.", 401, 401);
                }
            }

            // extract func params from path:
            // /1.1/call/{0}/{1}
            $funcParams = explode("/", ltrim($pathParts["extra"], "/"));
            if (count($funcParams) == 1) {
                // {1,1.1}/functions/{funcName}
                static::dispatchFunc($funcParams[0], $json,
                                     $pathParts["endpoint"] === "call");
            } else {
                if ($funcParams[0] == "onVerified") {
                    // {1,1.1}/functions/onVerified/sms
                    static::dispatchOnVerified($funcParams[1], $json);
                } else if ($funcParams[0] == "_User" &&
                           $funcParams[1] == "onLogin") {
                    // {1,1.1}/functions/_User/onLogin
                    static::dispatchOnLogin($json);
                } else if ($funcParams[0] == "BigQuery" ||
                           $funcParams[0] == "Insight") {
                    // {1,1.1}/functions/Insight/onComplete
                    static::dispatchOnInsight($json);
                } else if (count($funcParams) == 2) {
                    // {1,1.1}/functions/{className}/beforeSave
                    static::dispatchHook($funcParams[0], $funcParams[1], $json);
                }
            }
        }
    }

    /**
     * Dispatch function and render result
     *
     * @param string $funcName Function name
     * @param array  $body     JSON decoded body params
     * @param bool   $decodeObj
     */
    private static function dispatchFunc($funcName, $body, $decodeObj=false) {
        $params = $body;
        if ($decodeObj) {
            $params = LeanClient::decode($body, null);
        }
        try {
            $result = Cloud::run($funcName, $params,
                                 LeanUser::getCurrentUser());
        } catch (FunctionError $err) {
            static::renderError($err->getMessage(), $err->getCode());
        }
        if ($decodeObj) {
            // Encode object to full, type-annotated JSON
            $out = LeanClient::encode($result, "toFullJSON");
        } else {
            // Encode object to type-less literal JSON
            $out = LeanClient::encode($result, "toJSON");
        }
        static::renderJSON(array("result" => $out));
    }

    /**
     * Dispatch class hook and render result
     *
     * @param string $className
     * @param string $hookName
     * @param array  $body     JSON decoded body params
     */
    private static function dispatchHook($className, $hookName, $body) {
        $json              = $body["object"];
        $json["__type"]    = "Object";
        $json["className"] = $className;
        $obj = LeanClient::decode($json, null);

        // set hook marks to prevent infinite loop. For example if user
        // invokes `$obj->save` in an afterSave hook, API will not again
        // invoke afterSave if we set hook marks.
        forEach(array("__before", "__after", "__after_update") as $key) {
            if (isset($json[$key])) {
                $obj->set($key, $json[$key]);
            }
        }

        // in beforeUpdate hook, set updatedKeys so user can detect
        // which keys are updated in the update.
        if (isset($json["_updatedKeys"])) {
            $obj->updatedKeys = $json["_updatedKeys"];
        }

        try {
            $result = Cloud::runHook($className,
                                     $hookName,
                                     $obj,
                                     LeanUser::getCurrentUser());
        } catch (FunctionError $err) {
            static::renderError($err->getMessage(), $err->getCode());
        }
        if ($hookName == "beforeDelete") {
            static::renderJSON(array());
        } else if (strpos($hookName, "after") === 0) {
            static::renderJSON(array("result" => "ok"));
        } else {
            $outObj = $result;
            // Encode result object to type-less literal JSON
            static::renderJSON($outObj->toJSON());
        }
    }

    /**
     * Dispatch onVerified hook
     *
     * @param string $type Verify type: email or sms
     * @param array  $body JSON decoded body params
     */
    private static function dispatchOnVerified($type, $body) {
        $userObj = LeanClient::decode($body["object"], null);
        LeanUser::saveCurrentUser($userObj);
        try {
            Cloud::runOnVerified($type, $userObj);
        } catch (FunctionError $err) {
            static::renderError($err->getMessage(), $err->getCode());
        }
        static::renderJSON(array("result" => "ok"));
    }

    /**
     * Dispatch onLogin hook
     *
     * @param array $body JSON decoded body params
     */
    private static function dispatchOnLogin($body) {
        $userObj = LeanClient::decode($body["object"], null);
        try {
            Cloud::runOnLogin($userObj);
        } catch (FunctionError $err) {
            static::renderError($err->getMessage(), $err->getCode());
        }
        static::renderJSON(array("result" => "ok"));
    }

    /**
     * Dispatch onInsight hook
     *
     * @param array $body JSON decoded body params
     */
    private static function dispatchOnInsight($body) {
        try {
            Cloud::runOnInsight($body);
        } catch (FunctionError $err) {
            static::renderError($err->getMessage(), $err->getCode());
        }
        static::renderJSON(array("result" => "ok"));
    }

    /**
     * Render data as JSON output and end request
     *
     * @param array $data
     */
    private static function renderJSON($data) {
        header("Content-Type: application/json; charset=utf-8;");
        echo json_encode($data);
        exit;
    }

    /**
     * Render error and end request
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
     * Start engine and process request
     */
    public static function start() {
        static::parseHeaders($_SERVER);
        static::dispatch($_SERVER["REQUEST_METHOD"],
                         $_SERVER["REQUEST_URI"]);
    }

    /**
     * Handle Laravel request
     *
     * It exposes LeanEngine as a Laravel middleware, which can be
     * registered in Laravel application. E.g. in
     * `app/Http/Kernel.php`:
     *
     * ```php
     * class Kernel extends HttpKernel {
     *     protected $middleware = [
     *         ...,
     *         \LeanCloud\Engine\LeanEngine::class,
     *     ];
     * }
     * ```
     *
     * @param Request  $request Laravel request
     * @param Callable $next    Laravel Closure
     * @return mixed
     * @link http://laravel.com/docs/5.1/middleware
     */
    public function handle($request, $next) {
        static::parseHeaders($request->header());
        static::dispatch($request->method(),
                         $request->url(),
                         $request->getContent());
        return $next($request);
    }
}

