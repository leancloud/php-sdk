<?php

namespace LeanCloud\Storage;

/**
 * Session Based Storage
 *
 * `$_SESSION` based key-value storage for persistence.
 *
 * Note that PHP stores session data by default in local file, which
 * means the storage data will only be available to local server
 * instance. In a distributed deployment, requests of same user might
 * be routed to different instances, where session data might be not
 * avaialible. In such cases this session based storage should __not__
 * be used.
 *
 */
class SessionStorage implements IStorage {

    /**
     * Storage Key
     *
     * Value will be stored under this key in $_SESSION.
     *
     * @var string
     */
    private static $storageKey = "LCData";

    /**
     * Initialize session storage
     */
    public function __construct() {
        if (!isset($_SESSION[static::$storageKey])) {
            $_SESSION[static::$storageKey] = array();
        }
    }

    /**
     * Set value by key
     *
     * @param string $key
     * @param mixed  $val
     */
    public function set($key, $val) {
        $_SESSION[static::$storageKey][$key] = $val;
    }

    /**
     * Get value by key
     *
     * @param string $key
     * @return mixed
     */
    public function get($key) {
        if (isset( $_SESSION[static::$storageKey][$key])) {
            return $_SESSION[static::$storageKey][$key];
        }
        return null;
    }

    /**
     * Remove key from storage
     *
     * @param string $key
     */
    public function remove($key) {
        unset($_SESSION[static::$storageKey][$key]);
    }

    /**
     * Clear all data in storage
     */
    public function clear() {
        $_SESSION[static::$storageKey] = array();
    }
}

