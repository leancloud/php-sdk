<?php

namespace LeanCloud\Engine;

/**
 * LeanEngine as Slim middleware
 *
 * Add it in a Slim application:
 *
 * ```php
 * $app = new \Slim\App();
 * $app->add(new SlimEngine());
 * ```
 *
 * @link http://www.slimframework.com/docs/concepts/middleware.html
 */
class SlimEngine extends LeanEngine {

    /**
     * Get request header value
     *
     * @param string $key Header key
     * @return string
     */
    protected function getHeaderLine($key) {
        return $this->request->getHeaderLine($key);
    }

    /**
     * Get request body string
     *
     * @return string
     */
    protected function getBody() {
        return $this->request->getBody()->getContents();
    }

    /*
     * Ideally we would like to write to Slim response and send
     * the response to client. But we did not yet find a good way
     * to end the request as Slime middleware. As a work around,
     * we fallback to PHP native functions to do that. Pull request
     * is welcome.
     * 
     * @see LeanEngine::withHeader LeanEngine::send
     */
    // protected function withHeader($key, $val) {}
    // protected function send($key, $val) {}

    /**
     * Slim middleware entry point
     *
     * @param  \Psr\Http\Message\ServerRequestInterface $request  PSR7 request
     * @param  \Psr\Http\Message\ResponseInterface      $response PSR7 response
     * @param  callable                                 $next     Next middleware
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function __invoke($request, $response, $next) {
        $this->request  = $request;
        $this->response = $response;
        $this->dispatch($request->getMethod(),
                        $request->getUri());
        return $next($this->request, $this->response);
    }
}

