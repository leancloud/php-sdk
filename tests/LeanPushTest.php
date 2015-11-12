<?php

use LeanCloud\LeanClient;
use LeanCloud\LeanQuery;
use LeanCloud\LeanPush;

class LeanPushTest extends PHPUnit_Framework_TestCase {
    public function testMessageEncode() {
        $data = array(
            "alert" => "Hello world!",
            "badge" => 20,
            "sound" => "APP/media/sound.mp3"
        );
        $push = new LeanPush($data);
        $out = $push->encode();
        $this->assertEquals($data, $out["data"]);
    }

    public function testSetData() {
        $push = new LeanPush(array(
            "alert" => "Hello world!"
        ));
        $push->setData("badge", 20);
        $push->setData("sound", "APP/media/sound.mp3");
        $out = $push->encode();
        $this->assertEquals(array(
            "alert" => "Hello world!",
            "badge" => 20,
            "sound" => "APP/media/sound.mp3"
        ), $out["data"]);
    }

    public function testSetProd() {
        $push = new LeanPush(array(
            "alert" => "Hello world!"
        ));
        $push->setOption("prod", "dev");
        $out = $push->encode();
        $this->assertEquals("dev", $out["prod"]);
    }
    
    public function testSetChannels() {
        $push = new LeanPush(array(
            "alert" => "Hello world!"
        ));
        $channels = array("vip", "premium");
        $push->setChannels($channels);
        $out = $push->encode();
        $this->assertEquals($channels, $out["channels"]);
    }

    public function testSetPushTime() {
        $push = new LeanPush(array(
            "alert" => "Hello world!"
        ));
        $time = new DateTime();
        $push->setPushTime($time);
        $out = $push->encode();
        $this->assertEquals($time, $out["push_time"]);
    }

    public function testSetExpirationInterval() {
        $push = new LeanPush(array(
            "alert" => "Hello world!"
        ));
        $push->setExpirationInterval(86400);
        $out = $push->encode();
        $this->assertEquals(86400, $out["expiration_interval"]);
    }

    public function testSetExpirationTime() {
        $push = new LeanPush(array(
            "alert" => "Hello world!"
        ));
        $date = new DateTime();
        $push->setExpirationTime($date);
        $out = $push->encode();
        $this->assertEquals($date, $out["expiration_time"]);
    }

    public function testSetWhere() {
        $push = new LeanPush(array(
            "alert" => "Hello world!"
        ));
        $query = new LeanQuery("_Installation"); 
        $date = new DateTime();
        $query->lessThan("updatedAt", $date);
        $push->setWhere($query);
        $out = $push->encode();
        $this->assertEquals(array(
            "updatedAt" => array(
                '$lt' => LeanClient::encode($date)
            )
        ), $out["where"]);
    }
}