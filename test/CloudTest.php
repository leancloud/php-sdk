<?php

use LeanCloud\Engine\Cloud;
use PHPUnit\Framework\TestCase;

class CloudTest extends TestCase {
    public function testGetKeys() {
        $name = uniqid();
        Cloud::define($name, function($params, $user) {
            return "hello";
        });
        $this->assertContains($name, Cloud::getKeys());
    }

    public function testDefineFunctionWithoutArg() {
        // user function are free to accept positional arguments,
        // this one should not error out.
        Cloud::define("hello", function() {
            return "hello";
        });
        $result = Cloud::run("hello", array("name" => "alice"), null);
        $this->assertEquals("hello", $result);
    }

    public function testFunctionWithoutArg() {
        Cloud::define("hello", function($params, $user) {
            return "hello";
        });

        $result = Cloud::run("hello", array(), null);
        $this->assertEquals("hello", $result);
    }

    public function testFunctionWithArg() {
        Cloud::define("sayHello", function($params, $user) {
            return "hello {$params['name']}";
        });

        $result = Cloud::run("sayHello", array("name" => "alice"), null);
        $this->assertEquals("hello alice", $result);
    }

    public function testFunctionAcceptMeta() {
        Cloud::define("getMeta", function($params, $user, $meta) {
            return $meta['remoteAddress'];
        });

        $result = Cloud::run("getMeta",
                             array("name" => "alice"),
                             null,
                             array("remoteAddress" => "10.0.0.1")
        );
        $this->assertEquals("10.0.0.1", $result);
    }

    public function testClassHook() {
        forEach(array("beforeSave", "afterSave",
                      "beforeUpdate", "afterUpdate",
                      "beforeDelete", "afterDelete") as $hookName) {
            $count = 42;
            call_user_func(
                array("LeanCloud\Engine\Cloud", $hookName),
                "TestObject",
                function($obj, $user) use (&$count) {
                    $count += 1;
                }
            );
            Cloud::runHook("TestObject", $hookName, null, null);
            $this->assertEquals(43, $count);
        }
    }

    public function testOnVerifiedHook() {
        // use a closure to ensure hook being executed
        $count = 42;
        Cloud::onVerified("sms", function($user) use (&$count) {
            $count += 1;
        });
        Cloud::runOnVerified("sms", null);
        $this->assertEquals(43, $count);
    }

    public function testOnLogin() {
        $count = 42;
        Cloud::onLogin(function($user) use (&$count) {
            $count += 1;
        });
        Cloud::runOnLogin(null);
        $this->assertEquals(43, $count);
    }

    public function testOnInsight() {
        $count = 42;
        Cloud::onInsight(function($job) use (&$count) {
            $count += 1;
        });
        Cloud::runOnInsight(null);
        $this->assertEquals(43, $count);
    }

}

