<?php

use LeanCloud\LeanClient;
use LeanCloud\LeanException;

class LeanClientTest extends PHPUnit_Framework_TestCase {
    public function setUp() {
        LeanClient::initialize(
            getenv("LEANCLOUD_APP_ID"),
            getenv("LEANCLOUD_APP_KEY"),
            getenv("LEANCLOUD_APP_MASTER_KEY"));
        LeanClient::useRegion("CN");
    }

    // TODO:
    // - test use master key
    // - test use production

    public function testGetAPIEndpoint() {
        LeanClient::useRegion("CN");
        $this->assertEquals(LeanClient::getAPIEndpoint(),
                            "https://api.leancloud.cn/1.1");
    }

    public function testUseInvalidRegion() {
        $this->setExpectedException("ErrorException", "Invalid API region");
        LeanClient::useRegion("cn-bla");
    }

    public function testUseRegion() {
        LeanClient::useRegion("US");
        $this->assertEquals(LeanClient::getAPIEndpoint(),
                            "https://us-api.leancloud.cn/1.1");
    }

    public function testRequestServerDate() {
        $data = LeanClient::request("GET", "/date", null);
        $this->assertEquals($data["__type"], "Date");
    }

    public function testRequestUnauthorized() {
        LeanClient::initialize(getenv("LEANCLOUD_APP_ID"), "invalid key", "invalid key");
        $this->setExpectedException("LeanCloud\LeanException", "Unauthorized");
        $data = LeanClient::request("POST",
                                    "/classes/TestObject",
                                    array("name" => "alice",
                                          "story" => "in wonderland"));
    }

    public function testRequestTestObject() {
        $data = LeanClient::request("POST",
                                    "/classes/TestObject",
                                    array(
                                        "name" => "alice",
                                        "story" => "in wonderland"));
        $this->assertArrayHasKey("objectId", $data);
        $id = $data["objectId"];

        $data = LeanClient::request("GET",
                                    "/classes/TestObject/" . $id,
                                    null);
        $this->assertEquals($data["name"], "alice");
    }

    public function testPostCreateTestObject() {
        $data = LeanClient::post("/classes/TestObject",
                                 array("name" => "alice",
                                       "story" => "in wonderland"));
        $this->assertArrayHasKey("objectId", $data);
    }

    public function testGetTestObject() {
        $data = LeanClient::post("/classes/TestObject",
                                 array("name" => "alice",
                                       "story" => "in wonderland"));
        $this->assertArrayHasKey("objectId", $data);
        $obj = LeanClient::get("/classes/TestObject/{$data['objectId']}");
        $this->assertEquals($obj["name"], "alice");
        $this->assertEquals($obj["story"], "in wonderland");
    }

    public function testUpdateTestObject() {
        $data = LeanClient::post("/classes/TestObject",
                                 array("name" => "alice",
                                       "story" => "in wonderland"));
        $this->assertArrayHasKey("objectId", $data);

        LeanClient::put("/classes/TestObject/{$data['objectId']}",
                         array("name" => "Hiccup",
                               "story" => "How to train your dragon"));

        $obj = LeanClient::get("/classes/TestObject/{$data['objectId']}");
        $this->assertEquals($obj["name"], "Hiccup");
        $this->assertEquals($obj["story"], "How to train your dragon");
    }

    public function testDeleteTestObject() {
        $data = LeanClient::post("/classes/TestObject",
                                 array("name" => "alice",
                                       "story" => "in wonderland"));
        $this->assertArrayHasKey("objectId", $data);

        LeanClient::delete("/classes/TestObject/{$data['objectId']}");

        $obj = LeanClient::get("/classes/TestObject/{$data['objectId']}");
        $this->assertEmpty($obj);
    }
}

?>
