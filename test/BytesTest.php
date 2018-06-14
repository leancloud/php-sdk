<?php

use LeanCloud\Bytes;
use PHPUnit\Framework\TestCase;

class BytesTest extends TestCase {
    public function testEncodeEmptyArray() {
        $bytes = Bytes::createFromByteArray(array());
        $out   = $bytes->encode();
        $this->assertEquals("Bytes", $out["__type"]);
        $this->assertEquals("", $out["base64"]);
    }

    public function testEncodeArray() {
        $bytes = Bytes::createFromByteArray(array(72, 101, 108, 108, 111));
        $out   = $bytes->encode();
        $this->assertEquals("Bytes", $out["__type"]);
        $this->assertEquals(base64_encode("Hello"), $out["base64"]);
    }

    public function testCreateFromEmptyString() {
        $bytes = Bytes::createFromBase64Data(base64_encode(""));
        $this->assertEmpty($bytes->getByteArray());
        $this->assertEquals("", $bytes->asString());
    }

    public function testCreateFromBase64() {
        $bytes  = Bytes::createFromByteArray(array(72, 101, 108, 108, 111));
        $bytes1 = Bytes::createFromBase64Data(base64_encode("Hello"));
        $this->assertEquals($bytes->getByteArray(), $bytes1->getByteArray());
        $this->assertEquals("Hello", $bytes->asString());
        $this->assertEquals("Hello", $bytes1->asString());
    }

    public function testEncodeCreateFromBase64() {
        $bytes = Bytes::createFromBase64Data(base64_encode("Hello"));
        $out   = $bytes->encode();
        $this->assertEquals(base64_encode("Hello"), $out["base64"]);
    }
}

