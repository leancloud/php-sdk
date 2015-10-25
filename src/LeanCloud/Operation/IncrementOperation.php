<?php
namespace LeanCloud\Operation;

use LeanCloud\Operation\SetOperation;

class IncrementOperation implements IOperation {
    /**
     * The key of field the operation applies to.
     *
     * @var string
     */
    private $key;

    /**
     * The value of operation
     */
    private $value;

    public function __construct($key, $val) {
        if (!is_numeric($val)) {
            throw new \InvalidArgumentException("Increment amount must be numeric.");
        }
        $this->key   = $key;
        $this->value = $val;
    }

    /**
     * Get key of field the operation applies to.
     *
     * @return string
     */
    public function getKey() {
        return $this->key;
    }

    /**
     * Get value of operation
     *
     * @return number
     */
    public function getValue() {
        return $this->value;
    }

    /**
     * Encode to json represented operation.
     *
     * @return string json represented string
     */
    public function encode() {
        return array("__op" => "Increment",
                     "amount" => $this->value);
    }

    /**
     * Apply this operation on old value.
     *
     * @param mixed $oldval
     * @return mixed new value
     */
    public function applyOn($oldval) {
        $oldval = is_null($oldval) ? 0 : $oldval;
        if (is_numeric($oldval)) {
            return $this->value + $oldval;
        }
        throw new \RuntimeException("Operation incompatible with previous value.");
    }

    /**
     * Merge this operation into a (previous) operation.
     *
     * @param IOperation $prevOp
     * @return IOperation
     */
    public function mergeWith($prevOp) {
        if (!$prevOp) {
            return $this;
        } else if ($prevOp instanceof SetOperation) {
            return new SetOperation($this->getKey(),
                                    $this->applyOn($prevOp->getValue()));
        } else if ($prevOp instanceof IncrementOperation) {
            return new IncrementOperation($this->getKey(),
                                          $this->applyOn($prevOp->getValue()));
        } else if ($prevOp instanceof DeleteOperation){
            return new SetOperation($this->getKey(), $this->getValue());
        } else {
            throw new \RuntimeException("Operation incompatible with previous one.");
        }
    }
}

