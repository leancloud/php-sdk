<?php
namespace LeanCloud\Operation;

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
     * Encode to json represented operation.
     *
     * @return string json represented string
     */
    public function encode() {}

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
        throw new \ErrorException("Cannot increment on non-numeric value.");
    }

    /**
     * Merge this operation into a (previous) operation.
     *
     * @param IOperation $prevOp
     * @return IOperation
     */
    public function mergeWith(IOperation $prevOp) {}
}

?>