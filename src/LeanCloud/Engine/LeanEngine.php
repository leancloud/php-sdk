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
     * Request info
     *
     * @var array
     */
    private $req = array();

    /**
     * Retrieve value by multiple keys in array
     *
     * @param array $arr  The array to search in
     * @param array $keys Keys in order
     * @retrun mixed
     */
    private function retrieveVal($arr, $keys) {
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
     * @return array $data
     */
    private function parsePlainBody($body) {
        $data = json_decode($body, true);
        if (!empty($data)) {
            $this->req["X_LC_ID"]         = isset($data["_ApplicationId"]) ?
                                          $data["_ApplicationId"] : null;
            $this->req["X_LC_KEY"]        = isset($data["_ApplicationKey"]) ?
                                          $data["_ApplicationKey"] : null;
            $this->req["X_LC_MASTER_KEY"] = isset($data["_MasterKey"]) ?
                                          $data["_MasterKey"] : null;
            $this->req["X_LC_SESSION"]    = isset($data["_SessionToken"]) ?
                                          $data["_SessionToken"] : null;
            $this->req["X_LC_SIGN"]       = null;
            $this->req["useProd"] = isset($data["_ApplicationProduction"]) ?
                                  (true && $data["_ApplicationProduction"]) :
                                  true;
            $this->req["useMaster"] = false;
            // remove internal fields set by API
            forEach($data as $key) {
                if ($key[0] === "_") {
                    unset($data[$key]);
                }
            }
            $this->req["data"] = $data;
        }
        return $data;
    }

    /**
     * Parse variant headers into standard names
     */
    private function parseHeaders($headers=null) {
        if (empty($headers)) {
            $headers = $_SERVER;
        }
        $this->req["ORIGIN"] = $this->retrieveVal($headers, array(
            "HTTP_ORIGIN"
        ));
        $this->req["CONTENT_TYPE"] = $this->retrieveVal($headers, array(
            "CONTENT_TYPE",
            "HTTP_CONTENT_TYPE"
        ));

        $this->req["X_LC_ID"] = $this->retrieveVal($headers, array(
            "HTTP_X_LC_ID",
            "HTTP_X_AVOSCLOUD_APPLICATION_ID",
            "HTTP_X_ULURU_APPLICATION_ID"
        ));
        $this->req["X_LC_KEY"] = $this->retrieveVal($headers, array(
            "HTTP_X_LC_KEY",
            "HTTP_X_AVOSCLOUD_APPLICATION_KEY",
            "HTTP_X_ULURU_APPLICATION_KEY"
        ));
        $this->req["X_LC_MASTER_KEY"] = $this->retrieveVal($headers, array(
            "HTTP_X_AVOSCLOUD_MASTER_KEY",
            "HTTP_X_ULURU_MASTER_KEY"
        ));
        $this->req["X_LC_SESSION"] = $this->retrieveVal($headers, array(
            "HTTP_X_LC_SESSION",
            "HTTP_X_AVOSCLOUD_SESSION_TOKEN",
            "HTTP_X_ULURU_SESSION_TOKEN"
        ));
        $this->req["X_LC_SIGN"] = $this->retrieveVal($headers, array(
            "HTTP_X_LC_SIGN",
            "HTTP_X_AVOSCLOUD_REQUEST_SIGN"
        ));
        $prod = $this->retrieveVal($headers, array(
            "HTTP_X_LC_PROD",
            "HTTP_X_AVOSCLOUD_APPLICATION_PRODUCTION",
            "HTTP_X_ULURU_APPLICATION_PRODUCTION"
        ));
        $this->req["useProd"] = true;
        if ($prod === 0 || $prod === false) {
            $this->req["useProd"] = false;
        }
        $this->req["useMaster"] = false;
    }

    /**
     * Authenticate request by app ID and key
     */
    private function authRequest() {
        $appId = $this->req["X_LC_ID"];
        $sign  = $this->req["X_LC_SIGN"];
        if ($sign && LeanClient::verifySign($appId, $sign)) {
            if (strpos($sign, "master") !== false) {
                $this->req["useMaster"] = true;
            }
            return true;
        }

        $appKey = $this->req["X_LC_KEY"];
        if ($appKey && LeanClient::verifyKey($appId, $appKey)) {
            if (strpos($appKey, "master") !== false) {
                $this->req["useMaster"] = true;
            }
            return true;
        }

        $masterKey = $this->req["X_LC_MASTER_KEY"];
        $key = "{$masterKey}, master";
        if ($masterKey && LeanClient::verifyKey($appId, $key)) {
            $this->req["useMaster"] = true;
            return true;
        }

        $this->renderError("Unauthorized", 401, 401);
    }

    /**
     * Set user session if sessionToken present
     */
    private function processSession() {
        $this->authRequest();
        $token = $this->req["X_LC_SESSION"];
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
     * @param string $url    Request URL
     * @param array  $body   Request body
     */
    private function dispatch($method, $url, $body=null) {
        $url      = rtrim($url, "/");
        if (strpos($url, "/__engine/1/ping") === 0) {
            $this->renderJSON(array(
                "runtime" => "PHP:TODO",
                "version" => LeanClient::VERSION
            ));
        }
        $matches = array();
        if (preg_match("/^\/(1|1\.1)\/(functions|call)(.*)/", $url, $matches) == 1) {
            $origin = $this->req["ORIGIN"];
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
            if (preg_match("/text\/plain/", $this->req["CONTENT_TYPE"])) {
                $json = $this->parsePlainBody($body);
            } else {
                $json = json_decode($body, true);
            }

            $this->processSession();
            $user = LeanUser::getCurrentUser();
            if (strpos($matches[3], "/_ops/metadatas") === 0) {
                if ($this->req["useMaster"]) {
                    $this->renderJSON(Cloud::getKeys());
                } else {
                    $this->renderError("Unauthorized.", 401, 401);
                }
            }

            $data   = LeanClient::decode($json, null);
            $params = explode("/", ltrim($matches[3], "/"));
            try {
                if (count($params) == 1) {
                    // {1,1.1}/functions/{funcName}
                    $result = Cloud::runFunc($params[0], $data, $user);
                    $this->renderJSON(array("result" => $result));
                } else if ($params[0] == "onVerified") {
                    // {1,1.1}/functions/onVerified/sms
                    Cloud::runOnVerified($params[1], $user);
                    $this->renderJSON(array("result" => "ok"));
                } else if ($params[0] == "_User" && $params[1] == "onLogin") {
                    // {1,1.1}/functions/_User/onLogin
                    Cloud::runOnLogin($data["object"]);
                    $this->renderJSON(array("result" => "ok"));
                } else if ($params[0] == "BigQuery" || $params[0] == "Insight") {
                    // {1,1.1}/functions/BigQuery/onComplete
                    Cloud::runOnInsight($data);
                    $this->renderJSON(array("result" => "ok"));
                } else if (count($params) == 2) {
                    // {1,1.1}/functions/{className}/beforeSave
                    $obj = $data["object"];
                    $obj2 = Cloud::runHook($params[0], $params[1],
                                   $obj, $user);
                    if ($params[1] == "beforeDelete") {
                        $this->renderJSON(array());
                    } else if (strpos($params[1], "after") === 0) {
                        $this->renderJSON(array("result" => "ok"));
                    } else {
                        $this->renderJSON($obj2);
                    }
                }
            } catch (FunctionError $err) {
                $this->renderError($err->getMessage(), $err->getCode());
            }
        }
    }

    /**
     * Render data as JSON output and end request
     *
     * @param array $data
     */
    private function renderJSON($data) {
        header("Content-Type: application/json; charset=utf-8;");
        echo json_encode(LeanClient::encode($data));
        exit;
    }

    /**
     * Render error and end request
     *
     * @param string $message Error message
     * @param string $code    Error code
     * @param string $status  Http response status code
     */
    private function renderError($message, $code=1, $status=400) {
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
        $this->parseHeaders($_SERVER);
        $this->dispatch($_SERVER["REQUEST_METHOD"],
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
        $this->parseHeaders($request->header());
        $this->dispatch($request->method(),
                        $request->url(),
                        $request->getContent());
        return $next($request);
    }
}

