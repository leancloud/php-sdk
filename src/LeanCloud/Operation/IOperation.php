<?php
namespace LeanCloud\Operation;

/**
 * Operation Interface
 */
interface IOperation {
    /**
     * Encode to JSON represented operation
     *
     * @return array
     */
    public function encode();

    /**
     * Apply this operation on an old value
     *
     * @param mixed $oldval
     * @return mixed
     */
    public function applyOn($oldval);

    /**
     * Merge this operation into a (previous) operation
     *
     * @param IOperation $prevOp
     * @return IOperation
     */
    public function mergeWith($prevOp);
}

