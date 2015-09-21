<?php

namespace LeanCloud;

/**
 * LeanClient - HTTP LeanClient talking to LeanCloud REST API
 *
 * Usage examples:
 *
 *     LeanClient::initialize($appId, $appKey, $masterKey);
 *     LeanClient::useRegion("CN");
 *     LeanClient::useProduction(true);
 *     LeanClient::useMasterKey(false);
 *
 *     LeanClient::request("GET", "classes/{Object}/{id}", $payload=null,
 *                         $headers=null,
 *                         $useMasterKey=false);
 *     LeanClient::get("classes/{Object}/{id}", $headers=null);
 *     LeanClient::put("classes/{Object}", $payload,
 *                      $headers=null,
 *                      $useMasterKey=false);
 *     LeanClient::post("classes/{Object}", $payload, $headers=null,
 *                      $useMasterKey=false);
 *     LeanClient::delete("classes/{Object}/{id}", $headers=null,
 *                         $useMasterKey=false);
 */

class LeanClient {
    /**
     * LeanClient version string
     *
     * @var string
     */
    private static $versionString = '0.1.0';

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
     * API Timeout (in seconds);
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
    private static $default_headers;

    /**
     * Initialize application credentials
     *
     * @param string $appId        Application ID
     * @param string $appKey       Application key
     * @param string $appMasterKey Application master key
     *
     * @return void
     */
    public static function initialize($appId, $appKey, $appMasterKey) {
        self::$appId        = $appId;
        self::$appKey       = $appKey;
        self::$appMasterKey = $appMasterKey;

        self::$default_headers = array(
            'X-LC-Id' => self::$appId,
            'Content-Type' => 'application/json;charset=utf-8',
            'User-Agent'   => 'LeanCloud PHP SDK ' . self::$versionString
        );
    }

    /**
     * Assert client correctly initialized.
     */
    private static function assertInitialized() {
        $e = new \ErrorException("LeanClient is not adequately initialized, please use LeanClient::initialize to initialize it.");
        if (!isset(self::$appId) &&
            !isset(self::$appKey) &&
            !isset(self::$appMasterKey)) {
            throw $e;
        }
        return true;
    }

    /**
     * Set API region
     *
     * Available regions are "CN" and "US".
     * @param  string $region
     * @return void
     */
    public static function useRegion($region) {
        if (!isset(self::$api[$region])) {
            throw new \ErrorException("Invalid API region: " . $region);
        }
        self::$apiRegion = $region;
    }

    /**
     * Use production or not
     *
     * @param  bool $flag
     * @return void
     */
    public static function useProduction(bool $flag) {
        self::$useProduction = $flag ? true : false;
    }

    /**
     * Use master key or not
     *
     * @param  bool $flag
     * @return void
     */
    public static function useMasterKey(bool $flag) {
        self::$useMasterKey = $flag ? true : false;
    }

    /**
     * Get API Endpoint
     *
     * Build the API endpoint with api region and version, the returned
     * endpoint will include version string. E.g.
     * https://api.leancloud.cn/1.1 .
     *
     * @return string
     */
    public static function getAPIEndPoint() {
        return self::$api[self::$apiRegion] . "/"  . self::$apiVersion;
    }

    /**
     * Build request headers from settings
     *
     * @param  array $headers      Additional headers
     * @param  bool  $useMasterKey
     * @return array
     */
    private static function getRequestHeaders($headers, $useMasterKey) {
        $h = self::$default_headers;

        $h['X-LC-Prod'] = self::$useProduction ? 1 : 0;

        $timestamp = time();
        $key       = $useMasterKey ? self::$appMasterKey : self::$appKey;
        $sign      = md5($timestamp . $key);
        $h['X-LC-Sign'] = $sign . "," . $timestamp;
        if ($useMasterKey || self::$useMasterKey) {
            $h['X-LC-Sign'] .= ",master";
        }

        if (!empty($headers)) {
            $h = array_merge($h, $headers);
        }

        // TODO: add current user session token
        // $headers['X-LC-Session'] = user._session_token
        return $h;
    }

    /**
     * Issue request to LeanCloud
     *
     * @param string $method       GET, POST, PUT, DELETE
     * @param string $path         Request path (without version string)
     * @param string $data         Payload data
     * @param array  $headers      Optional headers
     * @param bool   $useMasterkey Use master key or not, optional
     *
     * @return array               json decoded associated array
     * @throws ErrorException, LeanException
     */
    public static function request($method, $path, $data,
                                   $headers=array(),
                                   $useMasterKey=false) {
        self::assertInitialized();
        $url  = self::getAPIEndPoint();
        $url .= $path;

        $headers = self::getRequestHeaders($headers, $useMasterKey);
        $headers = array_map(function($key, $val) { return "$key: $val";},
                             array_keys($headers),
                             $headers);

        $req = curl_init($url);
        curl_setopt($req, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($req, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($req, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($req, CURLOPT_TIMEOUT, self::$apiTimeout);
        // curl_setopt($req, CURLINFO_HEADER_OUT, true);
        switch($method) {
            case "POST":
                curl_setopt($req, CURLOPT_POST, 1);
                curl_setopt($req, CURLOPT_POSTFIELDS, json_encode($data));
                break;
            case "PUT":
                curl_setopt($req, CURLOPT_CUSTOMREQUEST, $method);
                curl_setopt($req, CURLOPT_POSTFIELDS, json_encode($data));
                break;
            case "DELETE":
                curl_setopt($req, CURLOPT_CUSTOMREQUEST, $method);
                break;
            default:
                break;
        }
        $resp     = curl_exec($req);
        $respCode = curl_getinfo($req, CURLINFO_HTTP_CODE);
        $respType = curl_getinfo($req, CURLINFO_CONTENT_TYPE);
        $errno    = curl_errno($req);
        curl_close($req);

        /** type of error:
          *  - curl connection error
          *  - http status error 4xx, 5xx
          *  - rest api error
          */
        if ($errno > 0) {
            throw new ErrorException("Failed to connect to API service.", $errno);
        }
        if (strpos($respType, "text/html") !== false) {
            throw new LeanException(-1, "Bad request");
        }

        $data = json_decode($resp, true);
        if (isset($data["error"])) {
            $code = isset($data["code"]) ? $data["code"] : -1;
            throw new LeanException("{$code} {$data['error']}", $code);
        }
        return $data;
    }

    /**
     * Issue GET request to LeanCloud
     *
     * @param string $method       GET, POST, PUT, DELETE
     * @param string $path         Request path (without version string)
     * @param array  $headers      Optional headers
     * @param bool   $useMasterkey Use master key or not, optional
     *
     * @return array               json decoded associated array
     * @throws ErrorException, LeanException
     */
    public static function get($path, $headers=array(), $useMasterKey=false) {
        return self::request("GET", $path, null, $headers, $useMasterKey);
    }

    /**
     * Issue POST request to LeanCloud
     *
     * @param string $method       GET, POST, PUT, DELETE
     * @param string $path         Request path (without version string)
     * @param string $data         Payload data
     * @param array  $headers      Optional headers
     * @param bool   $useMasterkey Use master key or not, optional
     *
     * @return array               json decoded associated array
     * @throws ErrorException, LeanException
     */
    public static function post($path, $data, $headers=array(), $useMasterKey=false) {
        return self::request("POST", $path, $data, $headers, $useMasterKey);
    }

    /**
     * Issue PUT request to LeanCloud
     *
     * @param string $method       GET, POST, PUT, DELETE
     * @param string $path         Request path (without version string)
     * @param string $data         Payload data
     * @param array  $headers      Optional headers
     * @param bool   $useMasterkey Use master key or not, optional
     *
     * @return array               json decoded associated array
     * @throws ErrorException, LeanException
     */
    public static function put($path, $data, $headers=array(), $useMasterKey=false) {
        return self::request("PUT", $path, $data, $headers, $useMasterKey);
    }

    /**
     * Issue DELETE request to LeanCloud
     *
     * @param string $method       GET, POST, PUT, DELETE
     * @param string $path         Request path (without version string)
     * @param array  $headers      Optional headers
     * @param bool   $useMasterkey Use master key or not, optional
     *
     * @return array               json decoded associated array
     * @throws ErrorException, LeanException
     */
    public static function delete($path, $headers=array(), $useMasterKey=false) {
        return self::request("DELETE", $path, $headers, $useMasterKey);
    }

    /**
     * Encode value to LeanCloud compatible type.
     *
     * @param mixed $value
     * @return mixed
     */
    public static function encode($value) {
        if (is_scalar($value)) {
            return $value;
        } else if (($value instanceof \DateTime) ||
                   ($value instanceof \DateTimeImmutable)) {
            return array("__type" => "Date",
                         "iso"    => self::formatDate($value));
        }
    }

    /**
     * Format date according to LeanCloud spec.
     *
     * @param DateTime $date
     * @return string
     */
    public static function formatDate($date) {
        $s = $date->format("Y-m-d\TH:i:s.u");
        // PHP does not support sub seconds well, it and always gives 6 zero
        // digits as microseconds. We chop 3 zeros off:
        //  `2015-09-18T08:06:20.000000Z` -> `2015-09-18T08:06:20.000Z`
        $s = substr($s, 0, 23) . "Z";
        return $s;
    }
}

?>