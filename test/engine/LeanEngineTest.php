<?php

use LeanCloud\Client;
use LeanCloud\CloudException;
use LeanCloud\User;
use PHPUnit\Framework\TestCase;

/**
 * Test LeanEngine app server
 *
 * The test suite runs against a running server started at index.php, please
 * see that for returned response.
 */

class LeanEngineTest extends TestCase {
    public static function setUpBeforeClass() {
        Client::initialize(
            getenv("LEANCLOUD_APP_ID"),
            getenv("LEANCLOUD_APP_KEY"),
            getenv("LEANCLOUD_APP_MASTER_KEY"));

        User::clearCurrentUser();
    }

    private function request($url, $method, $data=null) {
        $appUrl = "http://" . getenv("LEANCLOUD_APP_HOST") . ":" .
                getenv("LEANCLOUD_APP_PORT");
        $url = $appUrl . $url;
        $headers = Client::buildHeaders(null, true);
        $headers["Content-Type"] = "application/json;charset=utf-8";
        $headers["Origin"] = getenv("LEANCLOUD_APP_HOST"); // emulate CORS
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
        $respCode = curl_getinfo($req, CURLINFO_HTTP_CODE);
        curl_close($req);
        if ($errno > 0) {
            throw new \RuntimeException("CURL connection error $errno: $url");
        }
        $data = json_decode($resp, true);
        if (isset($data["error"])) {
            $code = isset($data["code"]) ? $data["code"] : -1;
            throw new CloudException("{$data['error']}", $code, $respCode,
                                     $method, $url);
        }
        return $data;
    }

    private function signHook($hookName, $msec=null) {
        if (!$msec) {
            $msec = round(microtime(true) * 1000);
        }
        $hash = hash_hmac("sha1",
                          "{$hookName}:{$msec}",
                          getenv("LEANCLOUD_APP_MASTER_KEY"));
        return "{$msec},{$hash}";
    }

    public function testPingEngine() {
        $resp = $this->request("/__engine/1/ping", "GET");
        $this->assertArrayHasKey("runtime", $resp);
        $this->assertArrayHasKey("version", $resp);
    }

    public function testGetFuncitonMetadata() {
        $resp = $this->request("/1/functions/_ops/metadatas", "GET");
        $this->assertContains("hello", $resp["result"]);
    }

    public function testCloudFunctionHello() {
        $resp = $this->request("/1/functions/hello", "POST", array());
        $this->assertEquals("hello", $resp["result"]);
    }

    public function testCallFunctionWithObject() {
        $obj  = array(
            "__type"    => "Object",
            "className" => "TestObject",
            "objectId"  => "id001",
            "name"      => "alice"
        );
        $resp = $this->request("/1/call/updateObject", "POST", array(
            "object" => $obj
        ));
        $this->assertEquals($obj["className"], $resp["result"]["className"]);
        $this->assertEquals($obj["objectId"],  $resp["result"]["objectId"]);
        $this->assertEquals(42,  $resp["result"]["__testKey"]);
    }

    public function testFunctionWithParam() {
        $resp = $this->request("/1/functions/sayHello", "POST", array(
            "name" => "alice"
        ));
        $this->assertEquals("hello alice", $resp["result"]);
    }

    public function testMetaParamsShouldHaveRemoteAddress() {
        $resp = $this->request("/1/functions/getMeta", "POST", array(
            "name" => "alice"
        ));
        $this->assertNotEmpty($resp["result"]["remoteAddress"]);
    }

    public function testOnInsight() {
        $resp = $this->request("/1/functions/BigQuery/onComplete", "POST", array(
            "id"      => "id001",
            "status"  => "OK",
            "message" => "Big query completed successfully.",
            "__sign"  => $this->signHook("__on_complete_bigquery_job")
        ));
        $this->assertEquals("ok", $resp["result"]);
    }

    public function testOnLogin() {
        $resp = $this->request("/1/functions/_User/onLogin", "POST", array(
            "object" => array(
                "__type"    => "Object",
                "className" => "_User",
                "objectId"  => "id002",
                "username"  => "alice",
                "__sign"    => $this->signHook("__on_login__User")
            )
        ));
        $this->assertEquals("ok", $resp["result"]);
    }

    public function testOnVerifiedSms() {
        $resp = $this->request("/1/functions/onVerified/sms", "POST", array(
            "object" => array(
                "__type"    => "Object",
                "className" => "_User",
                "objectId"  => "id002",
                "username"  => "alice",
                "__sign"    => $this->signHook("__on_verified_sms")
            )
        ));
        $this->assertEquals("ok", $resp["result"]);
    }

    public function testBeforeSave() {
        $obj = array(
            "name"      => "alice",
            "likes"     => array(
                "__type"    => "Pointer",
                "className" => "TestObject",
                "objectId"  => "id002"
            ),
            "__before"  => $this->signHook("__before_for_TestObject")
        );
        $resp = $this->request("/1/functions/TestObject/beforeSave", "POST",
                               array("object" => $obj));
        $obj2 = $resp;
        $this->assertEquals($obj["name"],     $obj2["name"]);
        $this->assertEquals(42,               $obj2["__testKey"]);
        $this->assertEquals("Pointer",        $obj2["likes"]["__type"]);
        $this->assertEquals("id002",          $obj2["likes"]["objectId"]);
    }

    public function testAfterSave() {
        $obj = array(
            "__type"    => "Object",
            "className" => "TestObject",
            "objectId"  => "id002",
            "name"      => "alice",
            "__after"   => $this->signHook("__after_for_TestObject")
        );
        $resp = $this->request("/1/functions/TestObject/afterSave", "POST",
                               array("object" => $obj));
        $this->assertEquals("ok", $resp["result"]);
    }

    public function testBeforeDelete() {
        $obj = array(
            "__type"    => "Object",
            "className" => "TestObject",
            "objectId"  => "id002",
            "name"      => "alice",
            "__before"  => $this->signHook("__before_for_TestObject")
        );
        $resp = $this->request("/1.1/functions/TestObject/beforeDelete", "POST",
                               array("object" => $obj));
        $this->assertEmpty($resp);
    }

    public function test_messageReceived() {
        $resp = $this->request("/1.1/functions/_messageReceived", "POST", array(
            "convId"   => '5789a33a1b8694ad267d8040',
            "fromPeer" => "Tom",
            "receipt"  => false,
            "toPeers"  => array("Jerry"),
            "content"  => '{"_lctext":"耗子，起床！","_lctype":-1}',
            "__sign"   => $this->signHook("_messageReceived")
        ));
        $this->assertEquals(false, $resp["result"]["drop"]);
    }

    public function testFunctionError() {
        try {
            $this->request("/1.1/functions/customError", "POST", array());
        } catch (CloudException $ex) {
            $this->assertEquals("My custom error.", $ex->getMessage());
            $this->assertEquals(1, $ex->getCode());
            $this->assertEquals(500, $ex->status);
        }
    }

}

