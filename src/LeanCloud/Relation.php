<?php

namespace LeanCloud;

use LeanCloud\Operation\RelationOperation;

/**
 * Many-to-many relationship for LeanObject
 *
 * A relation consists of an array of objects, of which items can be
 * added to, and removed from. Each field could only have one kind of
 * object.
 */
class Relation {
    /**
     * The parent object of relation.
     *
     * @var LeanObject
     */
    private $parent;

    /**
     * The parent field key of relation.
     *
     * @var string
     */
    private $key;

    /**
     * The target className of relation.
     *
     * @var string
     */
    private  $targetClassName;

    /**
     * Initialize relation
     *
     * Build a relation on parent field. It shall be rarely used
     * directly, use `$parent->getRelation($key)` instead.
     *
     * @param LeanObject $parent    Parent object
     * @param string     $key       Field key on parent object
     * @param string     $className ClassName the object relatedTo
     */
    public function __construct($parent, $key, $className=null) {
        $this->parent          = $parent;
        $this->key             = $key;
        $this->targetClassName = $className;
    }

    /**
     * Encode to JSON representation of relation.
     *
     * @return array
     */
    public function encode() {
        return array("__type"    => "Relation",
                     "className" => $this->targetClassName);
    }

    /**
     * Attempt to set and validate parent of relation
     *
     * @param LeanObject $parent Parent object of relation
     * @param string     $key    Field key
     * @throws RuntimeException
     */
    public function setParentAndKey($parent, $key) {
        if ($this->parent && $this->parent != $parent) {
            throw new \RuntimeException("Relation does not belong to the object");
        }
        if ($this->key && $this->key != $key) {
            throw new \RuntimeException("Relation does not belong to the field");
        }
        $this->parent = $parent;
        $this->key    = $key;
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
     * Add object(s) to the field as relation
     *
     * @param object|array $objects LeanObject(s) to add
     */
    public function add($objects) {
        if (!is_array($objects)) { $objects = array($objects); }
        $op = new RelationOperation($this->key, $objects, null);
        $this->parent->set($this->key, $op);
        if (!$this->targetClassName) {
            $this->targetClassName = $op->getTargetClassName();
        }
    }

    /**
     * Remove object(s) from the field
     *
     * @param object|array $objects LeanObject(s) to remove
     */
    public function remove($objects) {
        if (!is_array($objects)) { $objects = array($objects); }
        $op = new RelationOperation($this->key, null, $objects);
        $this->parent->set($this->key, $op);
        if (!$this->targetClassName) {
            $this->targetClassName = $op->getTargetClassName();
        }
    }

    /**
     * Query on the target class of relation
     *
     * @return Query
     */
    public function getQuery() {
        if ($this->targetClassName) {
            $query = new Query($this->targetClassName);
        } else {
            $query = new Query($this->parent->getClassName());
            $query->addOption("redirectClassNameForKey", $this->key);
        }
        $query->relatedTo($this->key, $this->parent);
        return $query;
    }

    /**
     * Query on the parent class where child is in the relation
     *
     * @param LeanObject $child  Child object
     * @return Query
     */
    public function getReverseQuery(LeanObject $child) {
        $query = new Query($this->parent->getClassName());
        $query->equalTo($this->key, $child->getPointer());
        return $query;
    }

}

