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
     * Search keys for value in a hash array
     *
     * @param array $map  The hash array to search in
     * @param array $keys Keys in order
     * @retrun mixed
     */
    private function getVal($hash, $keys) {
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

    private function parsePlainBody($data) {
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
            // remove internal fields set by API
            forEach($data as $key) {
                if ($key[0] === "_") {
                    unset($data[$key]);
                }
            }
            $this->req["data"] = $data;
        }
    }

    /**
     * Parse raw request
     */
    private function parseRequest() {
        $url  = $_SERVER["REQUEST_URI"];
        $body = "";
        if (preg_match("/^\/(1|1\.1)\/(functions|call)(.*)/", $url) == 1) {
            // Note prior to php 5.6, input could be read only once. To not
            // interfere with 3rd party framework, we read it only within
            // LeanEngine internal endpoints.
            $body = file_get_contents("php://input");
        }
        $contentType = $this->getVal($_SERVER, array(
            "CONTENT_TYPE",
            "HTTP_CONTENT_TYPE"
        ));
        if (preg_match("/text\/plain/", $contentType)) {
            // the CORS request might be sent as POST request with text/plain
            // header, whence the app key info is attached in the body as
            // JSON.
            $this->parsePlainBody(json_decode($body, true));
        } else {
            $this->req["data"]  = json_decode($body, true);
            $this->req["X_LC_ID"] = $this->getVal($_SERVER, array(
                "HTTP_X_LC_ID",
                "HTTP_X_AVOSCLOUD_APPLICATION_ID",
                "HTTP_X_ULURU_APPLICATION_ID"
            ));
            $this->req["X_LC_KEY"] = $this->getVal($_SERVER, array(
                "HTTP_X_LC_KEY",
                "HTTP_X_AVOSCLOUD_APPLICATION_KEY",
                "HTTP_X_ULURU_APPLICATION_KEY"
            ));
            $this->req["X_LC_MASTER_KEY"] = $this->getVal($_SERVER, array(
                "HTTP_X_AVOSCLOUD_MASTER_KEY",
                "HTTP_X_ULURU_MASTER_KEY"
            ));
            $this->req["X_LC_SESSION"] = $this->getVal($_SERVER, array(
                "HTTP_X_LC_SESSION",
                "HTTP_X_AVOSCLOUD_SESSION_TOKEN",
                "HTTP_X_ULURU_SESSION_TOKEN"
            ));
            $this->req["X_LC_SIGN"] = $this->getVal($_SERVER, array(
                "HTTP_X_LC_SIGN",
                "HTTP_X_AVOSCLOUD_REQUEST_SIGN"
            ));
            $prod = $this->getVal($_SERVER, array(
                "HTTP_X_LC_PROD",
                "HTTP_X_AVOSCLOUD_APPLICATION_PRODUCTION",
                "HTTP_X_ULURU_APPLICATION_PRODUCTION"
            ));
            $this->req["useProd"] = true;
            if ($prod === 0 || $prod === false) {
                $this->req["useProd"] = false;
            }
            $this->req["ORIGIN"] = $_SERVER["HTTP_ORIGIN"];
        }
        $this->req["useMaster"] = false;
    }

    /**
     * Authenticate application request
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
     * Process request session
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
     * @param string $method Request method
     * @param string $url    Request URL
     * @param array  $data   JSON decoded body
     */
    private function dispatch($method, $url, $data) {
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
            $this->processSession();
            $user = LeanUser::getCurrentUser();
            if (strpos($matches[3], "/_ops/metadatas") === 0) {
                if ($this->req["useMaster"]) {
                    $this->renderJSON(Cloud::getKeys());
                } else {
                    $this->renderError("Unauthorized.", 401, 401);
                }
            }

            $data   = LeanClient::decode($data, null);
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
                    Cloud::runHook($params[0], $params[1],
                                   $obj, $user);
                    if ($params[1] == "beforeDelete") {
                        $this->renderJSON(array());
                    } else if (strpos($params[1], "after") === 0) {
                        $this->renderJSON(array("result" => "ok"));
                    } else {
                        $this->renderJSON($obj);
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
        $this->parseRequest();
        $this->dispatch($_SERVER["REQUEST_METHOD"],
                        $_SERVER["REQUEST_URI"],
                        $this->req["data"]);
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
        // TODO: parse laravel request
        $this->dispatch($request->method(),
                        $request->url(),
                        $request->json());
        return $next($request);
    }
}

