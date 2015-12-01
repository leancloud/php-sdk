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
     * Search keys for value in a hash array
     *
     * @param array $map  The hash array to search in
     * @param array $keys Keys in order
     * @retrun mixed
     */
    private static function getVal($hash, $keys) {
        $val = null;
        forEach($keys as $k) {
            if (isset($hash[$k])) {
                $val = $hash[$k];
            }
            if ($val) {
                return $val;
            }
        }
        return $val;
    }

    /**
     * Authenticate application request
     *
     * @return bool
     */
    private static function authRequest() {
        $appId = self::getVal($_SERVER, array(
            "HTTP_X_LC_ID",
            "HTTP_X_AVOSCLOUD_APPLICATION_ID",
            "HTTP_X_ULURU_APPLICATION_ID"
        ));
        if (!$appId) {
            self::renderError("Application ID not found", 401, 401);
        }
        $sign = self::getVal($_SERVER, array(
            "HTTP_X_LC_SIGN",
            "HTTP_X_AVOSCLOUD_REQUEST_SIGN"
        ));
        if ($sign && LeanClient::verifySign($appId, $sign)) {
            return true;
        }

        $appKey = self::getVal($_SERVER, array(
            "HTTP_X_LC_KEY",
            "HTTP_X_AVOSCLOUD_APPLICATION_KEY",
            "HTTP_X_ULURU_APPLICATION_KEY"
        ));
        if ($appKey && LeanClient::verifyKey($appId, $appKey)) {
            return true;
        }

        $masterKey = self::getVal($_SERVER, array(
            "HTTP_X_AVOSCLOUD_MASTER_KEY",
            "HTTP_X_ULURU_MASTER_KEY"
        ));
        $key = "{$masterKey}, master";
        if ($masterKey && LeanClient::verifyKey($appId, $key)) {
            return true;
        }

        self::renderError("Unauthorized", 401, 401);
    }

    /**
     * Process request session
     */
    private static function processSession() {
        self::authRequest();
        $token = self::getVal($_SERVER, array(
            "HTTP_X_LC_SESSION",
            "HTTP_X_AVOSCLOUD_SESSION_TOKEN",
            "HTTP_X_ULURU_SESSION_TOKEN"
        ));
        if ($token) {
            LeanUser::become($token);
        }
    }

    /**
     * Dispatch request
     *
     */
    private static function dispatch() {
        $url      = rtrim($_SERVER["REQUEST_URI"], "/");
        if ($url == "/__engine/1/ping") {
            self::renderJSON(array(
                "runtime" => "PHP:TODO",
                "version" => LeanClient::VERSION
            ));
        }
        self::processSession();
        $matches = array();
        if (preg_match("/\/(1|1\.1)\/(functions|call)(.*)/", $url, $matches) == 1) {
            $method = $_SERVER["REQUEST_METHOD"];
            $origin = $_SERVER["HTTP_ORIGIN"];
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

            if ($matches[3] == "/_ops/metadatas") {
                // only master key can do this
                self::renderJSON(Cloud::getKeys());
            }
            // Get request body from input stream. Note php framework
            // might read and emptied input.
            $body   = file_get_contents("php://input");
            $data   = LeanClient::decode(json_decode($body, true), null);
            $params = explode("/", ltrim($matches[3], "/"));
            $user   = LeanUser::getCurrentUser();
            try {
                if (count($params) == 1) {
                    // {1,1.1}/functions/{funcName}
                    $result = Cloud::runFunc($params[0], $data, $user);
                } else if ($params[0] == "onVerified") {
                    // {1,1.1}/functions/onVerified/sms
                    Cloud::runOnVerified($params[1], $user);
                    $result = "ok";
                } else if ($params[0] == "_User" && $params[1] == "onLogin") {
                    // {1,1.1}/functions/_User/onLogin
                    Cloud::runOnLogin($data["object"]);
                    $result = "ok";
                } else if ($params[0] == "BigQuery" || $params[0] == "Insight") {
                    // {1,1.1}/functions/BigQuery/onComplete
                    Cloud::runOnInsight($data);
                    $result = "ok";
                } else if (count($params) == 2) {
                    // {1,1.1}/functions/{className}/beforeSave
                    $obj = $data["object"];
                    Cloud::runHook($params[0], $params[1],
                                   $obj, $user);
                    if ($params[1] == "beforeDelete") {
                        $result = "";
                    } else if (strpos($params[1], "after") === 0) {
                        $result = "ok";
                    } else {
                        $result = $obj;
                    }
                } else {
                    self::renderError("Route not found.", 1, 404);
                }
                self::renderJSON(array("result" => $result));
            } catch (FunctionError $err) {
                self::renderError($err->getMessage(), $err->getCode());
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
        echo json_encode(LeanClient::encode($data));
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
     * Start engine and process request
     */
    public function start() {
        self::dispatch();
    }

    /**
     * Function to expose LeanEngine as Laraval middleware
     *
     * @param Request  $request Laravel request
     * @param Callable $next    Laravel Closure
     * @return mixed
     */
    public function handle($request, $next) {
        self::dispatch();
        return $next($request);
    }
}

