<?php

use LeanCloud\AppRouter;
use LeanCloud\Region;
use PHPUnit\Framework\TestCase;

class AppRouterTest extends TestCase {

    private function getShortAppId($appid) {
        return strtolower(substr($appid, 0, 8));
    }

    private function genId($length) {
        return substr(str_shuffle(MD5(rand())), 0, $length);
    }

    public function testSetRegion() {
        $appid = getenv("LEANCLOUD_APP_ID");
        $router = AppRouter::getInstance($this->genId(12));
        $router->setRegion("CN_E1");
        $router->setRegion("US");
        $router->setRegion(Region::CN_E1);
        $router->setRegion(Region::US);
    }

    public function testGetRoute() {
        $appid = getenv("LEANCLOUD_APP_ID");
        $router = AppRouter::getInstance($appid);
        $host = $router->getRoute(AppRouter::API_SERVER_KEY);
        $domain = getenv("LEANCLOUD_WILDCARD_DOMAIN");
        $this->assertEquals("{$this->getShortAppId($appid)}.api.{$domain}", $host);

        $host = $router->getRoute(AppRouter::ENGINE_SERVER_KEY);
        $domain = getenv("LEANCLOUD_WILDCARD_DOMAIN");
        $this->assertEquals("{$this->getShortAppId($appid)}.engine.{$domain}", $host);
    }

    public function testGetRouteWhenAppRouterNotAvailable() {
        $appid = $this->genId(18);
        $router = AppRouter::getInstance($appid);
        $router_url = getenv("LEANCLOUD_APP_ROUTER");
        putenv("LEANCLOUD_APP_ROUTER=http://localhost:4000/route?appId=");
        $this->assertEquals($router->getRegionDefaultRoute(AppRouter::ENGINE_SERVER_KEY),
                            $router->getRoute(AppRouter::ENGINE_SERVER_KEY));

        putenv("LEANCLOUD_APP_ROUTER={$router_url}");
        $host = $router->getRoute(AppRouter::API_SERVER_KEY);
        $this->assertEquals($router->getRegionDefaultRoute(AppRouter::API_SERVER_KEY),
                            $host);
    }

}