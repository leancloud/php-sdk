<?php

use LeanCloud\LeanClient;
use LeanCloud\LeanObject;
use LeanCloud\LeanRelation;
use LeanCloud\LeanException;

class LeanClientTest extends PHPUnit_Framework_TestCase {
    public function setUp() {
        LeanClient::initialize(
            getenv("LC_APP_ID"),
            getenv("LC_APP_KEY"),
            getenv("LC_APP_MASTER_KEY"));
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
        LeanClient::delete("/classes/TestObject/{$data['objectId']}");
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

        LeanClient::delete("/classes/TestObject/{$data['objectId']}");
    }

    public function testPostCreateTestObject() {
        $data = LeanClient::post("/classes/TestObject",
                                 array("name" => "alice",
                                       "story" => "in wonderland"));
        $this->assertArrayHasKey("objectId", $data);

        LeanClient::delete("/classes/TestObject/{$data['objectId']}");
    }

    public function testGetTestObject() {
        $data = LeanClient::post("/classes/TestObject",
                                 array("name" => "alice",
                                       "story" => "in wonderland"));
        $this->assertArrayHasKey("objectId", $data);
        $obj = LeanClient::get("/classes/TestObject/{$data['objectId']}");
        $this->assertEquals($obj["name"], "alice");
        $this->assertEquals($obj["story"], "in wonderland");

        LeanClient::delete("/classes/TestObject/{$obj['objectId']}");
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

        LeanClient::delete("/classes/TestObject/{$obj['objectId']}");
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

    public function testDecodeDate() {
        $date = new DateTime();
        $type = array("__type" => "Date",
                      "iso" => LeanClient::formatDate($date));
        $this->assertEquals($date, LeanClient::decode($type));
    }

    public function testDecodeDateWithTimeZone() {
        $zones = array("Asia/Shanghai", "America/Los_Angeles",
                       "Asia/Tokyo", "Europe/London");
        forEach($zones as $zone) {
            $date = new DateTime("now", new DateTimeZone($zone));
            $type = array("__type" => "Date",
                          "iso" => LeanClient::formatDate($date));
            $this->assertEquals($date, LeanClient::decode($type));
        }
    }

    public function testDecodeRelation() {
        $type = array("__type" => "Relation",
                      "className" => "TestObject");
        $val  = LeanClient::decode($type);
        $this->assertTrue($val instanceof LeanRelation);
        $this->assertEquals("TestObject", $val->getTargetClassName());
    }

    public function testDecodePointer() {
        $type = array("__type" => "Pointer",
                      "className" => "TestObject",
                      "objectId" => "abc101");
        $val  = LeanClient::decode($type);

        $this->assertTrue($val instanceof LeanObject);
        $this->assertEquals("TestObject", $val->getClassName());
    }

    public function testDecodeObject() {
        $type = array("__type"    => "Object",
                      "className" => "TestObject",
                      "objectId"  => "abc101",
                      "name"      => "alice",
                      "tags"      => array("fiction", "bar"));
        $val  = LeanClient::decode($type);

        $this->assertTrue($val instanceof LeanObject);
        $this->assertEquals("TestObject", $val->getClassName());
        $this->assertEquals($type["name"], $val->get("name"));
        $this->assertEquals($type["tags"], $val->get("tags"));
    }
}

?>
