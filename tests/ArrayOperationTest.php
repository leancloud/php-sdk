<?php
use LeanCloud\Operation\ArrayOperation;
use LeanCloud\Operation\SetOperation;
use LeanCloud\Operation\DeleteOperation;

class ArrayOperationTest extends PHPUnit_Framework_TestCase {
    public function testInvalidOp() {
        $this->setExpectedException("ErrorException",
                                    "Operation on array not supported: Set");
        new ArrayOperation("tags", array("frontend", "javascript"), "Set");
    }

    public function testInvalidArray() {
        $this->setExpectedException("InvalidArgumentException",
                                    "Provided value is not array.");
        new ArrayOperation("tags", "frontend", "Add");
    }

    public function testOperationEncode() {
        $op = new ArrayOperation("tags", array("frontend", "javascript"), "Add");
        $this->assertEquals("Add", $op->encode()["__op"]);
        $this->assertEquals(array("frontend", "javascript"),
                            $op->encode()["objects"]);

        $op = new ArrayOperation("tags",
                                 array("frontend", "javascript"),
                                 "AddUnique");
        $this->assertEquals("AddUnique", $op->encode()["__op"]);
        $this->assertEquals(array("frontend", "javascript"),
                            $op->encode()["objects"]);

        $op = new ArrayOperation("tags",
                                 array("frontend", "javascript"),
                                 "Remove");
        $this->assertEquals("Remove", $op->encode()["__op"]);
        $this->assertEquals(array("frontend", "javascript"),
                            $op->encode()["objects"]);
    }

    public function testApplyAddToNonArray() {
        $op = new ArrayOperation("tags", array("frontend", "javascript"), "Add");
        $this->setExpectedException("ErrorException",
                                    "Array operation incompatible with ".
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
        $this->setExpectedException("ErrorException",
                                    "Array operation incompatible with ".
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
        $this->setExpectedException("ErrorException",
                                    "Array operation incompatible with ".
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
        $this->setExpectedException("ErrorException",
                                    "Array operation incompatible " .
                                    "with previous value.");
        $op2 = $op->mergeWith(new SetOperation("tags", 42));
    }

    public function testMergeWithNull() {
        $op = new ArrayOperation("tags", array("frontend", "javascript"), "Add");
        $op2 = $op->mergeWith(null);
        $this->assertTrue($op2 instanceof ArrayOperation);
        $this->assertEquals("Add", $op2->encode()["__op"]);
        $this->assertEquals(array("frontend", "javascript"),
                            $op2->encode()["objects"]);

        $op = new ArrayOperation("tags",
                                 array("frontend", "javascript"),
                                 "AddUnique");
        $op2 = $op->mergeWith(null);
        $this->assertTrue($op2 instanceof ArrayOperation);
        $this->assertEquals("AddUnique", $op2->encode()["__op"]);
        $this->assertEquals(array("frontend", "javascript"),
                            $op2->encode()["objects"]);

        $op = new ArrayOperation("tags",
                                 array("frontend", "javascript"),
                                 "Remove");
        $op2 = $op->mergeWith(null);
        $this->assertTrue($op2 instanceof ArrayOperation);
        $this->assertEquals("Remove", $op2->encode()["__op"]);
        $this->assertEquals(array("frontend", "javascript"),
                            $op2->encode()["objects"]);
    }

    public function testMergeToSetOperation() {
        $op = new ArrayOperation("tags",
                                 array("frontend", "javascript"),
                                 "Add");
        $op2 = $op->mergeWith(new SetOperation("tags", array("css")));
        $this->assertTrue($op2 instanceof SetOperation);
        $this->assertEquals(array("css", "frontend", "javascript"),
                            $op2->encode());

        $op = new ArrayOperation("tags",
                                 array("css", "frontend", "frontend"),
                                 "AddUnique");
        $op2 = $op->mergeWith(new SetOperation("tags", array("css")));
        $this->assertTrue($op2 instanceof SetOperation);
        $this->assertEquals(array("css", "frontend"),
                            $op2->encode());

        $op = new ArrayOperation("tags",
                                 array("frontend", "javascript"),
                                 "Remove");
        $op2 = $op->mergeWith(new SetOperation("tags",
                                               array("css", "frontend", "javascript")));
        $this->assertTrue($op2 instanceof SetOperation);
        $this->assertEquals(array("css"),
                            $op2->encode());
    }

    public function testMergeAddToAdd() {
        $op = new ArrayOperation("tags",
                                 array("frontend", "javascript"),
                                 "Add");
        $op2 = $op->mergeWith(new ArrayOperation("tags", array("foo"), "Add"));
        $this->assertTrue($op2 instanceof ArrayOperation);
        $this->assertEquals("Add", $op2->encode()["__op"]);
        $this->assertEquals(array("foo", "frontend", "javascript"),
                            $op2->encode()["objects"]);
    }

    public function testMergeAddUniqueToAddUnique() {
        $op = new ArrayOperation("tags",
                                 array("frontend", "javascript"),
                                 "AddUnique");
        $op2 = $op->mergeWith(new ArrayOperation("tags",
                                                 array("foo", "frontend"),
                                                 "AddUnique"));
        $this->assertTrue($op2 instanceof ArrayOperation);
        $this->assertEquals("AddUnique", $op2->encode()["__op"]);
        $this->assertEquals(array("foo", "frontend", "javascript"),
                            $op2->encode()["objects"]);
    }

    public function testMergeRemoveToRemove() {
        $op = new ArrayOperation("tags",
                                 array("frontend", "javascript"),
                                 "Remove");
        $op2 = $op->mergeWith(new ArrayOperation("tags",
                                                 array("foo"),
                                                 "Remove"));
        $this->assertTrue($op2 instanceof ArrayOperation);
        $this->assertEquals("Remove", $op2->encode()["__op"]);
        $this->assertEquals(array("foo", "frontend", "javascript"),
                            $op2->encode()["objects"]);
    }

    public function testMergeAddWithDelete() {
        $op = new ArrayOperation("tags",
                                 array("frontend", "javascript"),
                                 "Add");
        $op2 = $op->mergeWith(new DeleteOperation("tags"));
        $this->assertTrue($op2 instanceof SetOperation);
        $this->assertEquals(array("frontend", "javascript"),
                            $op2->encode());
    }

    public function testMergeAddUniqueWithDelete() {
        $op = new ArrayOperation("tags",
                                 array("frontend", "frontend", "javascript"),
                                 "AddUnique");
        $op2 = $op->mergeWith(new DeleteOperation("tags"));
        $this->assertTrue($op2 instanceof SetOperation);
        $this->assertEquals(array("frontend", "javascript"),
                            $op2->encode());
    }

    public function testMergeRemoveWithDelete() {
        $op = new ArrayOperation("tags",
                                 array("frontend", "javascript"),
                                 "Remove");
        $op2 = $op->mergeWith(new DeleteOperation("tags"));
        $this->assertTrue($op2 instanceof DeleteOperation);
    }
}
?>