<?php

use LeanCloud\LeanClient;
use LeanCloud\CloudException;

class LeanEngineTest extends PHPUnit_Framework_TestCase {
    public static function setUpBeforeClass() {
        LeanClient::initialize(
            getenv("LC_APP_ID"),
            getenv("LC_APP_KEY"),
            getenv("LC_APP_MASTER_KEY"));
    }

    private function request($url, $method, $data=null) {
        $appUrl = "http://" . getenv("LC_APP_HOST") . ":" .
                getenv("LC_APP_PORT");
        $url = $appUrl . $url;
        $headers = LeanClient::buildHeaders(null, true);
        $headers["Content-Type"] = "application/json;charset=utf-8";
        $headers["Origin"] = getenv("LC_APP_HOST"); // emulate CORS
        $h = array_map(
            function($k, $v) {return "$k: $v";},
            array_keys($headers),
            $headers
        );

        $req = curl_init($url);
        curl_setopt($req, CURLOPT_HTTPHEADER, $h);
        curl_setopt($req, CURLOPT_RETURNTRANSFER, true);
        if ($method == "POST") {
            curl_setopt($req, CURLOPT_POST, 1);
            curl_setopt($req, CURLOPT_POSTFIELDS, json_encode($data));
        } else {
            // GET
            if ($data) {
                curl_setopt($req, CURLOPT_URL,
                            $url . "?" . http_build_query($data));
            }
        }
        $resp = curl_exec($req);
        $errno = curl_errno($req);
        curl_close($req);
        if ($errno > 0) {
            throw new \RuntimeException("CURL connection error $errno: $url");
        }
        $data = json_decode($resp, true);
        if (isset($data["error"])) {
            $code = isset($data["code"]) ? $data["code"] : -1;
            throw new CloudException("{$code} {$data['error']}", $code);
        }
        return $data;
    }

    public function testPingEngine() {
        $resp = $this->request("/__engine/1/ping", "GET");
        $this->assertArrayHasKey("runtime", $resp);
        $this->assertArrayHasKey("version", $resp);
    }

    public function testGetFuncitonMetadata() {
        $resp = $this->request("/1/functions/_ops/metadatas", "GET");
        $this->assertContains("hello", $resp);
    }

    public function testCloudFunctionHello() {
        $resp = $this->request("/1/functions/hello", "POST", array());
        $this->assertEquals("hello", $resp["result"]);
    }

    public function testFunctionWithParam() {
        $resp = $this->request("/1/functions/sayHello", "POST", array(
            "name" => "alice"
        ));
        $this->assertEquals("hello alice", $resp["result"]);
    }
}

