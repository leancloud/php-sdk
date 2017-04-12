<?php
namespace LeanCloud\Engine;

/**
 * Error thrown when invoking function error
 */
class FunctionError extends \Exception {
    public function __construct($message, $code = 1) {
        parent::__construct($message, $code);
    }

    public function __toString() {
        return __CLASS__ . ": [{$this->code}]: {$this->message}\n";
    }
}
