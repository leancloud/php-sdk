<?php
namespace LeanCloud;

use LeanCloud\Operation\SetOperation;
use LeanCloud\Operation\IncrementOperation;

/**
 * LeanObject - Object interface to storage API.
 *
 * Create an plain object with:
 *
 *     $testObject = new LeanObject("TestObject");
 *
 * Or we can extend object to provide more methods:
 *
 *      class Movie extends LeanObject {
 *          protected static $leanClassName;
 *          public function getActors() {
 *              // ...
 *          }
 *      }
 *      Movie::register();
 *      $movie = new Movie();
 *      $movie->getActors();
 */
class LeanObject {
    /**
     * Registered mapping of className to class.
     * @var array()
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
     * @throws InvalidArgumentexception
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
        $this->objectId      = $objectId;
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
     * Set field value by key.
     * @param string $key field key
     * @param mixed  $val field value
     * @return void
     */
    public function set($key, $val) {
        $this->_applyOperation(new SetOperation($key, $val));
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
        $newval = $operation->applyOn($oldval);
        $this->_data[$key] = $newval;

        $prevOp = $this->_getPreviousOp($key);
        $newOp  = $prevOp ? $operation->mergeWith($prevOp) : $operation;
        $this->_operationSet[$key] = $newOp;
    }

    public function addIn($key, $val) {}
    public function addUniqueIn($key, $val) {}
    public function removeIn() {}
    public function save() {}
    public function delete($key) {}
    public function destroy() {}

    public function query() {}
    public function relation($key) {}

    public static function saveAll() {}
    public static function destroyAll() {}
}

?>