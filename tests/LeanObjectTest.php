<?php

use LeanCloud\LeanObject;

class Movie extends LeanObject {
    protected static $leanClassName = "Movie";
}
Movie::registerClass();

class UnregisteredObject extends LeanObject{
    protected static $leanClassName = "UnregisteredObject";
}

class LeanObjectTest extends PHPUnit_Framework_TestCase {

    public function testInitializePlainObjectWithoutName() {
        $this->setExpectedException("InvalidArgumentException",
                                    "className is invalid.");
        new LeanObject();
    }

    public function testInitializeSubClass() {
        $movie = new Movie();
        $this->assertTrue($movie instanceof Movie);
        $this->assertTrue($movie instanceof LeanObject);
    }

    public function testInitializePlainObject() {
        $movie = new LeanObject("Movie");
        $this->assertFalse($movie instanceof Movie);
        $this->assertTrue($movie instanceof LeanObject);
    }

}

?>