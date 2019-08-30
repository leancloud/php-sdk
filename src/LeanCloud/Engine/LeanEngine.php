<?php

namespace LeanCloud\Engine;

use LeanCloud\Client;
use LeanCloud\User;
use LeanCloud\CloudException;

class LeanEngine {

    /**
     * Allowed headers in a cross origin request (CORS)
     *
     * @var array
     */
    private static $allowedHeaders = array(
        'X-LC-Id', 'X-LC-Key', 'X-LC-Session', 'X-LC-Sign', 'X-LC-Prod',
        'X-LC-UA',
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
     * Redirect to https or not
     * @var bool
     */
    protected static $useHttpsRedirect = false;

    /**
     * Parsed LeanEngine env variables
     *
     * @var array
     */
    protected $env = array();

    /**
     * Get header value
     *
     * @param  string $key
     * @return string|null
     */
    protected function getHeaderLine($key) {
        if (isset($_SERVER[$key])) {
            return $_SERVER[$key];
        }
        return null;
    }

    /**
     * Set header
     *
     * @param string $key Header key
     * @param string $val Header val
     * @return self
     */
    protected function withHeader($key, $val) {
        header("{$key}: {$val}");
        return $this;
    }

    /**
     * Send response with body and status code
     *
     * @param string $body   Response body
     * @param string $status Response status
     */
    protected function send($body, $status) {
        http_response_code($status);
        echo $body;
        exit;
    }

    /**
     * Redirect to URL
     *
     * @param string $url
     */
    protected function redirect($url) {
        http_response_code(302);
        header("Location: {$url}");
        exit;
    }

    /**
     * Get request body string
     *
     * It reads body from `php://input`, which has cavets that it could be
     * read only once prior to php 5.6. Thus it is recommended to override
     * this method in subclass.
     *
     * @return string
     */
    protected function getBody() {
        $body = file_get_contents("php://input");
        return $body;
    }

    /**
     * Render data as JSON output and end request
     *
     * @param array $data
     */
    private function renderJSON($data=null, $status=200) {
        $out = is_null($data) ? "" : json_encode($data);
        $this->withHeader("Content-Type",
                          "application/json; charset=utf-8;")
            ->send($out, $status);
    }

    /**
     * Render error and end request
     *
     * @param string $message Error message
     * @param string $code    Error code
     * @param string $status  Http response status code
     */
    private function renderError($message, $code=1, $status=400) {
        $data = json_encode(array(
            "code"  => $code,
            "error" => $message
        ));
        $this->withHeader("Content-Type", "application; charset=utf-8;")
            ->send($data, $status);
    }

    /**
     * Retrieve header value with multiple version of keys
     *
     * @param array $keys Keys in order
     * @return mixed
     */
    private function retrieveHeader($keys) {
        $val = null;
        forEach($keys as $k) {
            $val = $this->getHeaderLine($k);
            if (!empty($val)) {
                return $val;
            }
        }
        return $val;
    }

    /**
     * Extract variant headers into env
     *
     * PHP prepends `HTTP_` to user-defined headers, so `X-MY-VAR`
     * would be populated as `HTTP_X_MY_VAR`. But 3rd party frameworks
     * (e.g. Laravel) may overwrite the behavior, and populate it as
     * cleaner `X_MY_VAR`. So we try to retrieve header value from both
     * versions.
     *
     */
    private function parseHeaders() {
        $this->env["ORIGIN"] = $this->retrieveHeader(array(
            "ORIGIN",
            "HTTP_ORIGIN"
        ));
        $this->env["CONTENT_TYPE"] = $this->retrieveHeader(array(
            "CONTENT_TYPE",
            "HTTP_CONTENT_TYPE"
        ));
        $this->env["REMOTE_ADDR"] = $this->retrieveHeader(array(
            "X_REAL_IP",
            "HTTP_X_REAL_IP",
            "X_FORWARDED_FOR",
            "HTTP_X_FORWARDED_FOR",
            "REMOTE_ADDR"
        ));

        $this->env["LC_ID"] = $this->retrieveHeader(array(
            "X_LC_ID",
            "HTTP_X_LC_ID",
            "X_AVOSCLOUD_APPLICATION_ID",
            "HTTP_X_AVOSCLOUD_APPLICATION_ID",
            "X_ULURU_APPLICATION_ID",
            "HTTP_X_ULURU_APPLICATION_ID"
        ));
        $this->env["LC_KEY"] = $this->retrieveHeader(array(
            "X_LC_KEY",
            "HTTP_X_LC_KEY",
            "X_AVOSCLOUD_APPLICATION_KEY",
            "HTTP_X_AVOSCLOUD_APPLICATION_KEY",
            "X_ULURU_APPLICATION_KEY",
            "HTTP_X_ULURU_APPLICATION_KEY"
        ));
        $this->env["LC_MASTER_KEY"] = $this->retrieveHeader(array(
            "X_AVOSCLOUD_MASTER_KEY",
            "HTTP_X_AVOSCLOUD_MASTER_KEY",
            "X_ULURU_MASTER_KEY",
            "HTTP_X_ULURU_MASTER_KEY"
        ));
        $this->env["LC_SESSION"] = $this->retrieveHeader(array(
            "X_LC_SESSION",
            "HTTP_X_LC_SESSION",
            "X_AVOSCLOUD_SESSION_TOKEN",
            "HTTP_X_AVOSCLOUD_SESSION_TOKEN",
            "X_ULURU_SESSION_TOKEN",
            "HTTP_X_ULURU_SESSION_TOKEN"
        ));
        $this->env["LC_SIGN"] = $this->retrieveHeader(array(
            "X_LC_SIGN",
            "HTTP_X_LC_SIGN",
            "X_AVOSCLOUD_REQUEST_SIGN",
            "HTTP_X_AVOSCLOUD_REQUEST_SIGN"
        ));
        $prod = $this->retrieveHeader(array(
            "X_LC_PROD",
            "HTTP_X_LC_PROD",
            "X_AVOSCLOUD_APPLICATION_PRODUCTION",
            "HTTP_X_AVOSCLOUD_APPLICATION_PRODUCTION",
            "X_ULURU_APPLICATION_PRODUCTION",
            "HTTP_X_ULURU_APPLICATION_PRODUCTION"
        ));
        $this->env["useProd"] = true;
        if ($prod === 0 || $prod === false) {
            $this->env["useProd"] = false;
        }
        $this->env["useMaster"] = false;
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
    private function parsePlainBody($body) {
        $data = json_decode($body, true);
        if (!empty($data)) {
            $this->env["LC_ID"]         = isset($data["_ApplicationId"]) ?
                                          $data["_ApplicationId"] : null;
            $this->env["LC_KEY"]        = isset($data["_ApplicationKey"]) ?
                                          $data["_ApplicationKey"] : null;
            $this->env["LC_MASTER_KEY"] = isset($data["_MasterKey"]) ?
                                          $data["_MasterKey"] : null;
            $this->env["LC_SESSION"]    = isset($data["_SessionToken"]) ?
                                          $data["_SessionToken"] : null;
            $this->env["LC_SIGN"]       = null;
            $this->env["useProd"] = isset($data["_ApplicationProduction"]) ?
                                  (true && $data["_ApplicationProduction"]) :
                                  true;
            $this->env["useMaster"] = false;
            // remove internal fields set by API
            // note we need to preserve `__type` field for object decoding
            // see #61
            forEach($data as $key) {
                if ($key[0] === "_" && $key[1] !== "_") {
                    unset($data[$key]);
                }
            }
        }
        return $data;
    }

    /**
     * Authenticate request by app ID and key
     */
    private function authRequest() {
        $appId = $this->env["LC_ID"];
        $sign  = $this->env["LC_SIGN"];
        if ($sign && Client::verifySign($appId, $sign)) {
            if (strpos($sign, "master") !== false) {
                $this->env["useMaster"] = true;
            }
            return true;
        }

        $appKey = $this->env["LC_KEY"];
        if ($appKey && Client::verifyKey($appId, $appKey)) {
            if (strpos($appKey, "master") !== false) {
                $this->env["useMaster"] = true;
            }
            return true;
        }

        $masterKey = $this->env["LC_MASTER_KEY"];
        $key = "{$masterKey}, master";
        if ($masterKey && Client::verifyKey($appId, $key)) {
            $this->env["useMaster"] = true;
            return true;
        }

        $this->renderError("Unauthorized", 401, 401);
    }

    private function verifyHookSign($hookName, $sign){
        if (Client::verifyHookSign($hookName, $sign)) return true;
        error_log("Invalid hook sign for {$hookName}");
        $this->renderError("Unauthorized", 142, 401);
    }

    /**
     * Set user session if sessionToken present
     */
    private function processSession() {
        $token = $this->env["LC_SESSION"];
        if ($token) {
            User::become($token);
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
     */
    private function __dispatch($method, $url) {
        if (static::$useHttpsRedirect) {
            $this->httpsRedirect();
        }
        $path = parse_url($url, PHP_URL_PATH);
        $path = rtrim($path, "/");
        if (strpos($path, "/__engine/1/ping") === 0) {
            $this->renderJSON(array(
                "runtime" => "php-" . phpversion(),
                "version" => Client::VERSION
            ));
        }

        $this->parseHeaders();

        $pathParts = array(); // matched path components
        if (preg_match("/^\/(1|1\.1)\/(functions|call)(.*)/",
                       $path,
                       $pathParts) === 1) {
            $pathParts["version"]  = $pathParts[1]; // 1 or 1.1
            $pathParts["endpoint"] = $pathParts[2]; // functions or call
            $pathParts["extra"]    = $pathParts[3]; // extra part after endpoint
            $origin = $this->env["ORIGIN"];
            $this->withHeader("Access-Control-Allow-Origin",
                              $origin ? $origin : "*");
            if ($method == "OPTIONS") {
                $this->withHeader("Access-Control-Max-Age", 86400)
                    ->withHeader("Access-Control-Allow-Methods",
                                 "PUT, GET, POST, DELETE, OPTIONS")
                    ->withHeader("Access-Control-Allow-Headers",
                                 implode(", ", self::$allowedHeaders))
                    ->withHeader("Content-Length", 0)
                    ->renderJSON();
            }

            $body = $this->getBody();
            if (preg_match("/text\/plain/", $this->env["CONTENT_TYPE"])) {
                // To work around with CORS restriction, some requests are
                // submit as text/palin body, where headers are attached
                // in the body.
                $json = $this->parsePlainBody($body);
            } else {
                $json = json_decode($body, true);
            }

            $this->authRequest();
            $this->processSession();
            if (strpos($pathParts["extra"], "/_ops/metadatas") === 0) {
                if ($this->env["useMaster"]) {
                    $this->renderJSON(array("result" => Cloud::getKeys()));
                } else {
                    $this->renderError("Unauthorized.", 401, 401);
                }
            }

            // extract func params from path:
            // /1.1/call/{0}/{1}
            $funcParams = explode("/", ltrim($pathParts["extra"], "/"));
            if (count($funcParams) == 1) {
                // {1,1.1}/functions/{funcName}
                $this->dispatchFunc($funcParams[0], $json,
                                    $pathParts["endpoint"] === "call");
            } else {
                if ($funcParams[0] == "onVerified") {
                    // {1,1.1}/functions/onVerified/sms
                    $this->dispatchOnVerified($funcParams[1], $json);
                } else if ($funcParams[0] == "_User" &&
                           $funcParams[1] == "onLogin") {
                    // {1,1.1}/functions/_User/onLogin
                    $this->dispatchOnLogin($json);
                } else if ($funcParams[0] == "BigQuery" ||
                           $funcParams[0] == "Insight") {
                    // {1,1.1}/functions/Insight/onComplete
                    $this->dispatchOnInsight($json);
                } else if (count($funcParams) == 2) {
                    // {1,1.1}/functions/{className}/beforeSave
                    $this->dispatchHook($funcParams[0], $funcParams[1], $json);
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
    private function dispatchFunc($funcName, $body, $decodeObj=false) {
        // verify hook sign for RTM hooks
        if (in_array($funcName, array(
            '_messageReceived', '_receiversOffline', '_messageSent',
            '_conversationStart', '_conversationStarted',
            '_conversationAdd', '_conversationRemove', '_conversationUpdate'
        ))) {
            static::verifyHookSign($funcName, $body["__sign"]);
        }

        $params = $body;
        if ($decodeObj) {
            $params = Client::decode($body, null);
        }

        $meta["remoteAddress"] = $this->env["REMOTE_ADDR"];
        $result = Cloud::run($funcName,
                             $params,
                             User::getCurrentUser(),
                             $meta);
        if ($decodeObj) {
            // Encode object to full, type-annotated JSON
            $out = Client::encode($result, "toFullJSON");
        } else {
            // Encode object to type-less literal JSON
            $out = Client::encode($result, "toJSON");
        }
        $this->renderJSON(array("result" => $out));
    }

    /**
     * Dispatch class hook and render result
     *
     * @param string $className
     * @param string $hookName
     * @param array  $body     JSON decoded body params
     */
    private function dispatchHook($className, $hookName, $body) {
        $verified = false;
        if (strpos($hookName, "before") === 0) {
            $this->verifyHookSign("__before_for_{$className}",
                                   $body["object"]["__before"]);
        } else {
            $this->verifyHookSign("__after_for_{$className}",
                                   $body["object"]["__after"]);
        }

        $json              = $body["object"];
        $json["__type"]    = "Object";
        $json["className"] = $className;
        $obj = Client::decode($json, null);

        // set hook marks to prevent infinite loop. For example if user
        // invokes `$obj->save` in an afterSave hook, API will not again
        // invoke afterSave if we set hook marks.
        if (strpos($hookName, "before") === 0) {
            if (isset($json["__before"])) {
                $obj->set("__before", $json["__before"]);
            } else {
                $obj->disableBeforeHook();
            }
        } else {
            if (isset($json["__after"])) {
                $obj->set("__after", $json["__after"]);
            } else {
                $obj->disableAfterHook();
            }
        }

        // in beforeUpdate hook, attach updatedKeys to object so user
        // can detect changed keys in hook.
        if (isset($json["_updatedKeys"])) {
            $obj->updatedKeys = $json["_updatedKeys"];
        }

        $meta["remoteAddress"] = $this->env["REMOTE_ADDR"];
        $result = Cloud::runHook($className,
                                 $hookName,
                                 $obj,
                                 User::getCurrentUser(),
                                 $meta);
        if ($hookName == "beforeDelete") {
            $this->renderJSON(array());
        } else if (strpos($hookName, "after") === 0) {
            $this->renderJSON(array("result" => "ok"));
        } else {
            // Encode result object to type-less literal JSON
            $this->renderJSON($obj->toJSON());
        }
    }

    /**
     * Dispatch onVerified hook
     *
     * @param string $type Verify type: email or sms
     * @param array  $body JSON decoded body params
     */
    private function dispatchOnVerified($type, $body) {
        $this->verifyHookSign("__on_verified_{$type}",
                               $body["object"]["__sign"]);

        $userObj = Client::decode($body["object"], null);
        User::saveCurrentUser($userObj);
        $meta["remoteAddress"] = $this->env["REMOTE_ADDR"];
        Cloud::runOnVerified($type, $userObj, $meta);
        $this->renderJSON(array("result" => "ok"));
    }

    /**
     * Dispatch onLogin hook
     *
     * @param array $body JSON decoded body params
     */
    private function dispatchOnLogin($body) {
        $this->verifyHookSign("__on_login__User",
                               $body["object"]["__sign"]);

        $userObj = Client::decode($body["object"], null);
        $meta["remoteAddress"] = $this->env["REMOTE_ADDR"];
        Cloud::runOnLogin($userObj, $meta);
        $this->renderJSON(array("result" => "ok"));
    }

    /**
     * Dispatch onInsight hook
     *
     * @param array $body JSON decoded body params
     */
    private function dispatchOnInsight($body) {
        $this->verifyHookSign("__on_complete_bigquery_job",
                               $body["__sign"]);

        $meta["remoteAddress"] = $this->env["REMOTE_ADDR"];
        Cloud::runOnInsight($body, $meta);
        $this->renderJSON(array("result" => "ok"));
    }

    /**
     * Dispatch LeanEngine functions.
     *
     * @param string $method Request method
     * @param string $url    Request url
     */
    protected function dispatch($method, $url) {
        try {
            $this->__dispatch($method, $url);
        } catch (FunctionError $ex) {
            $status = (int) $ex->status;
            if ( $status >= 500) {
                error_log($ex);
                error_log($ex->getTraceAsString());
            }
            $this->renderError("{$ex->getMessage()}", $ex->getCode(), $ex->status);
        } catch (CloudException $ex) {
            error_log($ex);
            error_log($ex->getTraceAsString());
            $this->renderError("{$ex->getMessage()}", $ex->getCode(), $ex->status);
        } catch (\Exception $ex) {
            error_log($ex);
            error_log($ex->getTraceAsString());
            $this->renderError($ex->getMessage(),
                               $ex->getCode() ? $ex->getCode() : 1,
                               // unhandled internal exception
                               500);
        }
    }

    /**
     * Start engine and process request
     */
    public function start() {
        $this->dispatch($_SERVER["REQUEST_METHOD"],
                        $_SERVER["REQUEST_URI"]);
    }

    /**
     * Redirect to http request to https
     */
    private function httpsRedirect() {
        $reqProto = $this->getHeaderLine("HTTP_X_FORWARDED_PROTO");
        if ($reqProto === "http" &&
            in_array(getenv("LEANCLOUD_APP_ENV"), array("production", "stage"))) {
            $url = "https://{$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']}";
            $this->redirect($url);
        }
    }

    /**
     * Enable https redirect
     */
    public static function enableHttpsRedirect() {
        static::$useHttpsRedirect = true;
    }

}

