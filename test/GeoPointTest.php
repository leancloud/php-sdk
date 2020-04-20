<?php

use LeanCloud\GeoPoint;
use PHPUnit\Framework\TestCase;

class GeoPointTest extends TestCase {

    public function testInitializeDefaultGeoPoint() {
        $point = new GeoPoint();
        $this->assertEquals(0.0, $point->getLatitude());
        $this->assertEquals(0.0, $point->getLongitude());
    }

    public function testInitializeGeoPoint() {
        $point = new GeoPoint(90, 180);
        $this->assertEquals(90, $point->getLatitude());
        $this->assertEquals(180, $point->getLongitude());

        $point = new GeoPoint(90, -180);
        $point = new GeoPoint(-90, 180);
        $point = new GeoPoint(-90, -180);
    }

    public function testEncodeGeoPoint() {
        $point = new GeoPoint(39.9, 116.4);

        $out = $point->encode();
        $this->assertEquals("GeoPoint", $out["__type"]);
        $this->assertEquals(39.9, $out["latitude"]);
        $this->assertEquals(116.4, $out["longitude"]);
    }

    public function testInvalidPoints() {
        $this->setExpectedException("InvalidArgumentException",
                                    "Invalid latitude or longitude " .
                                    "for geo point");
        new GeoPoint(180, 90);
    }

    public function testRadiansDistance() {
        $point = new GeoPoint(39.9, 116.4);
        $this->assertEquals(0, $point->radiansTo($point));

        // it should be equal to to M_PI
        $rad = $point->radiansTo(new GeoPoint(-39.9, -63.6));
        $this->assertEquals(M_PI, $rad, '', 0.0000001);

        $rad = $point->radiansTo(new GeoPoint(0, 116.4));
        $this->assertEquals(39.9 * M_PI / 180.0, $rad, '', 0.0000001);

    }
}
