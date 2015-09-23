<?php
namespace LeanCloud\Operation;

use LeanCloud\LeanClient;

class SetOperation implements IOperation {
    /**
     * The key of field the operation is about to apply.
     *
     * @var string
     */
    private $key;

    /**
     * The value of operation
     */
    private $value;

    public function __construct($key, $val) {
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
     * @return mixed
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
        return LeanClient::encode($this->value);
    }

    /**
     * Apply this operation on old value.
     *
     * @param mixed $oldval
     * @return mixed new value
     */
    public function applyOn($oldval) {
        return $this->value;
    }

    /**
     * Merge this operation into a (previous) operation.
     *
     * @param IOperation $prevOp
     * @return IOperation
     */
    public function mergeWith($prevOp) {}
}

?>