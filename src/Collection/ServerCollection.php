<?php

declare(strict_types=1);

namespace Horizom\Http\Collection;

use Illuminate\Support\Collection;

/**
 * ServerDataCollection
 *
 * A DataCollection for "$_SERVER" like data
 *
 * Look familiar?
 *
 * Inspired by @fabpot's Symfony 2's HttpFoundation
 * @link https://github.com/symfony/HttpFoundation/blob/master/ServerBag.php
 */
class ServerCollection extends Collection
{

    /**
     * Class properties
     */

    /**
     * The prefix of HTTP headers normally
     * stored in the Server data
     *
     * @type string
     */
    protected static $http_header_prefix = 'HTTP_';

    /**
     * The list of HTTP headers that for some
     * reason aren't prefixed in PHP...
     *
     * @type array
     */
    protected static $http_nonprefixed_headers = array(
        'CONTENT_LENGTH',
        'CONTENT_TYPE',
        'CONTENT_MD5',
    );


    /**
     * Methods
     */

    /**
     * Quickly check if a string has the given prefix.
     */
    public static function hasPrefix(string $string, string $prefix): bool
    {
        return str_starts_with($string, $prefix);
    }

    /**
     * Get HTTP headers from the server data collection.
     *
     * PHP stores HTTP request headers in $_SERVER with the HTTP_ prefix.
     * This method normalises them back to standard header names.
     *
     * @return array<string, mixed>
     */
    public function getHeaders(): array
    {
        $headers = [];

        foreach ($this->all() as $key => $value) {
            if (self::hasPrefix($key, self::$http_header_prefix)) {
                $headers[substr($key, strlen(self::$http_header_prefix))] = $value;
            } elseif (in_array($key, self::$http_nonprefixed_headers, true)) {
                $headers[$key] = $value;
            }
        }

        return $headers;
    }
}
