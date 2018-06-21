<?php

use LeanCloud\Operation\DeleteOperation;
use LeanCloud\Operation\SetOperation;
use LeanCloud\Operation\IncrementOperation;
use LeanCloud\Operation\ArrayOperation;
use PHPUnit\Framework\TestCase;

class DeleteOperationTest extends TestCase {
    public function testOperationEncode() {
        $op = new DeleteOperation("tags");
        $out = $op->encode();
        $this->assertEquals("Delete", $out["__op"]);
    }

    public function testApplyOperation() {
        $op = new DeleteOperation("tags");
        $this->assertNull($op->applyOn());
    }

    public function testMergeWithAnyOp() {
        $op  = new DeleteOperation("tags");

        $op2 = $op->mergeWith(null);
        $this->assertTrue($op2 instanceof DeleteOperation);

        $op2 = $op->mergeWith(new SetOperation("tags", "foo"));
        $this->assertTrue($op2 instanceof DeleteOperation);

        $op2 = $op->mergeWith(new DeleteOperation("tags"));
        $this->assertTrue($op2 instanceof DeleteOperation);

        $op2 = $op->mergeWith(new IncrementOperation("tags", 1));
        $this->assertTrue($op2 instanceof DeleteOperation);

        $op2 = $op->mergeWith(new ArrayOperation("tags",
                                                 array("frontend"),
                                                 "Add"));
        $this->assertTrue($op2 instanceof DeleteOperation);
    }
}

