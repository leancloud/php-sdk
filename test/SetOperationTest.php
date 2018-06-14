<?php

use LeanCloud\Operation\SetOperation;
use LeanCloud\Operation\ArrayOperation;
use LeanCloud\Operation\DeleteOperation;
use LeanCloud\Operation\IncrementOperation;
use LeanCloud\Client;
use PHPUnit\Framework\TestCase;

class SetOperationTest extends TestCase {
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
        $out  = $op->encode();
        $this->assertEquals($out['__type'], "Date");
        $this->assertEquals($out['iso'],
                            Client::formatDate($date));
    }

    public function testMergeWithAnyOp() {
        $op = new SetOperation("name", "alice");

        $op2 = $op->mergeWith(null);
        $this->assertTrue($op2 instanceof SetOperation);
        $op2 = $op->mergeWith(new SetOperation("name", "jack"));
        $this->assertTrue($op2 instanceof SetOperation);
        $op2 = $op->mergeWith(new IncrementOperation("name", 1));
        $this->assertTrue($op2 instanceof SetOperation);
        $op2 = $op->mergeWith(new DeleteOperation("name"));
        $this->assertTrue($op2 instanceof SetOperation);
        $op2 = $op->mergeWith(new ArrayOperation("name", array("jack"), "Add"));
        $this->assertTrue($op2 instanceof SetOperation);
    }

}

