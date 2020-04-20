<?php
namespace LeanCloud;

/**
 * BatchRequestError
 *
 * A BatchRequestError object consists of zero or more request and
 * response errors.
 *
 */
class BatchRequestError extends CloudException {

    /**
     * Array of error response
     *
     * @var array
     */
    private $errors = [];

    public function __construct($message="", $code = 1) {
        $message = empty($message) ? "Batch request error." : $message;
        parent::__construct($message, $code);
    }

    /**
     * Add failed request and its error response
     *
     * Both request and response are expected to be array. The response
     * array must contain an `error` message, while the request should
     * contain `method` and `path`.
     *
     * @return BatchRequestError
     */
    public function add($request, $response) {
        $error["code"] = isset($response["code"]) ? $response["code"] : 1;
        $error["error"] = "{$error['code']} {$response['error']}:"
                        . json_encode($request);
        $this->errors[] = $error;
        return $this;
    }

    /**
     * Get all error response
     *
     * @return array
     */
    public function getAll() {
        return $this->errors;
    }

    /**
     * Get first error response as map
     *
     * Returns associative array of following format:
     *
     * `{"code": 101, "error": "error message", "request": {...}}`
     *
     * @return array|null
     */
    public function getFirst() {
        return isset($this->errors[0]) ? $this->errors[0] : null;
    }

    /**
     * Contains error response or not
     *
     * @return bool
     */
    public function isEmpty() {
        return count($this->errors) == 0;
    }

    public function __toString() {
        $message = $this->message;
        if (!$this->isEmpty()) {
            $message .= json_encode($this->errors);
        }
        return __CLASS__ . ": [{$this->code}]: {$message}\n";
    }
}

