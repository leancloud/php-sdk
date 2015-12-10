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
        $url      = rtrim($url, "/");
        if (strpos($url, "/__engine/1/ping") === 0) {
            static::renderJSON(array(
                "runtime" => "PHP:TODO",
                "version" => LeanClient::VERSION
            ));
        }
        $matches = array();
        if (preg_match("/^\/(1|1\.1)\/(functions|call)(.*)/", $url, $matches) === 1) {
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
            $user = LeanUser::getCurrentUser();
            if (strpos($matches[3], "/_ops/metadatas") === 0) {
                if (self::$ENV["useMaster"]) {
                    static::renderJSON(Cloud::getKeys());
                } else {
                    static::renderError("Unauthorized.", 401, 401);
                }
            }

            $data   = LeanClient::decode($json, null);
            $params = explode("/", ltrim($matches[3], "/"));
            try {
                if (count($params) == 1) {
                    // {1,1.1}/functions/{funcName}
                    $result = Cloud::runFunc($params[0], $data, $user);
                    static::renderJSON(array("result" => $result));
                } else if ($params[0] == "onVerified") {
                    // {1,1.1}/functions/onVerified/sms
                    Cloud::runOnVerified($params[1], $user);
                    static::renderJSON(array("result" => "ok"));
                } else if ($params[0] == "_User" && $params[1] == "onLogin") {
                    // {1,1.1}/functions/_User/onLogin
                    Cloud::runOnLogin($data["object"]);
                    static::renderJSON(array("result" => "ok"));
                } else if ($params[0] == "BigQuery" || $params[0] == "Insight") {
                    // {1,1.1}/functions/BigQuery/onComplete
                    Cloud::runOnInsight($data);
                    static::renderJSON(array("result" => "ok"));
                } else if (count($params) == 2) {
                    // {1,1.1}/functions/{className}/beforeSave
                    $obj = Cloud::runHook($params[0], $params[1],
                                          $data["object"], $user);
                    if ($params[1] == "beforeDelete") {
                        static::renderJSON(array());
                    } else if (strpos($params[1], "after") === 0) {
                        static::renderJSON(array("result" => "ok"));
                    } else {
                        static::renderJSON($obj->toJSON());
                    }
                }
            } catch (FunctionError $err) {
                static::renderError($err->getMessage(), $err->getCode());
            }
        }
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

