<?php

use LeanCloud\Client;
use LeanCloud\LeanObject;
use LeanCloud\Bytes;
use LeanCloud\User;
use LeanCloud\File;
use LeanCloud\Relation;
use LeanCloud\ACL;
use LeanCloud\GeoPoint;
use LeanCloud\CloudException;
use LeanCloud\Storage\SessionStorage;
use PHPUnit\Framework\TestCase;

class ClientTest extends TestCase {
    public function setUp() {
        Client::initialize(
            getenv("LEANCLOUD_APP_ID"),
            getenv("LEANCLOUD_APP_KEY"),
            getenv("LEANCLOUD_APP_MASTER_KEY"));

        Client::useMasterKey(false);
    }

    public function testAPIEndPoint() {
        $url = getenv("LEANCLOUD_API_SERVER");
        $this->assertEquals("{$url}/1.1", Client::getAPIEndPoint());

        Client::setServerURL("https://hello.api.lncld.net");
        $this->assertEquals("https://hello.api.lncld.net/1.1", Client::getAPIEndPoint());
        Client::setServerURL(null);

        $this->assertEquals("{$url}/1.1", Client::getAPIEndPoint());
    }

    public function testVerifyKey() {
        $result = Client::verifyKey(
            getenv("LEANCLOUD_APP_ID"),
            getenv("LEANCLOUD_APP_KEY")
        );
        $this->assertTrue($result);
    }

    # public function testVerifyKeyMaster() {
    #     $result = Client::verifyKey(
    #         getenv("LEANCLOUD_APP_ID"),
    #         getenv("LEANCLOUD_APP_MASTER_KEY") . ",master"
    #     );
    #     $this->assertTrue($result);
    # }

    public function testVerifySign() {
        $time = time();
        $sign = md5($time . getenv("LEANCLOUD_APP_KEY")) . ",{$time}";
        $result = Client::verifySign(getenv("LEANCLOUD_APP_ID"), $sign);
        $this->assertTrue($result);
    }

    public function testVerifySignMaster() {
        $time = time();
        $sign = md5($time . getenv("LEANCLOUD_APP_MASTER_KEY")) . ",{$time},master";
        $result = Client::verifySign(getenv("LEANCLOUD_APP_ID"), $sign);
        $this->assertTrue($result);
    }

    public function testUseMasterKeyByDefault() {
        Client::useMasterKey(true);
        $headers = Client::buildHeaders("token", null);
        $this->assertContains("master", $headers["X-LC-Sign"]);

        $headers = Client::buildHeaders("token", true);
        $this->assertContains("master", $headers["X-LC-Sign"]);

        $headers = Client::buildHeaders("token", false);
        $this->assertNotContains("master", $headers["X-LC-Sign"]);
    }

    public function testNotUseMasterKeyByDefault() {
        Client::useMasterKey(false);
        $headers = Client::buildHeaders("token", null);
        $this->assertNotContains("master", $headers["X-LC-Sign"]);

        $headers = Client::buildHeaders("token", false);
        $this->assertNotContains("master", $headers["X-LC-Sign"]);

        $headers = Client::buildHeaders("token", true);
        $this->assertContains("master", $headers["X-LC-Sign"]);
    }

    public function testRequestServerDate() {
        $data = Client::request("GET", "/date", null);
        $this->assertEquals($data["__type"], "Date");
    }

    public function testRequestUnauthorized() {
        Client::initialize(getenv("LEANCLOUD_APP_ID"),
                               "invalid key",
                               "invalid master key");
        $this->setExpectedException("LeanCloud\CloudException", "Unauthorized");
        $data = Client::request("POST",
                                    "/classes/TestObject",
                                    array("name" => "alice",
                                          "story" => "in wonderland"));
        Client::delete("/classes/TestObject/{$data['objectId']}");

    }

    public function testRequestTestObject() {
        $data = Client::request("POST",
                                    "/classes/TestObject",
                                    array(
                                        "name" => "alice",
                                        "story" => "in wonderland"));
        $this->assertArrayHasKey("objectId", $data);
        $id = $data["objectId"];

        $data = Client::request("GET",
                                    "/classes/TestObject/" . $id,
                                    null);
        $this->assertEquals($data["name"], "alice");

        Client::delete("/classes/TestObject/{$data['objectId']}");
    }

    public function testPostCreateTestObject() {
        $data = Client::post("/classes/TestObject",
                                 array("name" => "alice",
                                       "story" => "in wonderland"));
        $this->assertArrayHasKey("objectId", $data);

        Client::delete("/classes/TestObject/{$data['objectId']}");
    }

    public function testGetTestObject() {
        $data = Client::post("/classes/TestObject",
                                 array("name" => "alice",
                                       "story" => "in wonderland"));
        $this->assertArrayHasKey("objectId", $data);
        $obj = Client::get("/classes/TestObject/{$data['objectId']}");
        $this->assertEquals($obj["name"], "alice");
        $this->assertEquals($obj["story"], "in wonderland");

        Client::delete("/classes/TestObject/{$obj['objectId']}");
    }

    public function testUpdateTestObject() {
        $data = Client::post("/classes/TestObject",
                                 array("name" => "alice",
                                       "story" => "in wonderland"));
        $this->assertArrayHasKey("objectId", $data);

        Client::put("/classes/TestObject/{$data['objectId']}",
                         array("name" => "Hiccup",
                               "story" => "How to train your dragon"));

        $obj = Client::get("/classes/TestObject/{$data['objectId']}");
        $this->assertEquals($obj["name"], "Hiccup");
        $this->assertEquals($obj["story"], "How to train your dragon");

        Client::delete("/classes/TestObject/{$obj['objectId']}");
    }

    public function testDeleteTestObject() {
        $data = Client::post("/classes/TestObject",
                                 array("name" => "alice",
                                       "story" => "in wonderland"));
        $this->assertArrayHasKey("objectId", $data);

        Client::delete("/classes/TestObject/{$data['objectId']}");

        $obj = Client::get("/classes/TestObject/{$data['objectId']}");
        $this->assertEmpty($obj);
    }

    public function testDecodeDate() {
        $date = new DateTime();
        $type = array("__type" => "Date",
                      "iso" => Client::formatDate($date));
        $date2 = Client::decode($type, null);
        $this->assertEquals($date->getTimestamp(),
                            $date2->getTimestamp());
    }

    public function testDecodeDateWithTimeZone() {
        $zones = array("Asia/Shanghai", "America/Los_Angeles",
                       "Asia/Tokyo", "Europe/London");
        forEach($zones as $zone) {
            $date = new DateTime("now", new DateTimeZone($zone));
            $type = array("__type" => "Date",
                          "iso" => Client::formatDate($date));
            $date2 = Client::decode($type, null);
            $this->assertEquals($date->getTimestamp(),
                                $date2->getTimestamp());
        }
    }

    public function testDecodeRelation() {
        $type = array("__type" => "Relation",
                      "className" => "TestObject");
        $val  = Client::decode($type, null);
        $this->assertTrue($val instanceof Relation);
        $this->assertEquals("TestObject", $val->getTargetClassName());
    }

    public function testDecodePointer() {
        $type = array("__type" => "Pointer",
                      "className" => "TestObject",
                      "objectId" => "abc101");
        $val  = Client::decode($type, null);

        $this->assertTrue($val instanceof LeanObject);
        $this->assertEquals("TestObject", $val->getClassName());
    }

    public function testDecodeObject() {
        $type = array("__type"    => "Object",
                      "className" => "TestObject",
                      "objectId"  => "abc101",
                      "name"      => "alice",
                      "tags"      => array("fiction", "bar"));
        $val  = Client::decode($type, null);

        $this->assertTrue($val instanceof LeanObject);
        $this->assertEquals("TestObject", $val->getClassName());
        $this->assertEquals($type["name"], $val->get("name"));
        $this->assertEquals($type["tags"], $val->get("tags"));
    }

    public function testDecodeBytes() {
        $type = array("__type" => "Bytes",
                      "base64" => base64_encode("Hello"));
        $val = Client::decode($type, null);
        $this->assertTrue($val instanceof Bytes);
        $this->assertEquals(array(72, 101, 108, 108, 111),
                            $val->getByteArray());
    }

    public function testDecodeUserObject() {
        $type = array("__type"    => "Object",
                      "className" => "_User",
                      "objectId"  => "abc101",
                      "username"  => "alice",
                      "email"     => "alice@example.com");
        $val  = Client::decode($type, null);

        $this->assertTrue($val instanceof User);
        $this->assertEquals($type["objectId"], $val->getObjectId());
        $this->assertEquals($type["username"], $val->getUsername());
        $this->assertEquals($type["email"], $val->getEmail());
    }

    public function testDecodeUserPointer() {
        $type = array("__type"    => "Pointer",
                      "className" => "_User",
                      "objectId"  => "abc101");
        $val  = Client::decode($type, null);

        $this->assertTrue($val instanceof User);
        $this->assertEquals($type["objectId"], $val->getObjectId());
    }

    public function testDecodeFile() {
        $type = array("__type"    => "File",
                      "objectId"  => "abc101",
                      "name"      => "favicon.ico",
                      "url" => "https://leancloud.cn/favicon.ico");
        $val  = Client::decode($type, null);

        $this->assertTrue($val instanceof File);
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
        $val  = Client::decode($type, 'ACL');
        $this->assertTrue($val instanceof ACL);
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
        $val = Client::decode($type, null);
        $this->assertTrue($val instanceof LeanObject);
        $this->assertEquals('alice', $val->get('name'));
        $this->assertTrue($val->getACL() instanceof ACL);

        $parent = $val->get("parent");
        $this->assertTrue($parent instanceof LeanObject);
        $this->assertEquals('jill', $parent->get('name'));
        $this->assertTrue($parent->getACL() instanceof ACL);
    }

    /*
     * Fix not type-safe string comparison
     *
     * e.g  `0 == 'ACL'`
     *
     * @link bug #43: https://github.com/leancloud/php-sdk/issues/43
     */
    public function testDecodeIndexedArrayValue() {
        $val = Client::decode(array(
            '__type' => 'Pointer',
            'className' => 'TestObject',
            'objectId' => '5682bd'
        ), 0);
        $this->assertTrue($val instanceof LeanObject);
    }

    public function testDecodeGeoPoint() {
        $type = array(
            '__type' => 'GeoPoint',
            'latitude' => 39.9,
            'longitude' => 116.4
        );
        $val = Client::decode($type, null);
        $this->assertTrue($val instanceof GeoPoint);
        $this->assertEquals(39.9, $val->getLatitude());
        $this->assertEquals(116.4, $val->getLongitude());
    }

    public function testEncodeRelation() {
        $a = new LeanObject("TestObject", "id001");
        $rel = $a->getRelation("likes");
        $out = Client::encode($rel);
        $this->assertEquals("Relation",
                            $out["__type"]);
    }

    public function testEncodeObjectToJSON() {
        $a = new LeanObject("TestObject", "id001");
        $b = new LeanObject("TestObject", "id002");
        $a->set("name", "A");
        $b->set("name", "B");
        $a->addIn("likes", $b);
        $jsonA = Client::encode($a, "toJSON");
        $jsonB = $jsonA["likes"][0];
        // top level object A will be encoded as literal json
        $this->assertEquals("A",          $jsonA["name"]);
        $this->assertEquals("id001",      $jsonA["objectId"]);
        $this->assertEquals("B",          $jsonB["name"]);
        $this->assertEquals("id002",      $jsonB["objectId"]);
        $this->assertEquals("Object",     $jsonB["__type"]);
        $this->assertEquals("TestObject", $jsonB["className"]);

        $this->assertArrayNotHasKey("__type",    $jsonA);
        $this->assertArrayNotHasKey("className", $jsonA);
    }

    public function testEncodeObjectToFullJSON() {
        $a = new LeanObject("TestObject", "id001");
        $b = new LeanObject("TestObject", "id002");
        $a->set("name", "A");
        $b->set("name", "B");
        $a->addIn("likes", $b);
        $jsonA = Client::encode($a, "toFullJSON");
        $jsonB = $jsonA["likes"][0];
        $this->assertEquals("A",          $jsonA["name"]);
        $this->assertEquals("id001",      $jsonA["objectId"]);
        $this->assertEquals("Object",     $jsonA["__type"]);
        $this->assertEquals("TestObject", $jsonA["className"]);
        $this->assertEquals("B",          $jsonB["name"]);
        $this->assertEquals("id002",      $jsonB["objectId"]);
        $this->assertEquals("Object",     $jsonB["__type"]);
        $this->assertEquals("TestObject", $jsonB["className"]);
    }

    public function testEncodeCircularObjectAsPointer() {
        $a = new LeanObject("TestObject", "id001");
        $b = new LeanObject("TestObject", "id002");
        $c = new LeanObject("TestObject", "id003");
        $a->set("name", "A");
        $b->set("name", "B");
        $c->set("name", "C");
        $a->addIn("likes", $b);
        $b->addIn("likes", $c);
        $c->addIn("likes", $a);
        $jsonA = Client::encode($a, "toFullJSON");
        $jsonB = $jsonA["likes"][0];
        $jsonC = $jsonB["likes"][0];

        $this->assertEquals("Object",  $jsonA["__type"]);
        $this->assertEquals("Object",  $jsonB["__type"]);
        $this->assertEquals("Object",  $jsonC["__type"]);
        $this->assertEquals("Pointer", $jsonC["likes"][0]["__type"]);
    }

    public function testEncodePointerObject() {
        $json = array(
            "__type" => "Object",
            "objectId" => "id001",
            "className" => "TestObject",
            "name" => "A",
            "likes" => array(
                "__type" => "Pointer",
                "objectId" => "id002",
                "className" => "TestObject"
            )
        );
        $a = Client::decode($json, null);
        $this->assertTrue($a instanceof LeanObject);
        $this->assertTrue($a->get("likes") instanceof LeanObject);

        $out = $a->toFullJSON();
        $this->assertEquals("A", $out["name"]);
        $this->assertEquals("Pointer", $out["likes"]["__type"]);
        $this->assertEquals("TestObject", $out["likes"]["className"]);
    }

}


