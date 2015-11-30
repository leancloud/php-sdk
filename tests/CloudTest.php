<?php

use LeanCloud\Engine\Cloud;

class CloudTest extends PHPUnit_Framework_TestCase {
    public function testGetKeys() {
        $name = uniqid();
        Cloud::define($name, function($params, $user) {
            return "hello";
        });
        $this->assertContains($name, Cloud::getKeys());
    }

    public function testFunctionWithoutArg() {
        Cloud::define("hello", function($params, $user) {
            return "hello";
        });

        $result = Cloud::runFunc("hello", array(), null);
        $this->assertEquals("hello", $result);
    }

    public function testFunctionWithArg() {
        Cloud::define("sayHello", function($params, $user) {
            return "hello {$params['name']}";
        });

        $result = Cloud::runFunc("sayHello", array("name" => "alice"), null);
        $this->assertEquals("hello alice", $result);
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

    public function testAfterSave() {
        $count = 42;
        Cloud::afterSave("TestObject", function($obj, $user) use (&$count) {
            $count += 1;
        });
        Cloud::runHook("TestObject", "afterSave", null, null);
        $this->assertEquals(43, $count);
    }

    public function testBeforeUpdate() {
        $count = 42;
        Cloud::beforeUpdate("TestObject", function($obj, $user) use (&$count) {
            $count += 1;
        });
        Cloud::runHook("TestObject", "beforeUpdate", null, null);
        $this->assertEquals(43, $count);
    }

    public function testAfterUpdate() {
        $count = 42;
        Cloud::afterUpdate("TestObject", function($obj, $user) use (&$count) {
            $count += 1;
        });
        Cloud::runHook("TestObject", "afterUpdate", null, null);
        $this->assertEquals(43, $count);
    }

}

