<?php

namespace LeanCloud;

/**
 * Byte array data type for LeanObject
 */
class Bytes {
    /**
     * Byte array
     *
     * @var array
     */
    private $byteArray = array();

    /**
     * Create Bytes from byte array
     *
     * @param array $byteArray
     * @return Bytes
     */
    public static function createFromByteArray(array $byteArray) {
        $bytes = new Bytes();
        $bytes->byteArray = $byteArray;
        return $bytes;
    }

    /**
     * Create Bytes from base64 encoded string
     *
     * @param string $data Base64 encoded string
     * @return Bytes
     */
    public static function createFromBase64Data($data) {
        $bytes = new Bytes();

        // convert unpacked associative array to sequence array
        $byteMap = unpack('C*', base64_decode($data));
        forEach($byteMap as $byte) {
            $bytes->byteArray[] .= $byte;
        }
        return $bytes;
    }

    /**
     * Get byte array
     *
     * @return array
     */
    public function getByteArray() {
        return $this->byteArray;
    }

    /**
     * Get string representation of byte array
     *
     * @return string
     */
    public function asString() {
        $str = "";
        forEach($this->byteArray as $byte) {
            $str .= chr($byte);
        }
        return $str;
    }

    /**
     * Encode to LeanCloud bytes type
     *
     * @return array
     */
    public function encode() {
        return array(
            "__type" => "Bytes",
            "base64" => base64_encode($this->asString()));
    }

}

