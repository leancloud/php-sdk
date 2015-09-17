<?php
namespace LeanCloud;

/**
 * LeanObject - Object interface to storage API.
 *
 * Create an plain object with:
 *
 *     $testObject = new LeanObject("TestObject");
 *
 * Or we can extend object to provide domain methods:
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
     * @return string if found, false if not.
     */
    private static function getRegisteredClassName() {
        return array_search(get_called_class(), self::$_registeredClasses);
    }

    public function set($key, $val) {}
    public function increment($key, $val) {}
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