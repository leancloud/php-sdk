<?php
namespace LeanCloud;

// use LeanCloud\LeanClient;
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
        if (!$this->getObjectId()) {
            throw new \ErrorException("Object without ID cannot " .
                                      "be serialized.");
        }
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
     *
     * @return bool
     */
    public function hasChanges() {
        // TODO: check children too?
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
     * Save object and its children objects and files
     *
     * @return void
     * @throws ErrorException When save fialed
     */
    public function save() {
        if (!$this->hasChanges()) {return;}
        return self::saveAll(array($this));
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
        }
    }

    /**
     * Merge server data after save.
     *
     * @param array $data JSON decoded server response
     * @return void
     */
    private function _mergeAfterSave($data) {
        $this->_operationSet = array();
        $this->_mergeData($data);
    }

    /**
     * Merge server data after fetch.
     *
     * @param array $data JSON decoded server response
     * @return void
     */
    private function _mergeAfterFetch($data) {
        // Clear local operations prior to the fetch, except the new
        // new fields that does not exist on server.
        forEach($data as $key => $val) {
            if (isset($this->_operationSet[$key])) {
                unset($this->_operationSet[$key]);
            }
        }
        $this->_mergeData($data);
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
        static::fetchAll(array($this));
    }

    /**
     * Fetch objects from server
     *
     * @param array $objects Objects to fetch.
     * @return ???
     * @throws ErrorException
     *         LeanException
     */
    public function fetchAll($objects) {
        $batch = array();
        forEach($objects as $obj) {
            if (!$obj->getObjectId()) {
                throw new \ErrorException("Cannot fetch object without ID.");
            }
            // remove duplicate objects by id
            $batch[$obj->getObjectId()] = $obj;
        }
        if (empty($batch)) { return; }

        $requests = array();
        $objects  = array();
        forEach($batch as $obj) {
            $requests[] = array(
                "path" => "/1.1/classes/{$obj->getClassName()}" .
                          "/{$obj->getObjectId()}",
                "method" => "GET"
            );
            $objects[] = $obj;
        }

        $response = LeanClient::batch($requests);

        $errors = array();
        forEach($objects as $i => $obj) {
            if (isset($response[$i]["success"])) {
                if (!empty($response[$i]["success"])) {
                    $obj->_mergeAfterFetch($response[$i]["success"]);
                } else {
                    $errors[] = array("request" => $requests[$i],
                                      "error" => "Object not found.");
                }
            } else {
                $errors[] = array("request" => $requests[$i],
                                  "error"   => $response[$i]["error"]);
            }
        }
        if (count($errors) > 0) {
            throw new LeanException("Batch requests error: " .
                                    json_encode($errors));
        }
    }

    /**
     * Destroy object on server
     *
     * It does and only destroy current object.
     *
     * @return bool True if success
     * @throws LeanException
     */
    public function destroy() {
        if (!$this->getObjectId()) {
            return false;
        }
        return self::destroyAll(array($this));
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

    /**
     * Traverse value in a hierarchy of arrays and objects
     *
     * Array and data attributes of LeanObject will be traversed, each time
     * a non-array value found the func will be invoked with the value as
     * arguement.
     *
     * @param array    $seen Objects that have been traversed
     * @param function $func A function to call when non-array value found.
     * @return void
     */
    public static function traverse($value, &$seen, $func) {
        if ($value instanceof LeanObject) {
            if (!in_array($value, $seen)) {
                $seen[] = $value;
                static::traverse($value->_data, $seen, $func);
                $func($value);
            }
        } else if (is_array($value)) {
            forEach($value as $val) {
                if (is_array($val)) {
                    static::traverse($val, $seen, $func);
                } else if ($val instanceof LeanObject) {
                    static::traverse($val, $seen, $func);
                } else {
                    $func($val);
                }
            }
        } else {
            $func($value);
        }
    }

    /**
     * Find unsaved children (both files and objects) of object.
     *
     * @return array
     */
    public function findUnsavedChildren() {
        $unsavedChildren = array();
        $seen            = array($this); // excluding object itself
        static::traverse($this->_data, $seen,
                       function($val) use (&$unsavedChildren) {
                           if (($val instanceof LeanObject) ||
                               ($val instanceof LeanFile)) {
                               if ($val->hasChanges()) {
                                   $unsavedChildren[] = $val;
                               }
                           }
                       });
        return $unsavedChildren;
    }

    /**
     * Save objects and associated unsaved children and files.
     *
     * @param  array $objects Array of objects to save
     * @return void
     * @throws ErrorException When save failed
     */
    public static function saveAll($objects) {
        if (empty($objects)) { return; }
        // Unsaved children (objects and files) are saved before saving
        // top level objects.
        $unsavedChildren = array();
        forEach($objects as $obj) {
            $unsavedChildren = array_merge($unsavedChildren,
                                           $obj->findUnsavedChildren());
        }

        $children = array(); // Array of unsaved objects excluding files
        forEach($unsavedChildren as $obj) {
            if ($obj instanceof LeanFile) {
                $obj.save();
            } else if ($obj instanceof LeanObject) {
                if (!in_array($obj, $children)) {
                    $children[] = $obj;
                }
            }
        }

        static::batchSave($children);
        static::batchSave($objects);
    }

    /**
     * Save objects in batch.
     *
     * It saves objects in a shallow way, that we do not care about children
     * objects.
     *
     * @param array $objects   Array of objects to save
     * @param int   $batchSize Number of objects to save per batch
     * @return void
     * @throws ErrorException When save failed
     */
    private static function batchSave($objects, $batchSize=20) {
        if (empty($objects)) { return; }
        $batch     = array(); // current batch of objects to save
        $remaining = array(); // remaining objects to save
        $count     = 0;
        forEach($objects as $obj) {
            if (!$obj->hasChanges()) {
                continue;
            }
            if ($count > $batchSize) {
                $remaining[] = $obj;
                $count++;
                continue;
            }
            $count++;
            $batch[] = $obj;
        }

        $path     = "/1.1/classes";
        $requests = array();
        $objects  = array();
        forEach($batch as $obj) {
            $req = array("body" => $obj->getSaveData());
            if ($obj->getObjectId()) {
                $req["method"] = "PUT";
                $req["path"]   = "{$path}/{$obj->getClassName()}" .
                               "/{$obj->getObjectId()}";
            } else {
                $req["method"] = "POST";
                $req["path"]   = "{$path}/{$obj->getClassName()}";
            }
            $requests[] = $req;
            $objects[]  = $obj;
        }

        $response = LeanClient::batch($requests);

        // TODO: append remaining unsaved items to errors, so user
        // knows all objects that failed to save?
        $errors = array();
        forEach($objects as $i => $obj) {
            if (isset($response[$i]["success"])) {
                $obj->_mergeAfterSave($response[$i]["success"]);
            } else {
                $errors[] = array("request" => $requests[$i],
                                  "error"   => $response[$i]["error"]);
            }
        }
        if (count($errors) > 0) {
            throw new LeanException("Batch requests error: " .
                                      json_encode($errors));
        }

        // start next batch
        static::batchSave($remaining, $batchSize);
    }

    /**
     * Delete objects in batch.
     *
     * @param array $objects Array of LeanObjects to destroy
     * @return bool
     */
    public static function destroyAll($objects) {
        $batch = array();
        forEach($objects as $obj) {
            if (!$obj->getObjectId()) {
                throw new \ErrorException("Cannot destroy object without ID");
            }
            // Remove duplicate objects by ID
            $batch[$obj->getObjectId()] = $obj;
        }
        if (empty($batch)) { return; }

        $requests = array();
        $objects  = array();
        forEach($batch as $obj) {
            $requests[] = array(
                "path" => "/1.1/classes/{$obj->getClassName()}" .
                          "/{$obj->getObjectId()}",
                "method" => "DELETE"
            );
            $objects[] = $obj;
        }

        $response = LeanClient::batch($requests);

        $errors = array();
        forEach($objects as $i => $obj) {
            if (isset($response[$i]["error"])) {
                $errors[] = array("request" => $requests[$i],
                                  "error"   => $response[$i]["error"]);
            }
        }
        if (count($errors) > 0) {
            throw new \LeanException("Batch requests error: " .
                                      json_encode($errors));
        }
    }
}

?>