<?php

use LeanCloud\LeanObject;
use LeanCloud\LeanRelation;

class LeanRelationTest extends PHPUnit_Framework_TestCase {
    public function testRelationEncode() {
        $obj = new LeanObject("TestObject");
        $rel = $obj->getRelation("likes");
        $this->assertEquals("Relation", $rel->encode()["__type"]);
    }

    public function testRelationClassEncode() {
        $obj = new LeanObject("TestObject");
        $rel = $obj->getRelation("likes");
        $this->assertEquals("Relation", $rel->encode()["__type"]);

        $child1 = new LeanObject("Test2Object", "abc101");
        $rel->add($child1);
        $this->assertEquals("Test2Object", $rel->encode()["className"]);
    }
}
?>