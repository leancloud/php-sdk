<?php

use LeanCloud\Operation\SetOperation;

class SetOperationTest extends PHPUnit_Framework_TestCase {
    public function testGetKey() {
        $op = new SetOperation("name", "alice");
        $this->assertEquals($op->getKey(), "name");

        $op = new SetOperation("story", "in wonderland");
        $this->assertEquals($op->getKey(), "story");
    }

    public function testApplyOperation() {
        $op  = new SetOperation("name", "alice");
        $val = $op->applyOn("alicia");
        $this->assertEquals($val, "alice");

        $val = $op->applyOn(42);
        $this->assertEquals($val, "alice");
    }
}

?>