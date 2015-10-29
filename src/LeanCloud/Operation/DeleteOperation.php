<?php
namespace LeanCloud\Operation;

class DeleteOperation implements IOperation {
    /**
     * The key of field the operation is about to apply
     *
     * @var string
     */
    private $key;

    /**
     * Initialize operation
     *
     * @param string $key Key of field to delete.
     */
    public function __construct($key) {
        $this->key = $key;
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
     * Encode to JSON represented operation
     *
     * @return array
     */
    public function encode() {
        return array("__op" => "Delete");
    }

    /**
     * Apply this operation on an old value.
     *
     * @param  mixed $oldval
     * @return null
     */
    public function applyOn($oldval=null) {
        return null;
    }

    /**
     * Merge this operation with (previous) operation.
     *
     * @param  IOperation $prevOp
     * @return IOperation
     */
    public function mergeWith($prevOp) {
        return $this;
    }
}

