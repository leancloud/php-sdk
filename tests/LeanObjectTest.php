<?php

use LeanCloud\LeanObject;
use LeanCloud\LeanClient;

class Movie extends LeanObject {
    protected static $leanClassName = "Movie";
}
Movie::registerClass();

class UnregisteredObject extends LeanObject{
    protected static $leanClassName = "UnregisteredObject";
}

class LeanObjectTest extends PHPUnit_Framework_TestCase {
    public function setUp() {
        LeanClient::initialize(
            getenv("LEANCLOUD_APP_ID"),
            getenv("LEANCLOUD_APP_KEY"),
            getenv("LEANCLOUD_APP_MASTER_KEY"));
        LeanClient::useRegion("CN");
    }

    public function testInitializePlainObjectWithoutName() {
        $this->setExpectedException("InvalidArgumentException",
                                    "className is invalid.");
        new LeanObject();
    }

    public function testInitializeSubClass() {
        $movie = new Movie();
        $this->assertTrue($movie instanceof Movie);
        $this->assertTrue($movie instanceof LeanObject);
    }

    public function testInitializePlainObject() {
        $movie = new LeanObject("Movie");
        $this->assertFalse($movie instanceof Movie);
        $this->assertTrue($movie instanceof LeanObject);
    }

    public function testSetGet() {
        $movie = new Movie();
        $movie->set("title", "How to train your dragon");
        $movie->set("release", 2010);
        $this->assertEquals($movie->get("title"), "How to train your dragon");
        $this->assertEquals($movie->get("release"), 2010);
    }

    public function testeSetPreservedField() {
        $setOps = array(
            "objectId"  => "32a",
            "createdAt" => "",
            "updatedAt" => "",
        );
        $movie = new Movie();

        forEach($setOps as $key => $val) {
            try { $movie->set($key, $val); }
            catch (ErrorException $exp) {
                continue;
            }
            $this->fail("Set on preserved key {$key} should throw exception.");
        }
    }

    public function testIncrement() {
        $movie = new Movie();
        $movie->set("score", 60);
        $this->assertEquals($movie->get("score"), 60);
        $movie->increment("score", 10);
        $this->assertEquals($movie->get("score"), 70);
        $movie->increment("score", -5);
        $this->assertEquals($movie->get("score"), 65);
    }

    public function testSaveNewObject() {
        $obj = new LeanObject("TestObject");
        $obj->set("name", "Alice in wonderland");
        $obj->set("score", 81);
        $obj->save();
        $this->assertNotEmpty($obj->getObjectId());
        $this->assertNotEmpty($obj->getCreatedAt());

        $this->assertEquals($obj->get("score"), 81);
    }

    public function testSaveFetchObject() {
        $obj = new LeanObject("TestObject");
        $obj->set("name", "Alice in wonderland");
        $obj->set("score", 81);
        $obj->save();
        $this->assertNotEmpty($obj->getObjectId());

        $id   = $obj->getObjectId();
        $obj2 = new LeanObject("TestObject", $id);
        $obj2->fetch();
        $this->assertEquals($obj2->get("name"), "Alice in wonderland");
        $this->assertEquals($obj2->get("score"), 81);
    }

    public function testSaveExistingObject() {
        $obj = new LeanObject("TestObject");
        $obj->set("foo", "bar");
        $obj->save();
        $this->assertNotEmpty($obj->getObjectId());
        $this->assertNotEmpty($obj->getCreatedAt());
        $obj->set("name", "Alice in wonderland");
        $obj->set("score", 81);
        $obj->save();
        $this->assertNotEmpty($obj->getUpdatedAt());

        $obj2 = new LeanObject("TestObject", $obj->getObjectId());
        $obj2->fetch();
        $this->assertEquals($obj2->get("name"), "Alice in wonderland");
        $this->assertEquals($obj2->get("score"), 81);
    }
}

?>