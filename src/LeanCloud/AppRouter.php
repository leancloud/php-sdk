<?php

namespace LeanCloud;

use LeanCloud\Region;

class AppRouter {
    const ROUNTER_URL = "https://app-router.leancloud.cn/2/route?appId=";
    const TTL_KEY               = "ttl";
    const API_SERVER_KEY        = "api_server";
    const PUSH_SERVER_KEY       = "push_server";
    const STATS_SERVER_KEY      = "stats_server";
    const ENGINE_SERVER_KEY     = "engine_server";
    const RTM_ROUTER_SERVER_KEY = "rtm_router_server";
    private final $appId;
    private final $region;

    private static final $DEFAULT_REGION_HOST = array(
        Region::US    => "us-api.leancloud.cn",
        Region::CN_E1 => "e1-api.leancloud.cn",
        Region::CN_N1 => "api.leancloud.cn"
    );

    private function __construct($appId) {
        $this->appId = $appId;
        $this->setRegion();
    }

    public static function getInstance($appId) {
        if (!self::INSTANCE) {
            self::INSTANCE = new AppRouter($appId);
        }
        return self::INSTANCE;
    }

    public function getHost($server_key) {
        validate_server_key($server_key);
        if ($host = $this->cacheGetHost($server_key)) {
            return $host;
        }
        $this->fetchRoutes();
        return $this->cacheGetHost($server_key);
    }

    private function validate_server_key($server_key) {
        if (!isset($this->getDefaultRoutes(), $server_key)) {
            throw new IllegalArgumentException("Invalid server key.");
        }
    }

    private function cacheGetHost($server_key) {
        return apcu_fetch("app_router:{$server_key}");
    }

    private function cacheSetHost($server_key, $host, $ttl=0) {
        acpu_store("app_router:{$server_key}", $host, $ttl);
    }

    /**
     * Fetch and catch routes
     */
    private function fetchRoutes() {
        $routes = $this->getRoutes();
        if (!$routes) {
            $routes = $this->getDefaultRoutes();
        }
        $ttl = $routes[self::TTL_KEY];
        unset($routes[self::TTL_KEY]);
        forEach($routes as $k => $v) {
            $this->cacheSetHost($k, $v, $ttl);
        }
    }

    /**
     * Set region according app-id
     */
    private function setRegion() {
        if (!$this->appId) {
            $this->region = Region::CN_N1;
            return true;
        }
        $parts = explode("-", $this->appId);
        if (count($parts) <= 1) {
            $this->region = Region::CN_N1;
        } else if ($parts[1] === "MdYXbMMI") {
            $this->region = Region::US;
        } else if ($parts[1] === "9Nh9j0Va") {
            $this->region = Region::CN_E1;
        } else {
            $this->region = Region::CN_N1;
        }
    }

    /**
     * Get routes from app router, return array.
     */
    private function getRoutes() {
        return json_decode(file_get_contents(self::ROUTER_URL . $this->appId));
    }

    /**
     * Fallback default routes, if app router not available.
     */
    private function getDefaultRoutes() {
        $host = self::$DEFAULT_REGION_HOST[$this->region];

        return array(
            self::API_SERVER_KEY        => $host,
            self::PUSH_SERVER_KEY       => $host,
            self::STATS_SERVER_KEY      => $host,
            self::ENGINE_SERVER_KEY     => $host,
            self::RTM_ROUTER_SERVER_KEY => $host,
            self::TTL_KEY               => 3600
        );
    }
}