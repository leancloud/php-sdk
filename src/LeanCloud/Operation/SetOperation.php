<?php
namespace LeanCloud\Operation;

use LeanCloud\Client;

/**
 * Set operation
 *
 * Set field to value
 */

class SetOperation implements IOperation {
    /**
     * The key of field the operation is about to apply
     *
     * @var string
     */
    private $key;

    /**
     * The value of operation
     *
     * @var mixed
     */
    private $value;

    /**
     * Initialize operation
     *
     * @param string $key
     * @param mixed  $val
     */
    public function __construct($key, $val) {
        $this->key   = $key;
        $this->value = $val;
    }

    /**
     * Get key of field the operation applies to
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
     * Encode to JSON represented operation
     *
     * @return array
     */
    public function encode() {
        return Client::encode($this->value);
    }

    /**
     * Apply operation on old value and returns new one
     *
     * @param mixed $oldval
     * @return mixed
     */
    public function applyOn($oldval) {
        return $this->value;
    }

    /**
     * Merge this operation with (previous) operation
     *
     * @param  IOperation $prevOp
     * @return IOperation
     */
    public function mergeWith($prevOp) {
        return $this;
    }
}

