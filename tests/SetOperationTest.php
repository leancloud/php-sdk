<?php

use LeanCloud\Operation\SetOperation;
use LeanCloud\LeanClient;

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

    public function testOperationEncode() {
        $op  = new SetOperation("name", "alice");
        $this->assertEquals($op->encode(), "alice");
        $op  = new SetOperation("score", 70.0);
        $this->assertEquals($op->encode(), 70.0);

        $date = new DateTime();
        $op   = new SetOperation("released", $date);
        $this->assertEquals($op->encode()['__type'], "Date");
        $this->assertEquals($op->encode()['iso'],
                            LeanClient::formatDate($date));
    }

}

?>