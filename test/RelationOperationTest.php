<?php

use LeanCloud\LeanObject;
use LeanCloud\Client;
use LeanCloud\Relation;
use LeanCloud\Operation\RelationOperation;
use PHPUnit\Framework\TestCase;

class RelationOperationTest extends TestCase {
    public static function setUpBeforeClass() {
        Client::initialize(
            getenv("LEANCLOUD_APP_ID"),
            getenv("LEANCLOUD_APP_KEY"),
            getenv("LEANCLOUD_APP_MASTER_KEY"));

    }

    public function testBothEmpty() {
        $this->setExpectedException("InvalidArgumentException",
                                    "Operands are empty.");
        $op = new RelationOperation("foo", array(), null);
    }

    public function testAddOpEncode() {
        $child1 = new LeanObject("TestObject", "ab123");
        $op = new RelationOperation("foo", array($child1), null);
        $out = $op->encode();
        $this->assertEquals("AddRelation", $out["__op"]);
        $this->assertEquals($child1->getPointer(), $out["objects"][0]);
    }

    public function testAddUnsavedObjects() {
        $child1 = new LeanObject("TestObject");
        $this->setExpectedException("RuntimeException",
                                    "Cannot add unsaved object to relation.");
        $op = new RelationOperation("foo", array($child1), null);
    }

    public function testAddDuplicateObjects() {
        $child1 = new LeanObject("TestObject", "ab123");
        $op = new RelationOperation("foo", array($child1, $child1), null);
        $out = $op->encode();
        $this->assertEquals("AddRelation", $out["__op"]);
        $this->assertEquals(1, count($out["objects"]));
        $this->assertEquals($child1->getPointer(), $out["objects"][0]);
    }

    public function testRemoveOpEncode() {
        $child1 = new LeanObject("TestObject", "ab123");
        $op = new RelationOperation("foo", null, array($child1));
        $out = $op->encode();
        $this->assertEquals("RemoveRelation", $out["__op"]);
        $this->assertEquals($child1->getPointer(), $out["objects"][0]);
    }

    public function testRemoveDuplicateObjects() {
        $child1 = new LeanObject("TestObject", "ab123");
        $op = new RelationOperation("foo", null, array($child1, $child1));
        $out = $op->encode();
        $this->assertEquals("RemoveRelation", $out["__op"]);
        $this->assertEquals(1, count($out["objects"]));
        $this->assertEquals($child1->getPointer(), $out["objects"][0]);
    }

    public function testAddWinsOverRemove() {
        $child1 = new LeanObject("TestObject", "ab101");
        $op = new RelationOperation("foo",
                                    array($child1),
                                    array($child1));
        $out = $op->encode();
        $this->assertEquals("AddRelation", $out["__op"]);
        $this->assertEquals(1, count($out["objects"]));
        $this->assertEquals($child1->getPointer(), $out["objects"][0]);
    }

    public function testAddAndRemove() {
        $child1 = new LeanObject("TestObject", "ab101");
        $child2 = new LeanObject("TestObject", "ab102");
        $child3 = new LeanObject("TestObject", "ab103");
        $op = new RelationOperation("foo",
                                    array($child1, $child2),
                                    array($child2, $child3));
        $out = $op->encode();
        $this->assertEquals("Batch", $out["__op"]);

        $adds = $out["ops"][0];
        $this->assertEquals("AddRelation", $adds["__op"]);
        $this->assertEquals(array($child1->getPointer(), $child2->getPointer()),
                            $adds["objects"]);
        $removes = $out["ops"][1];
        $this->assertEquals("RemoveRelation", $removes["__op"]);
        $this->assertEquals(array($child3->getPointer()),
                            $removes["objects"]);
    }

    public function testMultipleClassesNotAllowed() {
        $child1 = new LeanObject("TestObject",  "abc101");
        $child2 = new LeanObject("Test2Object", "bac102");
        $this->setExpectedException("RuntimeException",
                                    "LeanObject type incompatible with " .
                                    "relation.");
        $op = new RelationOperation("foo",
                                    array($child1),
                                    array($child2));
    }

    public function testApplyOperation() {
        $child1 = new LeanObject("TestObject",  "abc101");
        $op     = new RelationOperation("foo", array($child1), null);
        $parent = new LeanObject("Test2Object");
        $val    = $op->applyOn(null, $parent);
        $this->assertTrue($val instanceof Relation);
        $out    = $val->encode();
        $this->assertEquals("TestObject", $out["className"]);
    }

    public function testMergeWithNull() {
        $child1 = new LeanObject("TestObject",  "abc101");
        $op     = new RelationOperation("foo", array($child1), null);
        $op2    = $op->mergeWith(null);
        $this->assertTrue($op2 instanceof RelationOperation);
        $this->assertEquals($op->encode(), $op2->encode());
    }

    public function testMergeWithRelationOperation() {
        $child1 = new LeanObject("TestObject",  "abc101");
        $op     = new RelationOperation("foo", array($child1), null);

        $child2 = new LeanObject("TestObject",  "abc102");
        $op2    = new RelationOperation("foo", null, array($child2));

        $op3 = $op->mergeWith($op2);
        $this->assertTrue($op3 instanceof RelationOperation);
        $out = $op3->encode();

        // it adds child1, removes child2
        $this->assertEquals("Batch", $out["__op"]);
        $this->assertEquals(array($child1->getPointer()),
                            $out["ops"][0]["objects"]);
        $this->assertEquals(array($child2->getPointer()),
                            $out["ops"][1]["objects"]);
    }

}

