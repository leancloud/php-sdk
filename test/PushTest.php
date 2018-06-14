<?php

use LeanCloud\Client;
use LeanCloud\Query;
use LeanCloud\Push;
use PHPUnit\Framework\TestCase;

class PushTest extends TestCase {

    public function setUp() {
        Client::initialize(
            getenv("LEANCLOUD_APP_ID"),
            getenv("LEANCLOUD_APP_KEY"),
            getenv("LEANCLOUD_APP_MASTER_KEY"));

        Client::useMasterKey(false);
    }

    public function testMessageEncode() {
        $data = array(
            "alert" => "Hello world!",
            "badge" => 20,
            "sound" => "APP/media/sound.mp3"
        );
        $push = new Push($data);
        $out = $push->encode();
        $this->assertEquals($data, $out["data"]);
    }

    public function testSetData() {
        $push = new Push(array(
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

    public function testSetPushForMultiplatform() {
        $data = array(
            "ios" => array(
                "alert" => "Hello world!",
                "badge" => 20,
                "sound" => "APP/media/sound.mp3"
            ),
            "android" => array(
                "alert" => "Hello world!",
                "title" => "Hello from app",
                "action" => "app.action"
            ),
            "wp" => array(
                "alert" => "Hello world!",
                "title" => "Hello from app",
                "wp-param" => "/chat.xaml?NavigatedFrom=Toast Notification"
            )
        );
        $push = new Push($data);
        $out = $push->encode();
        $this->assertEquals($data, $out["data"]);
    }

    public function testDefaultProd() {
        $push = new Push(array(
            "alert" => "Hello world!"
        ));
        $out = $push->encode();
        $this->assertEquals(Client::$isProduction, $out["prod"] == "prod");
    }

    public function testSetProd() {
        $push = new Push(array(
            "alert" => "Hello world!"
        ));
        $push->setOption("prod", "dev");
        $out = $push->encode();
        $this->assertEquals("dev", $out["prod"]);
    }
    
    public function testSetChannels() {
        $push = new Push(array(
            "alert" => "Hello world!"
        ));
        $channels = array("vip", "premium");
        $push->setChannels($channels);
        $out = $push->encode();
        $this->assertEquals($channels, $out["channels"]);
    }

    public function testSetPushTime() {
        $push = new Push(array(
            "alert" => "Hello world!"
        ));
        $time = new DateTime();
        $push->setPushTime($time);
        $out = $push->encode();
        $time2 = new DateTime($out["push_time"]);
        $this->assertEquals($time->getTimestamp(), $time2->getTimestamp());
    }

    public function testSetExpirationInterval() {
        $push = new Push(array(
            "alert" => "Hello world!"
        ));
        $push->setExpirationInterval(86400);
        $out = $push->encode();
        $this->assertEquals(86400, $out["expiration_interval"]);
    }

    public function testSetExpirationTime() {
        $push = new Push(array(
            "alert" => "Hello world!"
        ));
        $date = new DateTime();
        $push->setExpirationTime($date);
        $out = $push->encode();
        $date2 = new DateTime($out["expiration_time"]);
        $this->assertEquals($date->getTimestamp(), $date2->getTimestamp());
    }

    public function testSetWhere() {
        $push = new Push(array(
            "alert" => "Hello world!"
        ));
        $query = new Query("_Installation"); 
        $date = new DateTime();
        $query->lessThan("updatedAt", $date);
        $push->setWhere($query);
        $out = $push->encode();
        $this->assertEquals(array(
            "updatedAt" => array(
                '$lt' => Client::encode($date)
            )
        ), $out["where"]);
    }

    public function testSendPush() {
        $push = new Push(array(
            "alert" => "Hello world!"
        ));
        $query = new Query("_Installation");
        $query->equalTo("deviceType", "Android");
        $push->setWhere($query);

        $at = new DateTime();
        $at->add(new DateInterval("P1D"));
        $push->setPushTime($at);

        // $push->send();
    }
}
