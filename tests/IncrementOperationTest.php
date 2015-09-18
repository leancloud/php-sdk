<?php
use LeanCloud\Operation\IncrementOperation;

class IncrementOperationTest extends PHPUnit_Framework_TestCase {
    public function testGetKey() {
        $op = new IncrementOperation("score", 1);
        $this->assertEquals($op->getKey(), "score");
    }

    public function testIncrementNonNumericAmount() {
        $op  = new IncrementOperation("score", " 2");

        $this->setExpectedException("InvalidArgumentException",
                                    "Increment amount must be numeric.");
        $op  = new IncrementOperation("score", "a");
    }

    public function testApplyOperation() {
        $op  = new IncrementOperation("score", 20);
        $val = $op->applyOn(2);
        $this->assertEquals($val, 22);

        $val = $op->applyOn(22);
        $this->assertEquals($val, 42);

        $val = $op->applyOn("7");
        $this->assertEquals($val, 27);
    }

    public function testIncrementOnString() {
        $op = new IncrementOperation("score", 1);
        $this->setExpectedException("ErrorException",
                                    "Cannot increment on non-numeric value.");
        $op->applyOn("alice");
    }
}
?>