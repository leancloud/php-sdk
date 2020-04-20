<?php

use LeanCloud\Storage\SessionStorage;
use PHPUnit\Framework\TestCase;

class StorageTest extends TestCase {

    public function testSessionStorage() {
        $storage = new SessionStorage();

        $storage->set("null", null);
        $this->assertNull($storage->get("null"));

        $storage->set("bool", true);
        $this->assertTrue(true === $storage->get("bool"));

        $storage->set("int", 42);
        $this->assertEquals(42, $storage->get("int"));

        $storage->set("string", "bar");
        $this->assertEquals("bar", $storage->get("string"));

        $date = new DateTime();
        $storage->set("date", $date);
        $this->assertEquals($date, $storage->get("date"));

        $arr = array("a", "b");
        $storage->set("array", $arr);
        $this->assertEquals($arr, $storage->get("array"));
    }
}


