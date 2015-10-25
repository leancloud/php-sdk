<?php
namespace LeanCloud;

use LeanCloud\LeanClient;
use LeanCloud\LeanObject;

/**
 * LeanQuery - Query representation for objects on LeanCloud
 *
 */
class LeanQuery {
    /**
     * ClassName the query will operate on
     *
     * @var string
     */
    private $className;

    /**
     * Keys of fields to include in query result
     *
     * @var array
     */
    private $select;

    /**
     * Keys of (nested) object fields to include in query result
     *
     * @var array
     */
    private $include;

    /**
     * Limit number of rows to return in query result
     *
     * @var int
     */
    private $limit;

    /**
     * Skip number of rows to return in query result
     *
     * @var int
     */
    private $skip;

    /**
     * Ordering for the query
     *
     * @var array
     */
    private $order;

    /**
     * Extra option as query request params
     *
     * @var array
     */
    private $extraOption;
    
    /**
     * Initilize query
     *
     * @param string|LeanObject $queryClass The class to operate on
     */
    public function __construct($queryClass) {
        if (is_string($queryClass)) {
            $this->className = $queryClass;
        } else if (is_subclass_of($queryClass, "LeanObject")) {
            $this->className = $queryClass::$className;
        } else {
            throw new \InvalidArgumentException("Query class invalid.");
        }
        $this->where   = array();
        $this->select  = array();
        $this->include = array();
        $this->order   = array();
        $this->limit   = -1;
        $this->skip    = 0;
        $this->extraOption = array();
    }

    /**
     * Get className of the query
     *
     * @return string
     */
    public function getClassName() {
        return $this->className;
    }

    /**
     * Add condition on field
     *
     * @param string $key Field key
     * @param string $op  Condition operator: $ne, $in, $all, $nin etc
     * @param mixed  $val Condition value(s)
     * @return null
     */
    private function _addCondition($key, $op, $val) {
        $this->where[$key][$op] = LeanClient::encode($val);
    }

    /**
     * Field equals to a value
     *
     * Equal-to condition is the strongest constraint overall, it has
     * higher precedence than all other conditions. All prior
     * conditions set on the field will be discarded and of no effect
     * when adding an equal-to condition.
     *
     * @param string $key
     * @param mixed  $val
     * @return $this
     */
    public function equalTo($key, $val) {
        $this->where[$key] = LeanClient::encode($val);
        return $this;
    }

    /**
     * Field does not equal to a value
     *
     * @param string $key
     * @param mixed  $val
     * @return $this
     */
    public function notEqualTo($key, $val) {
        $this->_addCondition($key, '$ne', $val);
        return $this;
    }

    /**
     * Field less than a value
     *
     * @param string $key
     * @param mixed  $val
     * @return $this
     */
    public function lessThan($key, $val) {
        $this->_addCondition($key, '$lt', $val);
        return $this;
    }

    /**
     * Field less than or equal to a value
     *
     * @param string $key
     * @param mixed  $val
     * @return $this
     */
    public function lessThanOrEqualTo($key, $val) {
        $this->_addCondition($key, '$lte', $val);
        return $this;
    }

    /**
     * Field greater than a value
     *
     * @param string $key
     * @param mixed  $val
     * @return $this
     */
    public function greaterThan($key, $val) {
        $this->_addCondition($key, '$gt', $val);
        return $this;
    }

    /**
     * Field greater than or equal to a vaule
     *
     * @param string $key
     * @param mixed  $val
     * @return $this
     */
    public function greaterThanOrEqualTo($key, $val) {
        $this->_addCondition($key, '$gte', $val);
        return $this;
    }

    /**
     * Field value is contained in an array
     *
     * @param string $key
     * @param array  $vals
     * @return $this
     */
    public function containedIn($key, $vals) {
        $this->_addCondition($key, '$in', $vals);
        return $this;
    }

    /**
     * Field value is not contained in an array
     *
     * @param string $key
     * @param array  $vals
     * @return $this
     */
    public function notContainedIn($key, $vals) {
        $this->_addCondition($key, '$nin', $vals);
        return $this;
    }

    /**
     * Field contains all values from an array
     *
     * @param string $key
     * @param array  $vals
     * @return $this
     */
    public function containsAll($key, $vals) {
        $this->_addCondition($key, '$all', $vals);
        return $this;
    }

    /**
     * Array field size equal to val
     *
     * @param string $key
     * @param int    $val
     * @return $this
     */
    public function sizeEqualTo($key, $val) {
        $this->_addCondition($key, '$size', $val);
        return $this;
    }

    /**
     * Field exists
     *
     * @param string $key
     * @return $this
     */
    public function exists($key) {
        $this->_addCondition($key, '$exists', true);
        return $this;
    }

    /**
     * Field not exists
     *
     * @param string $key
     * @return $this
     */
    public function notExists($key) {
        $this->_addCondition($key, '$exists', false);
        return $this;
    }

    // String match

    /**
     * String field contains a (string) value
     *
     * It performs regex search on string fields, thus might be slow
     * for large datasets.
     *
     * @param string $key
     * @param string $val The substring to find
     * @return $this
     */
    public function contains($key, $val) {
        $this->_addCondition($key, '$regex', $val);
        return $this;
    }

    /**
     * String field starts with a (string) value
     *
     * It performs regex search on string fields, thus might be slow
     * for large datasets.
     *
     * @param string $key
     * @param string $val The start string
     * @return $this
     */
    public function startsWith($key, $val) {
        $this->_addCondition($key, '$regex', '^' . $val);
        return $this;
    }

    /**
     * String field ends with a (string) value
     *
     * It performs regex search on string fields, thus might be slow
     * for large datasets.
     *
     * @param string $key
     * @param string $val The end string
     * @return $this
     */
    public function endsWith($key, $val) {
        $this->_addCondition($key, '$regex', $val . '$' );
        return $this;
    }

    /**
     * String field matches a regular expression
     *
     * The regex string should not be enclosed with `/`, and modifiers
     * must be provided via `$modifiers` argument.
     *
     * It performs regex search on string fields, thus might be slow
     * for large datasets.
     *
     * @param string $key
     * @param string $regex     The pattern string
     * @param string $modifiers (optional) Regexp modifiers: "i", "m" etc.
     * @return $this
     */
    public function matches($key, $regex, $modifiers="") {
        $this->_addCondition($key, '$regex', $regex);
        if (!empty($modifiers)) {
            $this->_addCondition($key, '$options', $modifiers);
        }
        return $this;
    }

    // end String match

    // Sub query

    /**
     * Matches result objects returned from a sub-query
     *
     * @param string    $key
     * @param LeanQuery $query The sub-query
     * @return $this
     */
    public function matchesInQuery($key, $query) {
        $this->_addCondition($key, '$inQuery', array(
            "where"     => $query->where,
            "className" => $query->getClassName()
        ));
        return $this;
    }

    /**
     * Not-match result objects returned from a sub-query
     *
     * @param string    $key
     * @param LeanQuery $query The sub-query
     * @return $this
     */
    public function notMatchInQuery($key, $query) {
        $this->_addCondition($key, '$notInQuery', array(
            "where"     => $query->where,
            "className" => $query->getClassName()
        ));
        return $this;
    }

    /**
     * Matches values of specified field from a sub-query
     *
     * @param string    $key
     * @param string    $queryKey Target field key in sub-query
     * @param LeanQuery $query    The sub-query
     * @return $this
     */
    public function matchesFieldInQuery($key, $queryKey, $query) {
        $this->_addCondition($key, '$select', array(
            "key"   => $queryKey,
            "query" => array(
                "where"     => $query->where,
                "className" => $query->getClassName()
            )
        ));
        return $this;
    }

    /**
     * Not-match values of specified field from a sub-query
     *
     * @param string    $key
     * @param string    $queryKey Target field key in sub-query
     * @param LeanQuery $query    The sub-query
     * @return $this
     */
    public function notMatchFieldInQuery($key, $queryKey, $query) {
        $this->_addCondition($key, '$dontSelect', array(
            "key"   => $queryKey,
            "query" => array(
                "where"     => $query->where,
                "className" => $query->getClassName()
            )
        ));
        return $this;
    }

    /**
     * Relation field related to an object
     *
     * @param string $key     A relation field key
     * @param LeanObject $obj Target object to relate
     * @return $this
     */
    public function relatedTo($key, $obj) {
        $this->where['$relatedTo'] = array(
            "key"    => $key,
            "object" => $obj->getPointer()
        );
        return $this;
    }

    // end Sub query

    /**
     * Specify fields to include in query result
     *
     * It accepts either one array of keys, or variable number of keys.
     *
     * @param string|array of string Field keys
     * @return $this
     */
    public function select($keys) {
        if (!is_array($keys)) {
            $keys = func_get_args();
        }
        $this->select = array_merge($this->select, $keys);
        return $this;
    }

    /**
     * Include nested objects in query result
     *
     * Include nested object to return full object instead of
     * pointer. It accepts an array of keys, or variable number of
     * keys.
     *
     * Deep nested fields can be specified as dot separated field,
     * e.g. `$commentQuery->include('post.author')` which will include
     * author object in comment query result.
     *
     * @param string|array of string Field keys
     * @return $this
     */
    public function _include($keys) {
        if (!is_array($keys)) {
            $keys = func_get_args();
        }
        $this->include = array_merge($this->include, $keys);
        return $this;
    }

    /**
     * Limit number of rows to return
     *
     * The default limit is 100, with a max of 1000 results being returned
     * at a time.
     *
     * @param int $n Number of rows
     * @return $this
     */
    public function limit($n) {
        $this->limit = $n;
        return $this;
    }

    /**
     * Skip number of rows to return in the query
     *
     * The default is to skip at zero.
     *
     * @param int $n Number of rows to skip
     * @return $this
     */
    public function skip($n) {
        $this->skip = $n;
        return $this;
    }

    /**
     * Set ascending order on field
     *
     * Previous order constraints will be discarded.
     *
     * @param string $key
     * @return $this
     */
    public function ascend($key) {
        $this->order = array($key);
        return $this;
    }

    /**
     * Set descending order on field
     *
     * Previous order constraints will be discarded.
     *
     * @param string $key
     * @return $this
     */
    public function descend($key) {
        $this->order = array("-$key");
        return $this;
    }

    /**
     * Add ascending order on a field
     *
     * The previous ordering will have higher precedence over this one.
     *
     * @param string $key
     * @return $this
     */
    public function addAscend($key) {
        $this->order[] = $key;
        return $this;
    }

    /**
     * Add descending order on a field
     *
     * The previous ordering will have higher precedence over this one.
     *
     * @param string $key
     * @return $this
     */
    public function addDescend($key) {
        $this->order[] = "-$key";
        return $this;
    }

    /**
     * Compose AND/OR query from queries
     *
     * @param string $op      Operator string, either `$and` or `$or`
     * @param array  $queries Array of LeanQuery
     * @return LeanQuery
     */
    private static function composeQuery($op, $queries) {
        $className = $queries[0]->getClassName();
        $conds = array(); // where conditions
        forEach($queries as $q) {
            if ($q->getClassName() != $className) {
                throw new \RuntimeException("Query class incompatible.");
            }
            $conds[] = $q->where;
        }
        $query = new LeanQuery($className);
        $query->where[$op] = $conds;
        return $query;
    }

    /**
     * Compose OR query from variable number of queries
     *
     * It accepts one array of LeanQueries, or variable number of
     * LeanQueries.
     *
     * @param ...
     * @return LeanQuery
     */
    public static function orQuery($queries) {
        if (!is_array($queries)) {
            $queries = func_get_args();
        }
        return self::composeQuery('$or', $queries);
    }

    /**
     * Compose AND query from variable number of queries
     *
     * It accepts one array of LeanQueries, or variable number of
     * LeanQueries.
     *
     * @param ...
     * @return LeanQuery
     */
    public static function andQuery($queries) {
        if (!is_array($queries)) {
            $queries = func_get_args();
        }
        return self::composeQuery('$and', $queries);
    }

    /**
     * Add extra option in query request
     *
     * Extra options will be appended as request params when
     * sending query to cloud.
     *
     * @param string $key
     * @param mixed  $val
     * @return $this
     */
    public function addOption($key, $val) {
        $this->extraOption[$key] = $val;
        return $this;
    }

    /**
     * Encode query to JSON representation
     *
     * @return array
     */
    public function encode() {
        $out = array();
        if (!empty($this->extraOption)) {
            // extraOption has lower precedence than preserved query params
            $out = $this->extraOption;
        }
        if (!empty($this->where)) {
            $out["where"]   = json_encode($this->where);
        }
        if (!empty($this->select)) {
            $out["keys"]    = implode(",", $this->select);
        }
        if (!empty($this->include)) {
            $out["include"] = implode(",", $this->include);
        }
        if ($this->skip > 0) {
            $out["skip"] = $this->skip;
        }
        if ($this->limit > -1) {
            $out["limit"] = $this->limit;
        }
        if (!empty($this->order)) {
            $out["order"] = implode(",", $this->order);
        }
        return $out;
    }

    /**
     * Query object by id
     *
     * @param string $objectId
     * @return LeanObject
     */
    public function get($objectId) {
        $this->equalTo('objectId', $objectId);
        return $this->first();
    }

    /**
     * Find the first object by the query
     *
     * @return LeanObject
     */
    public function first() {
        $objects = $this->find($this->skip, 1);
        if (empty($objects)) {
            throw new CloudException("Object not found.", 101);
        }
        return $objects[0];
    }

    /**
     * Find and return objects by the query
     *
     * The skip or limit provided here will have higher precedence
     * than the ones specified by query's `skip` and `limit` methods.
     * It does not mutate query.
     *
     * @param int $skip  (optional) Number of rows to skip
     * @param int $limit (optional) Max number of rows to fetch
     * @return array
     */
    public function find($skip=-1, $limit=-1) {
        $params = $this->encode();
        if ($skip >= 0) {
            $params["skip"] = $skip;
        }
        if ($limit >= 0) {
            $params["limit"] = $limit;
        }

        $resp = LeanClient::get("/classes/{$this->getClassName()}", $params);
        $objects = array();
        forEach($resp["results"] as $props) {
            $obj = LeanObject::create($this->getClassName());
            $obj->mergeAfterFetch($props);
            $objects[] = $obj;
        }
        return $objects;
    }

    /**
     * Count number of objects by the query
     *
     * @return int
     */
    public function count() {
        $params = $this->encode();
        $params["limit"] = 0;
        $params["count"] = 1;
        $resp = LeanClient::get("/classes/{$this->getClassName()}", $params);
        return $resp["count"];
    }
}

