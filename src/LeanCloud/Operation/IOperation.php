<?php
namespace LeanCloud\Operation;

/**
 * Operation Interface
 */
interface IOperation {
    /**
     * Encode to json represented operation.
     *
     * @return string json represented string
     */
    public function encode();

    /**
     * Apply this operation on an old value.
     *
     * @param mixed $oldval
     * @return mixed new value
     */
    public function applyOn($oldval);

    /**
     * Merge this operation into a (previous) operation.
     *
     * @param IOperation $prevOp
     * @return IOperation
     */
    public function mergeWith($prevOp);
}
?>