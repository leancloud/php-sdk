<?php
namespace LeanCloud;

/**
 * Exception thrown when cloud API returns error
 */
class CloudException extends \Exception {
    public function __construct($message, $code = 0) {
        parent::__construct($message, $code);
    }

    public function __toString() {
        return __CLASS__ . ": [{$this->code}]: {$this->message}\n";
    }
}

