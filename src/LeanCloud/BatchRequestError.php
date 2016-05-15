<?php
namespace LeanCloud;

/**
 * Exception thrown when doing batch request
 */
class BatchRequestError extends CloudException {

    /**
     * Array of error response
     *
     * @var array
     */
    private $errors;
    
    public function __construct($message="", $code = 1) {
        $message = empty($message) ? "Batch request error." : $message;
        parent::__construct($message, $code);
    }

    /**
     * Add a request and error response pair
     *
     * Both request and response are expected to be array. The response
     * array must contain an `error` message, while the request should
     * contain `method` and `path`.
     *
     * @return BatchRequestError
     */
    public function add($request, $response) {
        if (!isset($response["error"])) {
            throw new \InvalidArgumentException("Invalid error response.");
        }
        if (!isset($response["code"])) {
            $response["code"] = 1;
        }
        $response["request"] = $request;
        $this->errors[] = $response;
        return $this;
    }

    /**
     * Get first error as array that contains request and error response
     *
     * It returns null if there are no errors yet.
     *
     * @return array|null
     */
    public function getFirst() {
        return isset($this->errors[0]) ? $this->errors[0] : null;
    }

    /**
     * Contains error or not
     *
     * @return bool
     */
    public function empty() {
        return count($this->errors) == 0;
    }

    public function __toString() {
        $message = $this->message;
        if (!$this->empty()) {
            $message .= json_encode($this-errors);
        }
        return __CLASS__ . ": [{$this->code}]: {$message}\n";
    }
}

