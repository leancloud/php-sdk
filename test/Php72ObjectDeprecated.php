<?php

use LeanCloud\Client;
use LeanCloud\Object;
use LeanCloud\LeanObject;
use PHPUnit\Framework\TestCase;

/**
 * Should only run this file on PHP < 7.2.
 *
 * This file will be run manually by `Makefile` or `.travis.yaml`, so it isn't has `Test` suffix。
 */

class Php72ObjectDeprecated extends TestCase {
    public static function setUpBeforeClass() {
        Client::initialize(
            getenv("LEANCLOUD_APP_ID"),
            getenv("LEANCLOUD_APP_KEY"),
            getenv("LEANCLOUD_APP_MASTER_KEY"));
    }

    public function testSaveObject() {
        $obj = new Object("TestObject");
        $obj->set("name", "Alice in wonderland");
        $obj->set("score", 81);
        $obj->save();

        $this->assertTrue($obj instanceof Object);
        $this->assertTrue($obj instanceof LeanObject);
        $this->assertNotEmpty($obj->getObjectId());

        LeanObject::destroyAll([$obj]);
    }
}
