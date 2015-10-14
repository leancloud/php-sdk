<?php

namespace LeanCloud\Storage;

/**
 * Cookie Storage
 *
 * Persist key value in browser cookies, note there is limit on how many
 * keys and how many bytes per value could have.
 *
 * @see http://php.net/manual/en/function.setcookie.php
 */
class CookieStorage implements IStorage {
    /**
     * Domain scope for cookie availability.
     *
     * @var string
     */
    private $domain;

    /**
     * Path scope for cookie availability.
     *
     * @var string
     */
    private $path;

    /**
     * When to expire the cookie.
     *
     * It is number of seconds since epoch time.
     *
     * @var int Number of seconds since epoch time.
     */
    private $expireIn;

    /**
     * Initilize cookie storage
     *
     * @param int    $seconds Number of seconds to live
     * @param string $path    Cookie path scope
     * @param string $domain  Cookie domain scope
     */
    public function __construct($seconds=0, $path="/", $domain=null) {
        if ($seconds <= 0) {
            // default to 7 days from now
            $seconds = 60 * 60 * 24 * 7;
        }
        $this->expireIn = time() + $seconds;
        $this->path     = $path;
        $this->domain   = $domain;
    }

    /**
     * Set value by key
     *
     * @param string $key
     * @param mixed  $val
     * @param int    $seconds Number of seconds to live
     * @return $this
     */
    public function set($key, $val, $seconds=null) {
        $expire = $seconds ? (time() + seconds) : $this->expireIn;
        setcookie($key, $val, $expire, $this->path, $this->domain);
    }

    /**
     * Get value by key
     *
     * @param string $key
     * @return mixed
     */
    public function get($key) {
        if (isset($_COOKIE[$key])) {
            return $_COOKIE[$key];
        }
        return null;
    }

    /**
     * Remove key from storage
     *
     * @param string $key
     * @return null
     */
    public function remove($key) {
        setcookie($key, false, 1);
    }

    /**
     * Clear all data in storage
     *
     * @return null
     */
    public function clear() {
        throw new \ErrorException("Not implemented error.");
    }
}

?>