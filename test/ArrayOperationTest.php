<?php

use LeanCloud\Operation\ArrayOperation;
use LeanCloud\Operation\SetOperation;
use LeanCloud\Operation\DeleteOperation;
use PHPUnit\Framework\TestCase;

class ArrayOperationTest extends TestCase {
    public function testInvalidOp() {
        $this->setExpectedException("InvalidArgumentException",
                                    "Operation on array not supported: Set.");
        new ArrayOperation("tags", array("frontend", "javascript"), "Set");
    }

    public function testInvalidArray() {
        $this->setExpectedException("InvalidArgumentException",
                                    "Operand must be array.");
        new ArrayOperation("tags", "frontend", "Add");
    }

    public function testOperationEncode() {
        $op  = new ArrayOperation("tags", array("frontend", "javascript"), "Add");
        $out = $op->encode();
        $this->assertEquals("Add", $out["__op"]);
        $this->assertEquals(array("frontend", "javascript"),
                            $out["objects"]);

        $op = new ArrayOperation("tags",
                                 array("frontend", "javascript"),
                                 "AddUnique");
        $out = $op->encode();
        $this->assertEquals("AddUnique", $out["__op"]);
        $this->assertEquals(array("frontend", "javascript"),
                            $out["objects"]);

        $op = new ArrayOperation("tags",
                                 array("frontend", "javascript"),
                                 "Remove");
        $out = $op->encode();
        $this->assertEquals("Remove", $out["__op"]);
        $this->assertEquals(array("frontend", "javascript"),
                            $out["objects"]);
    }

    public function testApplyAddToNonArray() {
        $op = new ArrayOperation("tags", array("frontend", "javascript"), "Add");
        $this->setExpectedException("RuntimeException",
                                    "Operation incompatible with ".
                                    "previous value.");
        $op->applyOn(42);
    }

    public function testApplyAddToNull() {
        $op  = new ArrayOperation("tags", array("frontend", "javascript"), "Add");
        $val = $op->applyOn(null);
        $this->assertEquals(array("frontend", "javascript"), $val);
    }

    public function testApplyAddToArray() {
        $op  = new ArrayOperation("tags", array("frontend", "javascript"), "Add");
        $val = $op->applyOn(array("css"));
        $this->assertEquals(array("css", "frontend", "javascript"), $val);

        $val = $op->applyOn(array());
        $this->assertEquals(array("frontend", "javascript"), $val);
    }

    public function testApplyAddUniqueToNonArray() {
        $op = new ArrayOperation("tags", array("frontend", "javascript"),
                                 "AddUnique");
        $this->setExpectedException("RuntimeException",
                                    "Operation incompatible with ".
                                    "previous value.");
        $op->applyOn(42);
    }

    public function testApplyAddUniqueToNull() {
        $op  = new ArrayOperation("tags", array("foo", "foo",
                                                42, 42,
                                                1.1, 1.1,
                                                array("a", "b"), array("a", "b")),
                                  "AddUnique");
        $val = $op->applyOn(null);
        $this->assertEquals(array("foo", 42, 1.1, array("a", "b")), $val);
    }

    public function testApplyAddUniqueToArray() {
        $op  = new ArrayOperation("tags", array("foo", "foo",
                                                42, 42,
                                                1.1, 1.1,
                                                array("a", "b"), array("a", "b")),
                                  "AddUnique");
        $val = $op->applyOn(array());
        $this->assertEquals(array("foo", 42, 1.1, array("a", "b")), $val);

        $op  = new ArrayOperation("tags", array("foo",
                                                42,
                                                1.1,
                                                array("a", "b")),
                                  "AddUnique");
        $val = $op->applyOn(array("foo", 42, 1.1, array("a", "b")));
        $this->assertEquals(array("foo", 42, 1.1, array("a", "b")), $val);
    }

    public function testApplyRemoveToNonArray() {
        $op = new ArrayOperation("tags", array("frontend", "javascript"),
                                 "Remove");
        $this->setExpectedException("RuntimeException",
                                    "Operation incompatible with ".
                                    "previous value.");
        $op->applyOn(1.1);
    }

    public function testApplyRemoveToNull() {
        $op = new ArrayOperation("tags", array("frontend", "javascript"),
                                 "Remove");
        $val = $op->applyOn(null);
        $this->assertEmpty($val);
    }

    public function testApplyRemoveToArray() {
        $op = new ArrayOperation("tags", array("frontend", "javascript"),
                                 "Remove");
        $val = $op->applyOn(array());
        $this->assertEmpty($val);

        $val = $op->applyOn(array("frontend", "foo", "bar"));
        $this->assertEquals(array("foo", "bar"), $val);

        $op = new ArrayOperation("tags", array(42, 1.1, array("a", "b")),
                                 "Remove");
        $val = $op->applyOn(array("frontend", 42, 1.1, array("a", "b")));
        $this->assertEquals(array("frontend"), $val);
    }

    public function testMergeToIncompatibleSetOperation() {
        $op = new ArrayOperation("tags", array("frontend", "javascript"), "Add");
        $this->setExpectedException("RuntimeException",
                                    "Operation incompatible " .
                                    "with previous value.");
        $op2 = $op->mergeWith(new SetOperation("tags", 42));
    }

    public function testMergeWithNull() {
        $op = new ArrayOperation("tags", array("frontend", "javascript"), "Add");
        $op2 = $op->mergeWith(null);
        $this->assertTrue($op2 instanceof ArrayOperation);
        $out = $op2->encode();
        $this->assertEquals("Add", $out["__op"]);
        $this->assertEquals(array("frontend", "javascript"),
                            $out["objects"]);

        $op = new ArrayOperation("tags",
                                 array("frontend", "javascript"),
                                 "AddUnique");
        $op2 = $op->mergeWith(null);
        $out = $op2->encode();
        $this->assertTrue($op2 instanceof ArrayOperation);
        $this->assertEquals("AddUnique", $out["__op"]);
        $this->assertEquals(array("frontend", "javascript"),
                            $out["objects"]);

        $op = new ArrayOperation("tags",
                                 array("frontend", "javascript"),
                                 "Remove");
        $op2 = $op->mergeWith(null);
        $out = $op2->encode();
        $this->assertTrue($op2 instanceof ArrayOperation);
        $this->assertEquals("Remove", $out["__op"]);
        $this->assertEquals(array("frontend", "javascript"),
                            $out["objects"]);
    }

    public function testMergeToSetOperation() {
        $op = new ArrayOperation("tags",
                                 array("frontend", "javascript"),
                                 "Add");
        $op2 = $op->mergeWith(new SetOperation("tags", array("css")));
        $this->assertTrue($op2 instanceof SetOperation);
        $out = $op2->encode();
        $this->assertEquals(array("css", "frontend", "javascript"),
                            $out);

        $op = new ArrayOperation("tags",
                                 array("css", "frontend", "frontend"),
                                 "AddUnique");
        $op2 = $op->mergeWith(new SetOperation("tags", array("css")));
        $this->assertTrue($op2 instanceof SetOperation);
        $out = $op2->encode();
        $this->assertEquals(array("css", "frontend"),
                            $out);

        $op = new ArrayOperation("tags",
                                 array("frontend", "javascript"),
                                 "Remove");
        $op2 = $op->mergeWith(new SetOperation("tags",
                                               array("css", "frontend", "javascript")));
        $this->assertTrue($op2 instanceof SetOperation);
        $out = $op2->encode();
        $this->assertEquals(array("css"),
                            $out);
    }

    public function testMergeAddToAdd() {
        $op = new ArrayOperation("tags",
                                 array("frontend", "javascript"),
                                 "Add");
        $op2 = $op->mergeWith(new ArrayOperation("tags", array("foo"), "Add"));
        $this->assertTrue($op2 instanceof ArrayOperation);
        $out = $op2->encode();
        $this->assertEquals("Add", $out["__op"]);
        $this->assertEquals(array("foo", "frontend", "javascript"),
                            $out["objects"]);
    }

    public function testMergeAddUniqueToAddUnique() {
        $op = new ArrayOperation("tags",
                                 array("frontend", "javascript"),
                                 "AddUnique");
        $op2 = $op->mergeWith(new ArrayOperation("tags",
                                                 array("foo", "frontend"),
                                                 "AddUnique"));
        $this->assertTrue($op2 instanceof ArrayOperation);
        $out = $op2->encode();
        $this->assertEquals("AddUnique", $out["__op"]);
        $this->assertEquals(array("foo", "frontend", "javascript"),
                            $out["objects"]);
    }

    public function testMergeRemoveToRemove() {
        $op = new ArrayOperation("tags",
                                 array("frontend", "javascript"),
                                 "Remove");
        $op2 = $op->mergeWith(new ArrayOperation("tags",
                                                 array("foo"),
                                                 "Remove"));
        $this->assertTrue($op2 instanceof ArrayOperation);
        $out = $op2->encode();
        $this->assertEquals("Remove", $out["__op"]);
        $this->assertEquals(array("foo", "frontend", "javascript"),
                            $out["objects"]);
    }

    public function testMergeAddWithDelete() {
        $op = new ArrayOperation("tags",
                                 array("frontend", "javascript"),
                                 "Add");
        $op2 = $op->mergeWith(new DeleteOperation("tags"));
        $this->assertTrue($op2 instanceof SetOperation);
        $out = $op2->encode();
        $this->assertEquals(array("frontend", "javascript"),
                            $out);
    }

    public function testMergeAddUniqueWithDelete() {
        $op = new ArrayOperation("tags",
                                 array("frontend", "frontend", "javascript"),
                                 "AddUnique");
        $op2 = $op->mergeWith(new DeleteOperation("tags"));
        $this->assertTrue($op2 instanceof SetOperation);
        $out = $op2->encode();
        $this->assertEquals(array("frontend", "javascript"),
                            $out);
    }

    public function testMergeRemoveWithDelete() {
        $op = new ArrayOperation("tags",
                                 array("frontend", "javascript"),
                                 "Remove");
        $op2 = $op->mergeWith(new DeleteOperation("tags"));
        $this->assertTrue($op2 instanceof DeleteOperation);
    }
}

