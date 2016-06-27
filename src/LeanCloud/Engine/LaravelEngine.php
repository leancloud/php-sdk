<?php

namespace LeanCloud\Engine;

/**
 * LeanEngine as Laravel middleware
 *
 * Add LaravelEngine::class to Laravel middleware stack:
 *
 * ```php
 * // in app/Http/Kernel.php
 * $middleware = [
 *     \LeanCloud\Engine\LaravelEngine::class
 * ];
 * ```
 *
 * @link https://laravel.com/docs/5.1/middleware
 */
class LaravelEngine extends LeanEngine {

    /**
     * Get request header value
     *
     * @param string $key Header key
     * @return string
     */
    protected function getHeaderLine($key) {
        return $this->request->header($key);
    }

    /**
     * Get request body string
     *
     * @return string
     */
    protected function getBody() {
        return $this->request->getContent();
    }

    /**
     * Laravel middleware entry point
     *
     * @param  \Illuminate\Http\Reuqest $request Laravel request
     * @param  \Closure                 $next    Laravel closure
     * @return mixed
     */
    public function handle($request, $next) {
        $this->request  = $request;
        $this->dispatch($request->method(),
                        $request->url());
        return $next($this->request);
    }
}

