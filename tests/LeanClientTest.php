<?php

use LeanCloud\LeanClient;
use LeanCloud\LeanObject;
use LeanCloud\LeanBytes;
use LeanCloud\LeanUser;
use LeanCloud\LeanFile;
use LeanCloud\LeanRelation;
use LeanCloud\LeanACL;
use LeanCloud\GeoPoint;
use LeanCloud\CloudException;
use LeanCloud\Storage\SessionStorage;

class LeanClientTest extends PHPUnit_Framework_TestCase {
    public function setUp() {
        LeanClient::initialize(
            getenv("LC_APP_ID"),
            getenv("LC_APP_KEY"),
            getenv("LC_APP_MASTER_KEY"));
        LeanClient::useRegion(getenv("LC_API_REGION"));
        LeanClient::useMasterKey(false);
    }

    public function testGetAPIEndpoint() {
        LeanClient::useRegion("CN");
        $this->assertEquals(LeanClient::getAPIEndpoint(),
                            "https://api.leancloud.cn/1.1");
    }

    public function testUseInvalidRegion() {
        $this->setExpectedException("RuntimeException", "Invalid API region");
        LeanClient::useRegion("cn-bla");
    }

    public function testUseRegion() {
        LeanClient::useRegion("US");
        $this->assertEquals(LeanClient::getAPIEndpoint(),
                            "https://us-api.leancloud.cn/1.1");
    }

    public function testUseMasterKeyByDefault() {
        LeanClient::useMasterKey(true);
        $headers = LeanClient::buildHeaders("token", null);
        $this->assertContains("master", $headers["X-LC-Sign"]);

        $headers = LeanClient::buildHeaders("token", true);
        $this->assertContains("master", $headers["X-LC-Sign"]);

        $headers = LeanClient::buildHeaders("token", false);
        $this->assertNotContains("master", $headers["X-LC-Sign"]);
    }

    public function testNotUseMasterKeyByDefault() {
        LeanClient::useMasterKey(false);
        $headers = LeanClient::buildHeaders("token", null);
        $this->assertNotContains("master", $headers["X-LC-Sign"]);

        $headers = LeanClient::buildHeaders("token", false);
        $this->assertNotContains("master", $headers["X-LC-Sign"]);

        $headers = LeanClient::buildHeaders("token", true);
        $this->assertContains("master", $headers["X-LC-Sign"]);
    }

    public function testRequestServerDate() {
        $data = LeanClient::request("GET", "/date", null);
        $this->assertEquals($data["__type"], "Date");
    }

    public function testRequestUnauthorized() {
        LeanClient::initialize(getenv("LC_APP_ID"),
                               "invalid key",
                               "invalid master key");
        $this->setExpectedException("LeanCloud\CloudException", "Unauthorized");
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
        $this->assertEquals($date, LeanClient::decode($type, null));
    }

    public function testDecodeDateWithTimeZone() {
        $zones = array("Asia/Shanghai", "America/Los_Angeles",
                       "Asia/Tokyo", "Europe/London");
        forEach($zones as $zone) {
            $date = new DateTime("now", new DateTimeZone($zone));
            $type = array("__type" => "Date",
                          "iso" => LeanClient::formatDate($date));
            $this->assertEquals($date, LeanClient::decode($type, null));
        }
    }

    public function testDecodeRelation() {
        $type = array("__type" => "Relation",
                      "className" => "TestObject");
        $val  = LeanClient::decode($type, null);
        $this->assertTrue($val instanceof LeanRelation);
        $this->assertEquals("TestObject", $val->getTargetClassName());
    }

    public function testDecodePointer() {
        $type = array("__type" => "Pointer",
                      "className" => "TestObject",
                      "objectId" => "abc101");
        $val  = LeanClient::decode($type, null);

        $this->assertTrue($val instanceof LeanObject);
        $this->assertEquals("TestObject", $val->getClassName());
    }

    public function testDecodeObject() {
        $type = array("__type"    => "Object",
                      "className" => "TestObject",
                      "objectId"  => "abc101",
                      "name"      => "alice",
                      "tags"      => array("fiction", "bar"));
        $val  = LeanClient::decode($type, null);

        $this->assertTrue($val instanceof LeanObject);
        $this->assertEquals("TestObject", $val->getClassName());
        $this->assertEquals($type["name"], $val->get("name"));
        $this->assertEquals($type["tags"], $val->get("tags"));
    }

    public function testDecodeBytes() {
        $type = array("__type" => "Bytes",
                      "base64" => base64_encode("Hello"));
        $val = LeanClient::decode($type, null);
        $this->assertTrue($val instanceof LeanBytes);
        $this->assertEquals(array(72, 101, 108, 108, 111),
                            $val->getByteArray());
    }

    public function testDecodeUserObject() {
        $type = array("__type"    => "Object",
                      "className" => "_User",
                      "objectId"  => "abc101",
                      "username"  => "alice",
                      "email"     => "alice@example.com");
        $val  = LeanClient::decode($type, null);

        $this->assertTrue($val instanceof LeanUser);
        $this->assertEquals($type["objectId"], $val->getObjectId());
        $this->assertEquals($type["username"], $val->getUsername());
        $this->assertEquals($type["email"], $val->getEmail());
    }

    public function testDecodeUserPointer() {
        $type = array("__type"    => "Pointer",
                      "className" => "_User",
                      "objectId"  => "abc101");
        $val  = LeanClient::decode($type, null);

        $this->assertTrue($val instanceof LeanUser);
        $this->assertEquals($type["objectId"], $val->getObjectId());
    }

    public function testDecodeFile() {
        $type = array("__type"    => "File",
                      "objectId"  => "abc101",
                      "name"      => "favicon.ico",
                      "url" => "https://leancloud.cn/favicon.ico");
        $val  = LeanClient::decode($type, null);

        $this->assertTrue($val instanceof LeanFile);
        $this->assertEquals($type["objectId"], $val->getObjectId());
        $this->assertEquals($type["name"], $val->getName());
        $this->assertEquals($type["url"],  $val->getUrl());
    }

    public function testDecodeACL() {
        $type = array("*"         => array("read" => true,
                                           "write" => false),
                      "user123"    => array("write" => true),
                      "role:admin" => array("write" => true)
        );
        $val  = LeanClient::decode($type, 'ACL');
        $this->assertTrue($val instanceof LeanACL);
        $this->assertTrue($val->getPublicReadAccess());
        $this->assertFalse($val->getPublicWriteAccess());
        $this->assertTrue($val->getRoleWriteAccess("admin"));
        $this->assertTrue($val->getWriteAccess("user123"));
    }

    public function testDecodeRecursiveObjectWithACL() {
        $acl = array(
            'id102' => array(
                'write' => true
            )
        );
        $type = array(
            '__type' => 'Object',
            'className' => 'TestObject',
            'objectId' => 'id101',
            'name' => 'alice',
            'ACL' => $acl,
            'parent' => array(
                '__type' => 'Object',
                'className' => 'TestObject',
                'objectId'  => 'id102',
                'name' => 'jill',
                'ACL' => $acl
            )
        );
        $val = LeanClient::decode($type, null);
        $this->assertTrue($val instanceof LeanObject);
        $this->assertEquals('alice', $val->get('name'));
        $this->assertTrue($val->getACL() instanceof LeanACL);

        $parent = $val->get("parent");
        $this->assertTrue($parent instanceof LeanObject);
        $this->assertEquals('jill', $parent->get('name'));
        $this->assertTrue($parent->getACL() instanceof LeanACL);
    }

    public function testDecodeGeoPoint() {
        $type = array(
            '__type' => 'GeoPoint',
            'latitude' => 39.9,
            'longitude' => 116.4
        );
        $val = LeanClient::decode($type, null);
        $this->assertTrue($val instanceof GeoPoint);
        $this->assertEquals(39.9, $val->getLatitude());
        $this->assertEquals(116.4, $val->getLongitude());
    }
}


