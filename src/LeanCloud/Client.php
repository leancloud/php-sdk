<?php

namespace LeanCloud;

use LeanCloud\Bytes;
use LeanCloud\LeanObject;
use LeanCloud\ACL;
use LeanCloud\File;
use LeanCloud\User;
use LeanCloud\Operation\IOperation;
use LeanCloud\Storage\IStorage;
use LeanCloud\Storage\SessionStorage;
use LeanCloud\AppRouter;

/**
 * Client interfacing with LeanCloud REST API
 *
 * The client is responsible for sending request to API, and parsing
 * the response into objects in PHP. There are also utility functions
 * such as `::randomFloat` to generate a random float number.
 *
 */
class Client {
    /**
     * Client version
     */
    const VERSION = '0.10.3';

    /**
     * API Version string
     *
     * @var string
     */
    private static $apiVersion = "1.1";

    /**
     * API Timeout
     *
     * Default to 15 seconds
     *
     * @var int
     */
    private static $apiTimeout = 15;

    /**
     * Application ID
     *
     * @var string
     */
    private static $appId;

    /**
     * Application Key
     *
     * @var string
     */
    private static $appKey;

    /**
     * Application master key
     *
     * @var string
     */
    private static $appMasterKey;

    /**
     *  Server url
     *
     * @var string
     */
    private static $serverUrl;

    /**
     * Use master key or not
     *
     * @var bool
     */
    private static $useMasterKey = false;

    /**
     * Is in production or not
     *
     * @var bool
     */
    public static $isProduction = false;

    /**
     * Is in debug mode or not
     *
     * @var bool
     */
    private static $debugMode = false;

    /**
     * Default request headers
     *
     * @var array
     */
    private static $defaultHeaders;

    /**
     * Persistent key-value storage
     *
     * @var IStorage
     */
    private static $storage;

    /**
     * Initialize application key and settings
     *
     * @param string $appId        Application ID
     * @param string $appKey       Application key
     * @param string $appMasterKey Application master key
     */
    public static function initialize($appId, $appKey, $appMasterKey) {
        self::$appId        = $appId;
        self::$appKey       = $appKey;
        self::$appMasterKey = $appMasterKey;

        self::$defaultHeaders = array(
            'X-LC-Id' => self::$appId,
            'Content-Type' => 'application/json;charset=utf-8',
            'Accept-Encoding' => 'gzip, deflate',
            'User-Agent'   => self::getVersionString()
        );

        // Use session storage by default
        if (!self::$storage) {
            self::$storage = new SessionStorage();
        }

        self::useProduction(getenv("LEANCLOUD_APP_ENV") == "production");
        User::registerClass();
        Role::registerClass();
    }

    /**
     * Assert client is correctly initialized
     *
     * @throws RuntimeException
     */
    private static function assertInitialized() {
        if (!isset(self::$appId) &&
            !isset(self::$appKey) &&
            !isset(self::$appMasterKey)) {
            throw new \RuntimeException("Client is not initialized, " .
                                        "please specify application key " .
                                        "with Client::initialize.");
        }
    }

    /**
     * Get version string used as user agent
     *
     * @return string
     */
    public static function getVersionString() {
        return "LeanCloud PHP SDK " . self::VERSION;
    }

    /**
     * Set API region
     *
     * See `LeanCloud\Region` for available regions.
     *
     * @param mixed $region
     */
    public static function useRegion($region) {
        self::assertInitialized();
        AppRouter::getInstance(self::$appId)->setRegion($region);
    }

    /**
     * Use production or not
     *
     * @param bool $flag Default false
     */
    public static function useProduction($flag) {
        self::$isProduction = $flag ? true : false;
    }

    /**
     * Set debug mode
     *
     * Enable debug mode to log request params and response.
     *
     * @param bool $flag Default false
     */
    public static function setDebug($flag) {
        self::$debugMode = $flag ? true : false;
    }

    /**
     * Use master key or not
     *
     * @param bool $flag
     */
    public static function useMasterKey($flag) {
        self::$useMasterKey = $flag ? true : false;
    }

    /**
     * Set server url
     *
     * Explicitly set server url with which this client will communicate.
     * Url shall be in the form of: `https://api.leancloud.cn` .
     *
     * @param string $url
     */
    public static function setServerUrl($url) {
        self::$serverUrl = rtrim($url, "/");
    }

    /**
     * Get API Endpoint
     *
     * The returned endpoint will include version string.
     * For example: https://api.leancloud.cn/1.1 .
     *
     * @return string
     */
    public static function getAPIEndPoint() {
        if ($url = self::$serverUrl) {
            return $url . "/" . self::$apiVersion;
        } else if ($url = getenv("LEANCLOUD_API_SERVER")) {
            return $url . "/" . self::$apiVersion;
        } else {
            $host = AppRouter::getInstance(self::$appId)->getRoute(AppRouter::API_SERVER_KEY);
            return "https://{$host}/" . self::$apiVersion;
        }
    }

    /**
     * Build authentication headers
     *
     * @param string $sessionToken Session token of a User
     * @param bool   $useMasterKey
     * @return array
     */
    public static function buildHeaders($sessionToken, $useMasterKey) {
        if (is_null($useMasterKey)) {
            $useMasterKey = self::$useMasterKey;
        }
        $h = self::$defaultHeaders;

        $h['X-LC-Prod'] = self::$isProduction ? 1 : 0;

        $timestamp = time();
        $key       = $useMasterKey ? self::$appMasterKey : self::$appKey;
        $sign      = md5($timestamp . $key);
        $h['X-LC-Sign'] = $sign . "," . $timestamp;

        if ($useMasterKey) {
            $h['X-LC-Sign'] .= ",master";
        }

        if (!$sessionToken) {
            $sessionToken = User::getCurrentSessionToken();
        }

        if ($sessionToken) {
            $h['X-LC-Session'] = $sessionToken;
        }

        return $h;
    }

    /**
     * Verify app ID and sign
     *
     * The sign must be in the format of "{md5sum},{timestamp}[,master]",
     * which follows the format as in header "X-LC-Sign".
     *
     * @param string $appId App Id
     * @param string $sign  Request sign
     * @return bool
     */
    public static function verifySign($appId, $sign) {
        if (!$appId || ($appId != self::$appId)) {
            return false;
        }
        $parts = explode(",", $sign);
        $key   = self::$appKey;
        if (isset($parts[2]) && "master" === trim($parts[2])) {
            $key = self::$appMasterKey;
        }
        return $parts[0] === md5(trim($parts[1]) . $key);
    }

    /**
     * Verify app ID and key
     *
     * The key shall be in format of "{key}[,master]", it will be verified
     * as master key if master suffix present.
     *
     * @param string $appId App Id
     * @param string $key   App key or master key
     * @return bool
     */
    public static function verifyKey($appId, $key) {
        if (!$appId || ($appId != self::$appId)) {
            return false;
        }
        $parts = explode(",", $key);
        if (isset($parts[1]) && "master" === trim($parts[1])) {
            return self::$appMasterKey === $parts[0];
        }
        return self::$appKey === $parts[0];
    }

    /**
     * Generate a sign used to auth hook invocation on LeanEngine
     *
     * @param string  $hookName E.g. "__before_for_Object"
     * @param integer $msec Timestamap in microseconds
     * @return string
     */
    public static function signHook($hookName, $msec) {
        $hash = hash_hmac("sha1", "{$hookName}:{$msec}", self::$appMasterKey);
        return "{$msec},{$hash}";
    }

    /**
     * Verify a signed hook
     *
     * @param string $hookName
     * @param string $sign
     * @return bool
     */
    public static function verifyHookSign($hookName, $sign) {
        if ($sign) {
            $parts = explode(",", $sign);
            $msec  = $parts[0];
            return self::signHook($hookName, $msec) === $sign;
        }
        return false;
    }

    /**
     * Issue request to LeanCloud
     *
     * The data is passing in as an associative array, which will be encoded
     * into JSON if the content-type header is "application/json", or
     * be appended to url as query string if it's GET request.
     *
     * The optional headers do have higher precedence, if provided it
     * will overwrite the items in default headers.
     *
     * @param string $method       GET, POST, PUT, DELETE
     * @param string $path         Request path (without version string)
     * @param array  $data         Payload data
     * @param string $sessionToken Session token of a User
     * @param array  $headers      Optional headers
     * @param bool   $useMasterkey Use master key or not
     * @return array               JSON decoded associative array
     * @throws RuntimeException, CloudException
     */
    public static function request($method, $path, $data,
                                   $sessionToken=null,
                                   $headers=array(),
                                   $useMasterKey=null) {
        self::assertInitialized();
        $url  = self::getAPIEndPoint();
        $url .= $path;

        $defaultHeaders = self::buildHeaders($sessionToken, $useMasterKey);
        if (empty($headers)) {
            $headers = $defaultHeaders;
        } else {
            $headers = array_merge($defaultHeaders, $headers);
        }
        if (strpos($headers["Content-Type"], "/json") !== false) {
            $json = json_encode($data);
        }

        // Build headers list in HTTP format
        $headersList = array_map(function($key, $val) { return "$key: $val";},
                                 array_keys($headers),
                                 $headers);

        $req = curl_init($url);
        curl_setopt($req, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($req, CURLOPT_HTTPHEADER, $headersList);
        curl_setopt($req, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($req, CURLOPT_TIMEOUT, self::$apiTimeout);
        // curl_setopt($req, CURLINFO_HEADER_OUT, true);
        // curl_setopt($req, CURLOPT_HEADER, true);
        curl_setopt($req, CURLOPT_ENCODING, '');
        switch($method) {
            case "GET":
                if ($data) {
                    // append GET data as query string
                    $url .= "?" . http_build_query($data);
                    curl_setopt($req, CURLOPT_URL, $url);
                }
                break;
            case "POST":
                curl_setopt($req, CURLOPT_POST, 1);
                curl_setopt($req, CURLOPT_POSTFIELDS, $json);
                break;
            case "PUT":
                curl_setopt($req, CURLOPT_POSTFIELDS, $json);
                curl_setopt($req, CURLOPT_CUSTOMREQUEST, $method);
            case "DELETE":
                curl_setopt($req, CURLOPT_CUSTOMREQUEST, $method);
                break;
            default:
                break;
        }
        $reqId = rand(100,999);
        if (self::$debugMode) {
            error_log("[DEBUG] HEADERS {$reqId}:" . json_encode($headersList));
            error_log("[DEBUG] REQUEST {$reqId}: {$method} {$url} {$json}");
        }
        // list($headers, $resp) = explode("\r\n\r\n", curl_exec($req), 2);
        $resp     = curl_exec($req);
        $respCode = curl_getinfo($req, CURLINFO_HTTP_CODE);
        $respType = curl_getinfo($req, CURLINFO_CONTENT_TYPE);
        $error    = curl_error($req);
        $errno    = curl_errno($req);
        curl_close($req);

        if (self::$debugMode) {
            error_log("[DEBUG] RESPONSE {$reqId}: {$resp}");
        }

        /** type of error:
          *  - curl connection error
          *  - http status error 4xx, 5xx
          *  - rest api error
          */
        if ($errno > 0) {
            throw new \RuntimeException("CURL connection ($url) error: " .
                                        "$errno $error",
                                        $errno);
        }
        if (strpos($respType, "text/html") !== false) {
            throw new CloudException("Bad response type text/html", -1, $respCode,
                                     $method, $url);
        }

        $data = json_decode($resp, true);
        if (isset($data["error"])) {
            $code = isset($data["code"]) ? $data["code"] : -1;
            throw new CloudException("{$data['error']}", $code, $respCode,
                                     $method, $url);
        }
        return $data;
    }

    /**
     * Issue GET request to LeanCloud
     *
     * @param string $path         Request path (without version string)
     * @param array  $data         Payload data
     * @param string $sessionToken Session token of a User
     * @param array  $headers      Optional headers
     * @param bool   $useMasterkey Use master key or not
     * @return array               JSON decoded associated array
     * @see self::request()
     */
    public static function get($path, $data=null, $sessionToken=null,
                               $headers=array(), $useMasterKey=null) {
        return self::request("GET", $path, $data, $sessionToken,
                             $headers, $useMasterKey);
    }

    /**
     * Issue POST request to LeanCloud
     *
     * @param string $path         Request path (without version string)
     * @param array  $data         Payload data
     * @param string $sessionToken Session token of a User
     * @param array  $headers      Optional headers
     * @param bool   $useMasterkey Use master key or not, optional
     * @return array               JSON decoded associated array
     * @see self::request()
     */
    public static function post($path, $data, $sessionToken=null,
                                $headers=array(), $useMasterKey=null) {
        return self::request("POST", $path, $data, $sessionToken,
                             $headers, $useMasterKey);
    }

    /**
     * Issue PUT request to LeanCloud
     *
     * @param string $path         Request path (without version string)
     * @param array  $data         Payload data
     * @param string $sessionToken Session token of a User
     * @param array  $headers      Optional headers
     * @param bool   $useMasterkey Use master key or not, optional
     * @return array               JSON decoded associated array
     * @see self::request()
     */
    public static function put($path, $data, $sessionToken=null,
                               $headers=array(), $useMasterKey=null) {
        return self::request("PUT", $path, $data, $sessionToken,
                             $headers, $useMasterKey);
    }

    /**
     * Issue DELETE request to LeanCloud
     *
     * @param string $path         Request path (without version string)
     * @param string $sessionToken Session token of a User
     * @param array  $headers      Optional headers
     * @param bool   $useMasterkey Use master key or not, optional
     * @return array               JSON decoded associated array
     * @see self::request()
     */
    public static function delete($path, $sessionToken=null,
                                  $headers=array(), $useMasterKey=null) {
        return self::request("DELETE", $path, null, $sessionToken,
                             $headers, $useMasterKey);
    }

    /**
     * Issue a batch request
     *
     * @param array  $requests     Array of requests in batch op
     * @param string $sessionToken Session token of a User
     * @param array  $headers      Optional headers
     * @param bool   $useMasterkey Use master key or not, optional
     * @return array              JSON decoded associated array
     * @see self::request()
     */
    public static function batch($requests, $sessionToken=null,
                                 $headers=array(), $useMasterKey=null) {
        $response = Client::post("/batch",
                                     array("requests" => $requests),
                                     $sessionToken,
                                     $headers,
                                     $useMasterKey);

        $batchRequestError = new BatchRequestError();
        forEach($requests as $i => $req) {
            if (isset($response[$i]["error"])) {
                $batchRequestError->add($req, $response[$i]["error"]);
            }
        }

        if (!$batchRequestError->isEmpty()) {
            throw $batchRequestError;
        }

        return $response;
    }

    /**
     * Recursively encode value as JSON representation
     *
     * By default LeanObject will be encoded as pointer, though
     * `$encoder` could be provided to encode to customized type, such
     * as full `__type` annotated json object. The $encoder must be
     * name of instance method of object.
     *
     * To avoid infinite loop in the case of circular object
     * references, previously seen objects (`$seen`) are encoded
     * in pointer, even a customized encoder was provided.
     *
     * ```php
     * $obj = new TestObject();
     * $obj->set("owner", $user);
     *
     * // encode object to full JSON, with `__type` and `className`
     * Client::encode($obj, "toFullJSON");
     *
     * // encode object to literal JSON, without `__type` and `className`
     * Client::encode($obj, "toJSON");
     * ```
     *
     * @param mixed  $value
     * @param string $encoder Object encoder name, e.g.: getPointer, toJSON
     * @param array  $seen    Array of Object that has been traversed
     * @return mixed
     */
    public static function encode($value,
                                  $encoder=null,
                                  $seen=array()) {
        if (is_null($value) || is_scalar($value)) {
            return $value;
        } else if (($value instanceof \DateTime) ||
                   ($value instanceof \DateTimeImmutable)) {
            return array("__type" => "Date",
                         "iso"    => self::formatDate($value));
        } else if ($value instanceof LeanObject) {
            if ($encoder && $value->hasData() && !in_array($value, $seen)) {
                $seen[] = $value;
                return call_user_func(array($value, $encoder), $seen);
            } else {
                return $value->getPointer();
            }
        } else if ($value instanceof IOperation ||
                   $value instanceof GeoPoint   ||
                   $value instanceof Bytes      ||
                   $value instanceof ACL        ||
                   $value instanceof Relation   ||
                   $value instanceof File) {
            return $value->encode();
        } else if (is_array($value)) {
            $res = array();
            forEach($value as $key => $val) {
                $res[$key] = self::encode($val, $encoder, $seen);
            }
            return $res;
        } else {
            throw new \RuntimeException("Dont know how to encode " .
                                      gettype($value));
        }
    }

    /**
     * Format date according to LeanCloud spec.
     *
     * @param DateTime $date
     * @return string
     */
    public static function formatDate($date) {
        $utc = clone $date;
        $utc->setTimezone(new \DateTimezone("UTC"));
        $iso = $utc->format("Y-m-d\TH:i:s.u");
        // Milliseconds precision is required for server to correctly parse time,
        // thus we have to chop off last 3 microseconds to milliseconds.
        $iso = substr($iso, 0, 23) . "Z";
        return $iso;
    }

    /**
     * Decode value from LeanCloud response.
     *
     * @param mixed  $value Value to decode
     * @param string $key   Field key for the value
     * @return mixed
     */
    public static function decode($value, $key) {
        if (!is_array($value)) {
            return $value;
        }
        if ($key === 'ACL') {
            return new ACL($value);
        }
        if (!isset($value["__type"])) {
            $out = array();
            forEach($value as $k => $v) {
                $out[$k] = self::decode($v, $k);
            }
            return $out;
        }

        // Parse different data type from server.
        $type = $value["__type"];

        if ($type === "Date") {
            // return time in default time zone
            $date = new \DateTime($value["iso"]);
            $date->setTimezone(new \DateTimeZone(date_default_timezone_get()));
            return $date;
        }
        if ($type === "Bytes") {
            return Bytes::createFromBase64Data($value["base64"]);
        }
        if ($type === "GeoPoint") {
            return new GeoPoint($value["latitude"], $value["longitude"]);
        }
        if ($type === "File") {
            $file = new File($value["name"]);
            $file->mergeAfterFetch($value);
            return $file;
        }
        if ($type === "Pointer" || $type === "Object") {
            $id  = isset($value["objectId"]) ? $value["objectId"] : null;
            $obj = LeanObject::create($value["className"], $id);
            unset($value["__type"]);
            unset($value["className"]);
            if (!empty($value)) {
                $obj->mergeAfterFetch($value);
            }
            return $obj;
        }
        if ($type === "Relation") {
            return new Relation(null, $key, $value["className"]);
        }
    }

    /**
     * Get storage
     *
     * @return IStorage
     */
    public static function getStorage() {
        return self::$storage;
    }

    /**
     * Set storage
     *
     * It unset the storage if $storage is null.
     *
     * @param IStorage $storage
     */
    public static function setStorage($storage) {
        self::$storage = $storage;
    }

    /**
     * Generate a random float between [$min, $max)
     *
     * @param float $min
     * @param float $max
     * @return float
     */
    public static function randomFloat($min=0, $max=1) {
        $M = mt_getrandmax();
        return $min + (mt_rand(0, $M - 1) / $M) * ($max - $min);
    }

}
