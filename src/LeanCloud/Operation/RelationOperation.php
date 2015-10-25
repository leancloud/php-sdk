<?php
namespace LeanCloud\Operation;

use LeanCloud\LeanRelation;

/**
 * RelationOperation
 *
 * Operation that supports adding and removing objects from `LeanRelation`.
 */
class RelationOperation implements IOperation {
    /**
     * The key of field the operation is about to apply.
     *
     * @var string
     */
    private $key;

    /**
     * The target className of relation operation.
     *
     * @var string
     */
    private $targetClassName;

    /**
     * The objects to add
     *
     * @var array
     */
    private $objects_to_add = array();

    /**
     * The objects to remove
     *
     * @var array
     */
    private $objects_to_remove = array();

    /**
     * Initialize RelationOperation
     *
     * @param string $key     Field key
     * @param array  $adds    The objects to add
     * @param array  $removes The objects to remove
     * @throws RuntimeException When operand is not relation
     */
    public function __construct($key, $adds, $removes) {
        if (empty($adds) && empty($removes)) {
            throw new \RuntimeException("Invalid operands.");
        }
        $this->key   = $key;
        // The op order here ensures add wins over remove
        $this->remove($removes);
        $this->add($adds);
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
     * Get target className of relation
     *
     * @return string
     */
    public function getTargetClassName() {
        return $this->targetClassName;
    }

    /**
     * Encode to json represented operation.
     *
     * @return string json represented string
     */
    public function encode() {
        $adds    = array("__op" => "AddRelation",
                         "objects" => array());
        $removes = array("__op" => "RemoveRelation",
                         "objects" => array());
        forEach($this->objects_to_add as $obj) {
            $adds["objects"][] = $obj->getPointer();
        }
        forEach($this->objects_to_remove as $obj) {
            $removes["objects"][] = $obj->getPointer();
        }

        if (empty($this->objects_to_remove)) {
            return $adds;
        }
        if (empty($this->objects_to_add)) {
            return $removes;
        }
        return array("__op" => "Batch",
                     "ops"  => array($adds, $removes));
    }

    /**
     * Add object(s) to relation
     *
     * @param array $objects Object(s) to add
     * @return void
     */
    private function add($objects) {
        if (empty($objects)) { return; }
        if (!$this->targetClassName) {
            $this->targetClassName = current($objects)->getClassName();
        }
        forEach($objects as $obj) {
            if (!$obj->getObjectId()) {
                throw new \RuntimeException("Unsaved object(s) cannot be " .
                                          "added to relation.");
            }
            if ($obj->getClassName() !== $this->targetClassName) {
                throw new \RuntimeException("Objects in a relation are not " .
                                          "of same type.");
            }
            if (isset($this->objects_to_remove[$obj->getObjectID()])) {
                unset($this->objects_to_remove[$obj->getObjectID()]);
            }
            $this->objects_to_add[$obj->getObjectId()] = $obj;
        }
    }

    /**
     * Remove object(s) from relation
     *
     * @param array $objects Object(s) to remove
     * @return void
     */
    private function remove($objects) {
        if (empty($objects)) { return; }
        if (!$this->targetClassName) {
            $this->targetClassName = current($objects)->getClassName();
        }
        forEach($objects as $obj) {
            if (!$obj->getObjectId()) {
                throw new \RuntimeException("Unsaved object(s) cannot be " .
                                          "removed from relation.");
            }
            if ($obj->getClassName() !== $this->targetClassName) {
                throw new \RuntimeException("Objects in a relation are not " .
                                          "of same type.");
            }
            if (isset($this->objects_to_add[$obj->getObjectID()])) {
                unset($this->objects_to_add[$obj->getObjectID()]);
            }
            $this->objects_to_remove[$obj->getObjectId()] = $obj;
        }
    }

    /**
     * Apply the operation on previous relation
     *
     * @param LeanRelation $relation Previous relation
     * @param LeanObject   $object   Parent of relation
     * @return LeanRelation
     * @throws RuntimeException
     */
    public function applyOn($relation, $object=null) {
        if (!$relation) {
            return new LeanRelation($object, $this->getKey(),
                                    $this->getTargetClassName());
        }
        if (!($relation instanceof LeanRelation)) {
            throw new \RuntimeException("Operation incompatible to previous " .
                                      "value.");
        }
        // TODO: check target class
        return $relation;
    }

    /**
     * Merge with (previous) operation
     *
     * @param  IOperation $prevOp Previous operation
     * @return IOperation
     */
    public function mergeWith($prevOp) {
        if (!$prevOp) {
            return $this;
        }
        if ($prevOp instanceof RelationOperation) {
            $adds    = array_merge($this->objects_to_add,
                                   $prevOp->objects_to_add);
            $removes = array_merge($this->objects_to_remove,
                                   $prevOp->objects_to_remove);
            return new RelationOperation($this->getKey(), $adds, $removes);
        } else {
            throw new \RuntimeException("Operation incompatible to previous " .
                                      "operation.");
        }
    }
}

