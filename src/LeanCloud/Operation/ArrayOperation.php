<?php
namespace LeanCloud\Operation;

use LeanCloud\Client;
use LeanCloud\LeanObject;
use LeanCloud\Operation\SetOperation;
use LeanCloud\Operation\DeleteOperation;

/**
 * Array opertion - Add, Remove, AddUnique
 *
 */
class ArrayOperation implements IOperation {
    /**
     * The key of field the operation is about to apply
     *
     * @var string
     */
    private $key;

    /**
     * The value of operation
     * @var mixed
     */
    private $value;

    /**
     * The operation type
     *
     * @var string
     */
    private $opType;

    /**
     * Initialize an ArrayOperation
     *
     * @param string $key     Field key
     * @param array  $val     Array of values to add or remove
     * @param string $opType  One of Add, AddUnique, Remove
     * @throws RuntimeException, InvalidArgumentException
     */
    public function __construct($key, $val, $opType) {
        if (!in_array($opType, array("Add", "AddUnique", "Remove"))) {
            throw new \InvalidArgumentException("Operation on array not " .
                                                "supported: {$opType}.");
        }
        if (!is_array($val)) {
            throw new \InvalidArgumentException("Operand must be array.");
        }
        $this->key    = $key;
        $this->value  = $val;
        $this->opType = $opType;
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
     * Get type of operation
     *
     * @return string
     */
    public function getOpType() {
        return $this->opType;
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
        return array(
            "__op"    => $this->getOpType(),
            "objects" => Client::encode($this->value),
        );
    }

    /**
     * Add objects of this operation to old array
     *
     * @param  array $oldval Old array of objects
     * @return array         Merged new array
     */
    private function add($oldval) {
        return array_merge($oldval, $this->getValue());
    }

    /**
     * Add objects of this operation, uniquely, to old array.
     *
     * Note duplicated items in old array will remain duplicate.
     *
     * @param  array $oldval Old array of objects
     * @return array
     */
    private function addUnique($oldval) {
        $newval = $oldval; // New result array
        $found  = array(); // Hash map of objects with objectId as key
        forEach($oldval as $obj) {
            if (($obj instanceof LeanObject) && ($obj->getObjectId())) {
                $found[$obj->getObjectId()] = true;
            }
        }
        forEach($this->getValue() as $obj) {
            if (($obj instanceof LeanObject) && ($obj->getObjectId())) {
                if (isset($found[$obj->getObjectId()])) {
                    // skip duplicate object
                } else {
                    $found[$obj->getObjectId()] = true;
                    $newval[]                   = $obj;
                }
            } else if (!in_array($obj, $newval)) {
                $newval[] = $obj;
            }
        }
        return $newval;
    }

    /**
     * Remove objects of this operation from old array.
     *
     * @param  array $oldval Old array of objects
     * @return array
     */
    private function remove($oldval) {
        $newval = array();
        $remove = $this->getValue(); // items to remove
        forEach($oldval as $item) {
            if (!in_array($item, $remove)) {
                $newval[] = $item;
            }
        }
        return $newval;
    }

    /**
     * Apply this operation based on old array.
     *
     * @param  array $oldval Old array
     * @return array
     * @throws RuntimeException
     */
    public function applyOn($oldval) {
        if (!$oldval) { $oldval = array();}

        if (!is_array($oldval)) {
            throw new \RuntimeException("Operation incompatible" .
                                        " with previous value.");
        }

        // TODO: Ensure behaviours of adding and removing associative array
        if ($this->getOpType() === "Add") {
            return $this->add($oldval);
        }
        if ($this->getOpType() === "AddUnique") {
            return $this->addUnique($oldval);
        }
        if ($this->getOpType() === "Remove") {
            return $this->remove($oldval);
        }
        throw new \RuntimeException("Operation type {$this->getOptype()}" .
                                    " not supported.");
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
            if (!is_array($prevOp->getValue())) {
                throw new \RuntimeException("Operation incompatible " .
                                          "with previous value.");
            }
            return new SetOperation($this->key,
                                    $this->applyOn($prevOp->getValue()));
        } else if (($prevOp instanceof ArrayOperation) &&
                   ($this->getOpType() === $prevOp->getOpType())) {
            if ($this->getOpType() === "Remove") {
                $objects = array_merge($prevOp->getValue(), $this->getValue());
            } else {
                $objects = $this->applyOn($prevOp->getValue());
            }
            return new ArrayOperation($this->key,
                                      $objects,
                                      $this->getOpType());
        } else if ($prevOp instanceof DeleteOperation) {
            if ($this->getOpType() === "Remove") {
                return $prevOp;
            } else {
                return new SetOperation($this->getKey(), $this->applyOn(null));
            }
        } else {
            throw new \RuntimeException("Operation incompatible with" .
                                        " previous one.");
        }
    }
}

