<?php
namespace LeanCloud\Engine;

/**
 * Error thrown when invoking function error
 */
class FunctionError extends \Exception {

    /**
     * Http status code
     */
    public $status;

    public function __construct($message, $code = 1, $status = 400) {
        parent::__construct($message, $code);
        $this->status = $status;
    }

    public function __toString() {
        return __CLASS__ . ": [{$this->code}] {$this->message}\n";
    }
}
