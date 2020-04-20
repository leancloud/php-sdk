<?php
namespace LeanCloud;

/**
 * Exception thrown when cloud API returns error
 */
class CloudException extends \Exception {

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
        $this->status = $status;
        $this->method = $method;
        $this->url    = $url;
    }

    public function __toString() {
        $req = $this->method ? ": {$this->method} {$this->url}": "";
        return __CLASS__ . ": [{$this->code}] {$this->message}{$req}\n";
    }
}

