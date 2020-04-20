<?php

namespace LeanCloud;

use LeanCloud\Region;

class AppRouter {
    const TTL_KEY               = "ttl";
    const API_SERVER_KEY        = "api_server";
    const PUSH_SERVER_KEY       = "push_server";
    const STATS_SERVER_KEY      = "stats_server";
    const ENGINE_SERVER_KEY     = "engine_server";
    const RTM_ROUTER_SERVER_KEY = "rtm_router_server";
    private static $INSTANCES;
    private $appId;
    private $region;
    private $routeCache;

    private static $DEFAULT_REGION_ROUTE = array(
        Region::US    => "us-api.leancloud.cn",
        Region::CN_E1 => "e1-api.leancloud.cn",
        Region::CN_N1 => "api.leancloud.cn"
    );

    private static $DEFAULT_REGION_RTM_ROUTE = array(
        Region::US    => "router-a0-push.leancloud.cn",
        Region::CN_E1 => "router-q0-push.leancloud.cn",
        Region::CN_N1 => "router-g0-push.leancloud.cn"
    );

    private function __construct($appId) {
        $this->appId = $appId;
        $region = getenv("LEANCLOUD_REGION");
        if (!$region) {
            $region = Region::CN;
        }
        $this->setRegion($region);
        $this->routeCache = RouteCache::create($appId);
    }

    /**
     * Get instance of AppRouter.
     */
    public static function getInstance($appId) {
        if (isset(self::$INSTANCES[$appId])) {
            return self::$INSTANCES[$appId];
        } else {
            $router = new AppRouter($appId);
            self::$INSTANCES[$appId] = $router;
            return $router;
        }
    }

    /**
     * Get app region default route host
     */
    public function getRegionDefaultRoute($server_key) {
        $this->validate_server_key($server_key);
        return $this->getDefaultRoutes()[$server_key];
    }

    /**
     * Set region
     *
     * See `LeanCloud\Region` for available regions.
     *
     * @param mixed $region
     */
    public function setRegion($region) {
        if (is_numeric($region)) {
            $this->region = $region;
        } else {
            $this->region = Region::fromName($region);
        }
    }

    /**
     * Get and return route host by server type, or null if not found.
     */
    public function getRoute($server_key) {
        $this->validate_server_key($server_key);
        $routes = $this->routeCache->read();
        if (isset($routes[$server_key])) {
            return $routes[$server_key];
        }
        $routes = $this->getRoutes();
        if (!$routes) {
            $routes = $this->getDefaultRoutes();
        }
        $this->routeCache->write($routes);
        return isset($routes[$server_key]) ? $routes[$server_key] : null;
    }

    private function getRouterUrl() {
        $url = getenv("LEANCLOUD_APP_ROUTER");
        if (!$url) {
            $url = "https://app-router.leancloud.cn/2/route?appId=";
        }
        return "{$url}{$this->appId}";
    }

    private function validate_server_key($server_key) {
        $routes = $this->getDefaultRoutes();
        if (!isset($routes[$server_key])) {
            throw new IllegalArgumentException("Invalid server key.");
        }
    }

    /**
     * Detect region by app-id
     */
    private function detectRegion() {
        if (!$this->appId) {
            return Region::CN_N1;
        }
        $parts = explode("-", $this->appId);
        if (count($parts) <= 1) {
            return Region::CN_N1;
        } else if ($parts[1] === "MdYXbMMI") {
            return Region::US;
        } else if ($parts[1] === "9Nh9j0Va") {
            return Region::CN_E1;
        } else {
            $this->region = Region::CN_N1;
        }
    }

    /**
     * Get routes remotely from app router, return array.
     */
    private function getRoutes() {
        $routes = @json_decode(file_get_contents($this->getRouterUrl()), true);
        if (isset($routes[self::TTL_KEY])) {
            return $routes;
        }
        return null;
    }

    /**
     * Fallback default routes, if app router not available.
     */
    private function getDefaultRoutes() {
        $host = self::$DEFAULT_REGION_ROUTE[$this->region];

        return array(
            self::API_SERVER_KEY        => $host,
            self::PUSH_SERVER_KEY       => $host,
            self::STATS_SERVER_KEY      => $host,
            self::ENGINE_SERVER_KEY     => $host,
            self::RTM_ROUTER_SERVER_KEY => self::$DEFAULT_REGION_RTM_ROUTE[$this->region],
            self::TTL_KEY               => 3600
        );
    }


}


/**
 * Route cache
 *
 * Ideally we should use ACPu for caching, but it can be inconvenient to
 * install, esp. on Windows[1], thus we implement a naive file based
 * cache.
 *
 * [1]: https://stackoverflow.com/a/28124144/108112
 */
class RouteCache {
    private $filename;
    private $_cache;

    private function __construct($id) {
        $this->filename = sys_get_temp_dir() . "/route_{$id}.json";
    }

    public static function create($id) {
        return new RouteCache($id);
    }

    /**
     * Serialize array and store in file, array must be json_encode safe.
     */
    public function write($array) {
        $body = json_encode($array);
        if (file_put_contents($this->filename, $body, LOCK_EX) === false) {
            error_log("WARNING: failed to write route cache ({$this->filename}), performance may be degraded.");
        } else {
            $this->_cache = $array;
        }
    }

    /**
     * Read routes either from cache or file, return json_decoded array.
     */
    public function read() {
        if ($this->_cache) {
            return $this->_cache;
        }
        $data = $this->readFile();
        if (!empty($data)) {
            $this->_cache = $data;
            return $data;
        }
        return null;
    }

    private function readFile() {
        if (file_exists($this->filename)) {
            $fp = fopen($this->filename, "rb");
            $body = null;
            if (flock($fp, LOCK_SH)) {
                $body = fread($fp, filesize($this->filename));
                flock($fp, LOCK_UN);
            }
            fclose($fp);
            if (!empty($body)) {
                $data = @json_decode($body, true);
                if (!empty($data)) {
                    return $data;
                }
            }
        }
        return null;
    }
}