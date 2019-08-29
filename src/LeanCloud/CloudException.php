<?php
namespace LeanCloud;

/**
 * Exception thrown when cloud API returns error
 */
class CloudException extends \Exception {
    /**
     * Error code returned by API
     *
     * @var int
     */
    public $code;

    /**
     * Error message returned by API
     *
     * @var string
     */
    public $message;

    /**
     * Http status returned by API
     *
     * @var int
     */
    public $status;

    /**
     * Http method request to API
     *
     * @var string
     */
    public $method;

    /**
     * Http url request to API
     *
     * @var string
     */
    public $url;

    public function __construct($message, $code = 1, $status = 400,
                                $method=null, $url=null) {
        parent::__construct($message, $code);
    }

    public function __toString() {
        $req = $method ? "{$method} {$url}": "";
        return __CLASS__ . ": [{$this->code}] {$this->message} ${req}\n";
    }
}

