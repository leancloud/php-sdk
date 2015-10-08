<?php
namespace LeanCloud;

use LeanCloud\Operation\IOperation;
use LeanCloud\Operation\SetOperation;
use LeanCloud\Operation\DeleteOperation;
use LeanCloud\Operation\ArrayOperation;
use LeanCloud\Operation\IncrementOperation;

/**
 * LeanObject - Object interface to LeanCloud storage API.
 *
 */
class LeanObject {
    /**
     * Registered mapping of className to class.
     * @var array
     */
    private static $_registeredClasses = array();

    /**
     * ClassName to LeanCloud storage.
     * @var string
     */
    private $_className;
    private $_serverData;
    private $_data;
    private $_operationSet;

    /**
     * Unique ID on LeanCloud storage.
     * @var string
     */
    private $objectId;

    /**
     * Make a new *plain* LeanObject.
     *
     * @param string $className
     * @param string $objectId
     * @throws InvalidArgumentException
     */
    public function __construct($className=null, $objectId=null) {
        $class = get_called_class();
        $name  = static::getRegisteredClassName();

        $className = $className ? $className : $name;
        if (!$className || ($class !== __CLASS__ && $className !== $name)) {
            throw new \InvalidArgumentException(
                "className is invalid.");
        }
        $this->_className    = $className;
        $this->_data         = array();
        $this->_serverdata   = array();
        $this->_operationSet = array();
        $this->_data["objectId"] = $objectId;
    }

    /**
     * Register class with className.
     *
     * Only callable on subclass of LeanObject.
     * @throws ErrorException
     */
    public static function registerClass() {
        if (isset(static::$leanClassName)) {
            $class = get_called_class();
            if (isset(self::$_registeredClasses[static::$leanClassName])) {
                $prevClass = self::$_registeredClasses[static::$leanClassName];
                if ($class !== $prevClass) {
                    throw new \ErrorException("Cannot overwriting registered className.");
                }
            } else {
                self::$_registeredClasses[static::$leanClassName] = get_called_class();
            }
        } else {
            throw new \ErrorException("Cannot register class without leanClassName.");
        }
    }

    /**
     * Get registered className of current subclass.
     *
     * @return string if found, false if not.
     */
    private static function getRegisteredClassName() {
        return array_search(get_called_class(), self::$_registeredClasses);
    }

    /**
     * Get className of object
     *
     * @return string
     */
    public function getClassName() {
        return $this->_className;
    }

    /**
     * Get JSON representation of object pointer
     *
     * @return array
     */
    public function getPointer() {
        return array(
            "__type"    => "Pointer",
            "className" => $this->getClassName(),
            "objectId"  => $this->getObjectId(),
        );
    }

    /**
     * Get objectId.
     *
     * @return string
     */
    public function getObjectId() {
        return $this->get("objectId");
    }

    /**
     * Get DateTime of creating object on LeanCloud.
     */
    public function getCreatedAt() {
        return $this->get("createdAt");
    }

    /**
     * Get DateTime of updating object on LeanCloud.
     */
    public function getUpdatedAt() {
        return $this->get("updatedAt");
    }

    /**
     * Set field value by key.
     * @param string $key field key
     * @param mixed  $val field value
     * @return void
     * @throws ErrorException
     */
    public function set($key, $val) {
        if (in_array($key, array("objectId", "createdAt", "updatedAt"))) {
            throw new \ErrorException("Preserved field could not be set.");
        }
        if (!($val instanceof IOperation)) {
            $val = new SetOperation($key, $val);
        }
        $this->_applyOperation($val);
    }

    /**
     * Delete field by key.
     *
     * @param string $key Field key
     * @return void
     */
    public function delete($key) {
        $this->_applyOperation(new DeleteOperation($key));
    }

    /**
     * Get field value by key.
     *
     * @param string $key field key
     * @return mixed      field value
     */
    public function get($key) {
        if (isset($this->_data[$key])) {
            return $this->_data[$key];
        }
        return null;
    }

    /**
     * Increment a numeric field
     *
     * For decrement, provide a negative amount.
     *
     * @param string $key    field key
     * @param number $amount amount to increment
     */
    public function increment($key, $amount = 1) {
        $this->_applyOperation(new IncrementOperation($key, $amount));
    }

    /**
     * Get queued operation by key
     *
     * @param string $key
     * @return IOperation, null if not found.
     */
    private function _getPreviousOp($key) {
        if (isset($this->_operationSet[$key])) {
            return $this->_operationSet[$key];
        }
        return null;
    }

    /**
     * Apply operation
     *
     * @param IOperation $operation
     * @return void
     */
    private function _applyOperation($operation) {
        $key    = $operation->getKey();
        $oldval = $this->get($key);
        $newval = $operation->applyOn($oldval, $this);
        if ($newval !== null) {
            $this->_data[$key] = $newval;
        } else if (isset($this->_data[$key])) {
            unset($this->_data[$key]);
        }

        $prevOp = $this->_getPreviousOp($key);
        $newOp  = $prevOp ? $operation->mergeWith($prevOp) : $operation;
        $this->_operationSet[$key] = $newOp;
    }

    /**
     * Add one object into array field
     *
     * @param  string $key Field key
     * @param  miexed $val Object to add
     * @return void
     * @throws ErrorException When adding to non-array field
     */
    public function add($key, $val) {
        $this->_applyOperation(new ArrayOperation($key, array($val), "Add"));
    }

    /**
     * Add one object into array field only if it is not already present.
     *
     * @param string $key Field key
     * @param mixed  $val Object to add
     * @return void
     * @throws ErrorException When adding to non-array field
     */
    public function addUnique($key, $val) {
        $this->_applyOperation(new ArrayOperation($key,
                                                  array($val),
                                                  "AddUnique"));
    }

    /**
     * Remove one object from array field.
     *
     * @param string $key Field key
     * @param mixed  $val Object to remove
     * @return void
     * @throws ErrorException When removing from non-array field
     */
    public function remove($key, $val) {
        $this->_applyOperation(new ArrayOperation($key, array($val), "Remove"));
    }

    /**
     * Has changes or not.
     * @return bool
     */
    private function hasChanges() {
        return !empty($this->_operationSet);
    }

    /**
     * Get unsaved data
     *
     * @return array Deep associative array
     */
    private function getSaveData() {
        return LeanClient::encode($this->_operationSet);
    }

    /**
     * Save object
     *
     * @return void
     */
    public function save() {
        if (!$this->hasChanges()) {return;}
        $data = $this->getSaveData();
        if (empty($this->getObjectId())) {
            $resp = LeanClient::post("/classes/{$this->_className}", $data);
        } else {
            $resp = LeanClient::put("/classes/{$this->_className}/{$this->getObjectId()}",
                                    $data);
        }
        $this->_mergeData($resp);
    }

    /**
     * Merge data from server
     *
     * @param array $data JSON decoded server response
     * @return void
     */
    private function _mergeData($data) {
        forEach($data as $key => $val) {
            $this->_data[$key] = $val;
            if (isset($this->_operationSet[$key])) {
                unset($this->_operationSet[$key]);
            }
        }
    }

    /**
     * Fetch data from server
     *
     * Local unsaved changes will be **discarded**.
     *
     * @return bool False if object not found.
     * @throws ErrorException, LeanException
     */
    public function fetch() {
        if (empty($this->getObjectId())) {
            throw new \ErrorException("Cannot fetch object without objectId.");
        }
        $resp = LeanClient::get("/classes/{$this->_className}/{$this->getObjectId()}");
        if (empty($resp)) {
            return false;
        } else {
            $this->_mergeData($resp);
            return true;
        }
    }

    /**
     * Destroy object on server
     *
     * It does and only destroy current object.
     *
     * @return void
     * @throws LeanException
     */
    public function destroy() {
        if (!$this->getObjectId()) {
            return;
        }

        LeanClient::delete("/classes/{$this->_className}/" .
                           "{$this->getObjectId()}");
    }

    public function query() {}

    /**
     * Get (or build) relation on field
     *
     * @param  string $key Field key
     * @return LeanRelation
     * @throws ErrorException When it is not relation field
     */
    public function getRelation($key) {
        $val = $this->get($key);
        if ($val) {
            if ($val instanceof LeanRelation) {
                return $val;
            } else {
                throw new \ErrorException("Field {$key} is not relation.");
            }
        }
        return new LeanRelation($this, $key);
    }

    public static function saveAll() {}
    public static function destroyAll() {}
}

?>