<?php

declare(strict_types=1);

namespace Horizom\Http;

use Horizom\Http\Exceptions\HttpException;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;

class Request extends \Nyholm\Psr7\ServerRequest
{
    use RequestInputTrait;

    private string $basePath = '';

    private string $uriPath = '';

    private string $baseUrl = '';

    private string $url = '';

    private string $fullUrl = '';

    /** @var \Closure|null */
    protected $routeResolver;

    /**
     * @param string                               $method       HTTP method
     * @param string|UriInterface                  $uri          URI
     * @param array<string, string|string[]>       $headers      Request headers
     * @param string|resource|StreamInterface|null $body         Request body
     * @param string                               $version      Protocol version
     * @param array<string, mixed>                 $serverParams Server parameters
     */
    public function __construct(
        $method,
        $uri,
        array $headers = [],
        $body = null,
        $version = '1.1',
        array $serverParams = []
    ) {
        parent::__construct($method, $uri, $headers, $body, $version, $serverParams);
        $this->setBasePath('');
    }

    /**
     * Create a new Request from PHP global variables.
     */
    public static function create(): static
    {
        $headers = getallheaders() ?: [];
        $uri = self::getUriFromGlobals();
        $body = fopen('php://input', 'r') ?: null;
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $protocol = isset($_SERVER['SERVER_PROTOCOL'])
            ? str_replace('HTTP/', '', $_SERVER['SERVER_PROTOCOL'])
            : '1.1';

        return new static($method, $uri, $headers, $body, $protocol, $_SERVER);
    }

    /**
     * Alias for create(). Captures the current request.
     */
    public static function capture(): static
    {
        return static::create();
    }

    /**
     * Set the base path for the request.
     *
     * @return static
     */
    public function setBasePath(string $basePath): static
    {
        $uri = $this->getUri();
        $path = trim($basePath, '/');
        $host = $uri->getHost();
        $port = $uri->getPort();
        $query = $uri->getQuery();

        if ($port !== null && !in_array($port, [80, 443], true)) {
            $host = $host . ':' . $port;
        }

        $baseUri = $path !== '' ? $host . '/' . $path : $host;
        $requestUri = $uri->getPath();

        $this->basePath = $basePath;
        $this->uriPath = str_replace($basePath, '', $requestUri);
        $this->baseUrl = $uri->getScheme() . '://' . $baseUri;
        $this->fullUrl = $this->url = $uri->getScheme() . '://' . $host . $requestUri;

        if ($query !== '') {
            $this->fullUrl = $this->url . '?' . $query;
        }

        return $this;
    }

    /**
     * Return the request's path information.
     */
    public function path(): string
    {
        return $this->getUri()->getPath();
    }

    /**
     * Get the request method.
     */
    public function method(): string
    {
        return $this->getMethod();
    }

    /**
     * Verify that the HTTP verb matches a given string.
     */
    public function isMethod(string $method): bool
    {
        return $this->getMethod() === strtoupper($method);
    }

    /**
     * Retrieve a single header value by the given case-insensitive name.
     *
     * @param mixed $default
     * @return mixed
     */
    public function header(string $key, $default = null)
    {
        $entries = $this->getHeader($key);
        return $entries !== [] ? $entries[0] : $default;
    }

    /**
     * Retrieve all headers from the request.
     *
     * @return array<string, string[]>
     */
    public function headers(): array
    {
        return $this->getHeaders();
    }

    /**
     * Retrieve a bearer token from the Authorization header.
     */
    public function bearerToken(): ?string
    {
        $header = $this->getHeaderLine('Authorization');

        if ($header === '' || !str_starts_with($header, 'Bearer ')) {
            return null;
        }

        return trim(substr($header, 7));
    }

    /**
     * Get the root URL for the application.
     */
    public function root(): string
    {
        return rtrim($this->baseUrl(), '/');
    }

    /**
     * Return the URL without the query string.
     */
    public function url(): string
    {
        return $this->url;
    }

    /**
     * Return the URL including the query string.
     */
    public function fullUrl(): string
    {
        return $this->fullUrl;
    }

    /**
     * Determine if the current request URI matches one or more patterns.
     */
    public function is(string ...$patterns): bool
    {
        foreach ($patterns as $pattern) {
            if (Str::is($pattern, $this->uriPath)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Return the base URL.
     */
    public function baseUrl(): string
    {
        return $this->baseUrl;
    }

    /**
     * Return the base path.
     */
    public function basePath(): string
    {
        return $this->basePath;
    }

    /**
     * Get the client user agent.
     */
    public function userAgent(): ?string
    {
        $header = $this->getHeader('User-Agent');
        return $header !== [] ? $header[0] : null;
    }

    /**
     * Get the route handling the request.
     *
     * @param string|null $param
     * @param mixed       $default
     * @return mixed
     */
    public function route(?string $param = null, $default = null)
    {
        $route = ($this->getRouteResolver())();

        if ($route === null || $param === null) {
            return $route;
        }

        return Arr::get($route[2], $param, $default);
    }

    /**
     * Get a unique fingerprint for the request / route / IP address.
     *
     * @throws HttpException
     */
    public function fingerprint(): string
    {
        if (!$this->route()) {
            throw new HttpException('Unable to generate fingerprint. Route unavailable.');
        }

        return sha1(implode('|', [
            $this->getMethod(),
            $this->root(),
            $this->path(),
            $this->ip(),
        ]));
    }

    /**
     * Get the client IP address.
     *
     * Checks Cloudflare CF-Connecting-IP, then X-Forwarded-For, then REMOTE_ADDR.
     * Note: proxy headers can be spoofed — validate against a trusted proxy list in production.
     */
    public function ip(): ?string
    {
        $cf = $this->getHeaderLine('CF-Connecting-IP');
        if ($cf !== '') {
            return $cf;
        }

        $forwarded = $this->getHeaderLine('X-Forwarded-For');
        if ($forwarded !== '') {
            return trim(explode(',', $forwarded)[0]);
        }

        $serverParams = $this->getServerParams();
        return isset($serverParams['REMOTE_ADDR']) ? (string) $serverParams['REMOTE_ADDR'] : null;
    }

    /**
     * Determine if the request is over HTTPS.
     */
    public function secure(): bool
    {
        return $this->isSecure();
    }

    /**
     * Check if the request connection is secure.
     */
    public function isSecure(): bool
    {
        if ($this->getHeaderLine('X-Forwarded-Proto') === 'https') {
            return true;
        }

        $serverParams = $this->getServerParams();
        $https = $serverParams['HTTPS'] ?? '';
        $port = isset($serverParams['SERVER_PORT']) ? (int) $serverParams['SERVER_PORT'] : null;

        return (!empty($https) && strtolower((string) $https) !== 'off')
            || $port === 443;
    }

    /**
     * Determine if the request is the result of an AJAX call.
     */
    public function ajax(): bool
    {
        return $this->isXmlHttpRequest();
    }

    /**
     * Determine if the request is the result of a PJAX call.
     */
    public function pjax(): bool
    {
        return $this->getHeaderLine('X-PJAX') !== '';
    }

    /**
     * Determine if the request is an XMLHttpRequest.
     */
    public function isXmlHttpRequest(): bool
    {
        return strtolower($this->getHeaderLine('X-Requested-With')) === 'xmlhttprequest';
    }

    /**
     * Determine if the request Content-Type is application/json.
     */
    public function isJson(): bool
    {
        return str_contains($this->getHeaderLine('Content-Type'), 'application/json');
    }

    /**
     * Determine if the request Content-Type is text/html.
     */
    public function isHtml(): bool
    {
        return str_contains($this->getHeaderLine('Content-Type'), 'text/html');
    }

    /**
     * Determine if the client expects a JSON response.
     */
    public function wantsJson(): bool
    {
        $accept = $this->getHeaderLine('Accept');
        return $accept !== '' && str_contains($accept, 'application/json');
    }

    /**
     * Determine if the client does not expect a JSON response.
     */
    public function exceptJson(): bool
    {
        return !$this->wantsJson();
    }

    /**
     * Get the route resolver callback.
     */
    public function getRouteResolver(): \Closure
    {
        return $this->routeResolver ?? static function (): null {
            return null;
        };
    }

    /**
     * Set the route resolver callback.
     *
     * @return static
     */
    public function setRouteResolver(\Closure $callback): static
    {
        $this->routeResolver = $callback;
        return $this;
    }

    /**
     * Build a Uri populated with values from $_SERVER.
     */
    public static function getUriFromGlobals(): UriInterface
    {
        $uri = new Uri('');
        $uri = $uri->withScheme(
            !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http'
        );

        $hasPort = false;
        if (isset($_SERVER['HTTP_HOST'])) {
            [$host, $port] = self::extractHostAndPortFromAuthority($_SERVER['HTTP_HOST']);
            if ($host !== null) {
                $uri = $uri->withHost($host);
            }
            if ($port !== null) {
                $hasPort = true;
                $uri = $uri->withPort($port);
            }
        } elseif (isset($_SERVER['SERVER_NAME'])) {
            $uri = $uri->withHost($_SERVER['SERVER_NAME']);
        } elseif (isset($_SERVER['SERVER_ADDR'])) {
            $uri = $uri->withHost($_SERVER['SERVER_ADDR']);
        }

        if (!$hasPort && isset($_SERVER['SERVER_PORT'])) {
            $uri = $uri->withPort((int) $_SERVER['SERVER_PORT']);
        }

        $hasQuery = false;
        if (isset($_SERVER['REQUEST_URI'])) {
            $requestUriParts = explode('?', $_SERVER['REQUEST_URI'], 2);
            $uri = $uri->withPath($requestUriParts[0]);
            if (isset($requestUriParts[1])) {
                $hasQuery = true;
                $uri = $uri->withQuery($requestUriParts[1]);
            }
        }

        if (!$hasQuery && isset($_SERVER['QUERY_STRING'])) {
            $uri = $uri->withQuery($_SERVER['QUERY_STRING']);
        }

        return $uri;
    }

    /**
     * @return array{0: string|null, 1: int|null}
     */
    private static function extractHostAndPortFromAuthority(string $authority): array
    {
        $parts = parse_url('http://' . $authority);
        if ($parts === false) {
            return [null, null];
        }

        return [$parts['host'] ?? null, $parts['port'] ?? null];
    }
}
