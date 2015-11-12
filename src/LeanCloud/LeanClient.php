<?php

namespace LeanCloud;

use LeanCloud\LeanBytes;
use LeanCloud\LeanObject;
use LeanCloud\LeanACL;
use LeanCloud\LeanFile;
use LeanCloud\Operation\IOperation;
use LeanCloud\Storage\IStorage;
use LeanCloud\Storage\SessionStorage;

/**
 * Client interfacing with LeanCloud REST API
 *
 * The client is responsible for sending request to API, and parsing
 * the response into objects in PHP. There are also utility functions
 * such as `::randomFloat` to generate a random float number.
 *
 */
class LeanClient {
    /**
     * Client version
     */
    const VERSION = '0.2.0';

    /**
     * API Endpoints for Regions
     *
     * @var array
     */
    private static $api = array(
        "CN" => "https://api.leancloud.cn",
        "US" => "https://us-api.leancloud.cn");

    /**
     * API Region
     *
     * Default to CN
     *
     * @var string
     */
    private static $apiRegion = "CN";

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
     * Use master key or not
     *
     * @var bool
     */
    private static $useMasterKey = false;

    /**
     * Use production or not
     *
     * @var bool
     */
    private static $useProduction = false;

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
            'User-Agent'   => self::getVersionString()
        );

        // Use session storage by default
        if (!self::$storage) {
            self::$storage = new SessionStorage();
        }

        LeanUser::registerClass();
        LeanRole::registerClass();
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
                                        "with LeanClient::initialize.");
        }
    }

    /**
     * Get version string used as user agent
     *
     * @return string
     */
    private static function getVersionString() {
        return "LeanCloud PHP SDK " . self::VERSION;
    }

    /**
     * Set API region
     *
     * Available regions are "CN" and "US".
     *
     * @param string $region
     */
    public static function useRegion($region) {
        if (!isset(self::$api[$region])) {
            throw new \RuntimeException("Invalid API region: {$region}.");
        }
        self::$apiRegion = $region;
    }

    /**
     * Use production or not
     *
     * @param bool $flag
     */
    public static function useProduction($flag) {
        self::$useProduction = $flag ? true : false;
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
     * Get API Endpoint
     *
     * Build the API endpoint, the returned endpoint will include
     * version string. For example: https://api.leancloud.cn/1.1 .
     *
     * @return string
     */
    public static function getAPIEndPoint() {
        return self::$api[self::$apiRegion] . "/"  . self::$apiVersion;
    }

    /**
     * Build authentication headers
     *
     * @param string $sessionToken Session token of a LeanUser
     * @param bool   $useMasterKey
     * @return array
     */
    public static function buildHeaders($sessionToken, $useMasterKey) {
        if (is_null($useMasterKey)) {
            $useMasterKey = self::$useMasterKey;
        }
        $h = self::$defaultHeaders;

        $h['X-LC-Prod'] = self::$useProduction ? 1 : 0;

        $timestamp = time();
        $key       = $useMasterKey ? self::$appMasterKey : self::$appKey;
        $sign      = md5($timestamp . $key);
        $h['X-LC-Sign'] = $sign . "," . $timestamp;

        if ($useMasterKey) {
            $h['X-LC-Sign'] .= ",master";
        }

        if ($sessionToken) {
            $h['X-LC-Session'] = $sessionToken;
        }

        return $h;
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
     * @param string $sessionToken Session token of a LeanUser
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
        switch($method) {
            case "GET":
                if ($data) {
                    // append GET data as query string
                    curl_setopt($req, CURLOPT_URL,
                                $url ."?". http_build_query($data));
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
        $resp     = curl_exec($req);
        $respCode = curl_getinfo($req, CURLINFO_HTTP_CODE);
        $respType = curl_getinfo($req, CURLINFO_CONTENT_TYPE);
        $error    = curl_errno($req);
        $errno    = curl_errno($req);
        curl_close($req);

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
            throw new CloudException("Bad request", -1);
        }

        $data = json_decode($resp, true);
        if (isset($data["error"])) {
            $code = isset($data["code"]) ? $data["code"] : -1;
            throw new CloudException("{$code} {$data['error']}", $code);
        }
        return $data;
    }

    /**
     * Issue GET request to LeanCloud
     *
     * @param string $path         Request path (without version string)
     * @param array  $data         Payload data
     * @param string $sessionToken Session token of a LeanUser
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
     * @param string $sessionToken Session token of a LeanUser
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
     * @param string $sessionToken Session token of a LeanUser
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
     * @param string $sessionToken Session token of a LeanUser
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
     * @param string $sessionToken Session token of a LeanUser
     * @param array  $headers      Optional headers
     * @param bool   $useMasterkey Use master key or not, optional
     * @return array              JSON decoded associated array
     * @see self::request()
     */
    public static function batch($requests, $sessionToken=null,
                                 $headers=array(), $useMasterKey=null) {
        $response = LeanClient::post("/batch",
                                     array("requests" => $requests),
                                     $sessionToken,
                                     $headers,
                                     $useMasterKey);
        if (count($requests) != count($response)) {
            throw new CloudException("Number of resquest and response " .
                                    "mismatch in batch operation!");
        }
        return $response;
    }

    /**
     * Encode file with params in multipart format
     *
     * @param array  $file     File data and attributes
     * @param array  $params   Key-value params
     * @param string $boundary Boundary string used for frontier
     * @return string          Multipart encoded string
     */
    public static function multipartEncode($file, $params,
                                            $boundary=null) {
        if (!$boundary) {
            $boundary = md5(microtime());
        }

        $body = "";
        forEach($params as $key => $val) {
            $body .= <<<EOT
--{$boundary}
Content-Disposition: form-data; name="{$key}"

{$val}

EOT;
        }

        if (!empty($file)) {
            $mimeType = "application/octet-stream";
            if (isset($file["mimeType"])) {
                $mimeType = $file["mimeType"];
            }
            // escape quotes in file name
            $filename = filter_var($file["name"],
                                   FILTER_SANITIZE_MAGIC_QUOTES);

            $body .= <<<EOT
--{$boundary}
Content-Disposition: form-data; name="file"; filename="{$filename}"
Content-Type: {$mimeType}

{$file['content']}

EOT;
        }

        // append end frontier
        $body .=<<<EOT
--{$boundary}

EOT;

        return $body;
    }

    /**
     * Upload file content to Qiniu storage
     *
     * @param string $token    Qiniu token
     * @param string $content  File content
     * @param string $name     File name
     * @param string $mimeType MIME type of file
     * @return array           JSON response from qiniu
     * @throws CloudException, RuntimeException
     */
    public static function uploadToQiniu($token, $content, $name,
                                          $mimeType=null) {
        $boundary = md5(microtime());
        $file     = array("name"     => $name,
                          "content"  => $content,
                          "mimeType" => $mimeType);
        $params   = array("token" => $token, "key" => $name);
        $body     = static::multipartEncode($file, $params, $boundary);

        $headers[] = "User-Agent: " . self::getVersionString();
        $headers[] = "Content-Type: multipart/form-data;" .
                     " boundary={$boundary}";
        $headers[] = "Content-Length: " . strlen($body);

        $url = "http://upload.qiniu.com";
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        $resp     = curl_exec($ch);
        $respCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $respType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        $error    = curl_errno($ch);
        $errno    = curl_errno($ch);
        curl_close($ch);

        /** type of error:
         *  - curl error
         *  - http status error 4xx, 5xx
         *  - rest api error
         */
        if ($errno > 0) {
            throw new \RuntimeException("CURL connection ($url) error: " .
                                        "$errno $error",
                                        $errno);
        }

        $data = json_decode($resp, true);
        if (isset($data["error"])) {
            $code = isset($data["code"]) ? $data["code"] : -1;
            throw new CloudException("{$code} {$data['error']}", $code);
        }
        return $data;
    }

    /**
     * Encode value for sending to LeanCloud
     *
     * @param mixed $value
     * @return mixed
     */
    public static function encode($value) {
        if (is_null($value) || is_scalar($value)) {
            return $value;
        } else if (($value instanceof \DateTime) ||
                   ($value instanceof \DateTimeImmutable)) {
            return array("__type" => "Date",
                         "iso"    => self::formatDate($value));
        } else if ($value instanceof LeanObject) {
            return $value->getPointer();
        } else if ($value instanceof IOperation ||
                   $value instanceof GeoPoint   ||
                   $value instanceof LeanBytes  ||
                   $value instanceof LeanACL    ||
                   $value instanceof LeanFile) {
            return $value->encode();
        } else if (is_array($value)) {
            $res = array();
            forEach($value as $key => $val) {
                $res[$key] = self::encode($val);
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
        $utc = new \DateTime($date->format("c"));
        $utc->setTimezone(new \DateTimezone("UTC"));
        $iso = $utc->format("Y-m-d\TH:i:s.u");
        // PHP does not support sub seconds well, it will always gives 6 zero
        // digits as microseconds. We chop 3 zeros off:
        //  `2015-09-18T08:06:20.000000Z` -> `2015-09-18T08:06:20.000Z`
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
        if ($key == 'ACL') {
            return new LeanACL($value);
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

        if ($type == "Date") {
            // return time in default time zone
            return new \DateTime($value["iso"]);
        }
        if ($type == "Bytes") {
            return LeanBytes::createFromBase64Data($value["base64"]);
        }
        if ($type == "GeoPoint") {
            return new GeoPoint($value["latitude"], $value["longitude"]);
        }
        if ($type == "File") {
            $file = new LeanFile($value["name"]);
            $file->mergeAfterFetch($value);
            return $file;
        }
        if ($type == "Pointer" || $type == "Object") {
            $obj = LeanObject::create($value["className"], $value["objectId"]);
            unset($value["__type"]);
            unset($value["className"]);
            if (!empty($value)) {
                $obj->mergeAfterFetch($value);
            }
            return $obj;
        }
        if ($type == "Relation") {
            return new LeanRelation(null, $key, $value["className"]);
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

