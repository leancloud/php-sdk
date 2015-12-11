<?php

namespace LeanCloud\Engine;

/**
 * Proxy-aware https redirect middleware
 */

class HttpsRedirect {
    public static function redirect($permanet=false) {
        $reqProto = "http";
        $reqHost  = $_SERVER['HTTP_HOST'];
        if (isset($_SERVER["HTTP_X_FORWARDED_PROTO"])) {
            // behind proxy
            $reqProto = $_SERVER["HTTP_X_FORWARDED_PROTO"];
            if (isset($_SERVER["HTTP_X_FORWARDED_HOST"])) {
                $reqHost = $_SERVER["HTTP_X_FORWARDED_HOST"];
            }
        } else {
            // Note: By default it will be set non-empty for https request,
            // though ISAPI with IIS sets it as "off" to indicate non-secure
            // request.
            if (!empty($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] != "off")){
                $reqProto = "https";
            }
        }

        $prod = (getenv("LC_APP_ENV") == "production");
        if ($prod && $reqProto != "https") {
            $url = "https://{$reqHost}{$_SERVER['REQUEST_URI']}";
            if ($permanet) {
                http_response_code(301);
            } else {
                http_response_code(302);
            }
            header("Location: {$url}");
            exit;
        }
    }

    /**
     * Redirect Laravel request
     *
     * It exposes class as a Laravel middleware, which can be
     * registered in Laravel application. E.g. in
     * `app/Http/Kernel.php`:
     *
     * ```php
     * class Kernel extends HttpKernel {
     *     protected $middleware = [
     *         ...,
     *         \LeanCloud\Engine\HttpsRedirect::class,
     *     ];
     * }
     * ```
     *
     * @param Request  $request Laravel request
     * @param Callable $next    Laravel Closure
     * @return mixed
     * @link http://laravel.com/docs/5.1/middleware
     *
     */
    public function handle($request, $next) {
        static::redirect();
        return $next($request);
    }
}