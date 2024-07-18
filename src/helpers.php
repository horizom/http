<?php

use Horizom\Http\Request;
use Horizom\Http\Response;
use Psr\Http\Message\UriInterface;

if (!function_exists('response')) {
    /**
     * Returning a full Response instance
     *
     * Allows you to customize the response's HTTP status code and headers
     */
    function response(
        $status = 200,
        array $headers = [],
        $body = null,
        $version = '1.1',
        $reason = null
    ) {
        return new Response($status, $headers, $body, $version, $reason);
    }
}

if (!function_exists('request')) {
    /**
     * Create new Request instance
     *
     * @param string|null $method
     * @param string|UriInterface|null $uri
     * @param array $headers
     * @param mixed $body
     * @param mixed $version
     * @param array $serverParams
     * @return Request
     */
    function request(
        $method = null,
        $uri = null,
        array $headers = [],
        $body = null,
        $version = '1.1',
        array $serverParams = []
    ) {
        $method = $method ?? $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $uri = $uri ?? Request::getUriFromGlobals();
        $headers = array_merge(getallheaders(), $headers);

        return new Request($method, $uri, $headers, $body, $version, $serverParams);
    }
}

if (!function_exists('redirect')) {
    /**
     * Redirect to specified location
     *
     * This function prepares the response object to return an HTTP Redirect
     * response to the client.
     *
     * @param string    $url The redirect destination.
     * @param int|null  $status The redirect HTTP status code.
     */
    function redirect(string $url, ?int $status = null)
    {
        return (new Response())->redirect($url, $status);
    }
}
