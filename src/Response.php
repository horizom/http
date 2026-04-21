<?php

declare(strict_types=1);

namespace Horizom\Http;

use Horizom\Http\Exceptions\HttpException;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use InvalidArgumentException;

class Response extends \Nyholm\Psr7\Response
{
    /**
     * @var ResponseFactoryInterface
     * */
    protected $responseFactory;

    /**
     * @var StreamFactoryInterface
     */
    protected $streamFactory;

    protected string $baseUrl = '';

    /**
     * @var Psr17Factory
     */
    protected static $factory;

    /**
     * @inherit
     */
    public function __construct(int $status = 200, array $headers = [], $body = null, string $version = '1.1', ?string $reason = null)
    {
        parent::__construct($status, $headers, $body, $version, $reason);

        self::$factory = new Psr17Factory();
        $this->baseUrl = defined('HORIZOM_BASE_URL') ? HORIZOM_BASE_URL : '';
    }

    /**
     * Create new response
     *
     * @return static
     */
    public static function create(int $status = 200, array $headers = [], $body = null, string $version = '1.1', ?string $reason = null): static
    {
        return new self($status, $headers, $body, $version, $reason);
    }

    /**
     * Create new response from instance
     *
     * @param ResponseInterface $response
     * @return static
     */
    public static function fromInstance(ResponseInterface $response): static
    {
        $status = $response->getStatusCode();
        $headers = $response->getHeaders();
        $body = $response->getBody();
        $version = $response->getProtocolVersion();
        $reason = $response->getReasonPhrase();

        return new self($status, $headers, $body, $version, $reason);
    }

    /**
     * Set the base URL
     *
     * This method allows you to specify a base URL that will be used when generating redirect responses.
     * If a relative URL is provided to the redirect methods, it will be prefixed with this base URL.
     *
     * @param string $baseUrl The base URL to set for redirects.
     * @return $this
     */
    public function setBaseUrl(string $baseUrl): static
    {
        $this->baseUrl = $baseUrl;
        return $this;
    }

    /**
     * Redirect to specified location
     *
     * This method prepares the response object to return an HTTP Redirect
     * response to the client.
     *
     * @param string    $url The redirect destination.
     * @param int|null  $status The redirect HTTP status code.
     */
    public function redirect(string $url, ?int $status = null): ResponseInterface
    {
        $status = $status ?? 302;
        return static::create()->withHeader('Location', $url)->withStatus($status);
    }

    /**
     * Redirect to specified location
     *
     * This method prepares the response object to return an HTTP Redirect
     * response to the client.
     *
     * @param string    $url The redirect destination.
     * @param int|null  $status The redirect HTTP status code.
     */
    public function redirectWithBaseUrl($url = null, int $status = null): ResponseInterface
    {
        $url = (is_null($url)) ? $this->baseUrl : $this->baseUrl . '/' . trim($url, '/');
        return $this->redirect($url, $status);
    }

    /**
     * Return a view as the response's content
     */
    public function view(string $name, array $data = [], $contentType = 'text/html'): ResponseInterface
    {
        $content = (string) \Horizom\Core\Facades\View::make($name, $data)->render();
        $body = self::$factory->createStream($content);
        $response = clone $this;

        return $response->withHeader('Content-type', $contentType)->withBody($body);
    }

    /**
     * Write JSON to the response body.
     *
     * @param mixed $data
     * @throws HttpException if the data cannot be JSON-encoded
     */
    public function json($data, ?int $status = null, int $options = 0, int $depth = 512): ResponseInterface
    {
        try {
            $json = json_encode($data, $options | JSON_THROW_ON_ERROR, $depth);
        } catch (\JsonException $e) {
            throw new HttpException($e->getMessage(), $e->getCode(), $e);
        }

        $new = clone $this;
        $body = self::$factory->createStream($json);
        $response = $new->withHeader('Content-Type', 'application/json')->withBody($body);

        if ($status !== null) {
            $response = $response->withStatus($status);
        }

        return $response;
    }

    /**
     * This method will trigger the client to download the specified file
     * It will append the `Content-Disposition` header to the response object
     *
     * @param string|resource|StreamInterface $file
     * @param string|null $name
     * @param bool|string $contentType
     */
    public function download($file, string $name = null, $contentType = true): ResponseInterface
    {
        $disposition = 'attachment';
        $fileName = $name;

        if (is_string($file) && $name === null) {
            $fileName = basename($file);
        }

        if ($name === null && (is_resource($file) || $file instanceof StreamInterface)) {
            $metaData = $file instanceof StreamInterface
                ? $file->getMetadata()
                : stream_get_meta_data($file);

            if (is_array($metaData) && isset($metaData['uri'])) {
                $uri = $metaData['uri'];
                if ('php://' !== substr($uri, 0, 6)) {
                    $fileName = basename($uri);
                }
            }
        }

        if (is_string($fileName) && strlen($fileName)) {
            /*
             * The regex used below is to ensure that the $fileName contains only
             * characters ranging from ASCII 128-255 and ASCII 0-31 and 127 are replaced with an empty string
             */
            $disposition .= '; filename="' . preg_replace('/[\x00-\x1F\x7F\"]/', ' ', $fileName) . '"';
            $disposition .= "; filename*=UTF-8''" . rawurlencode($fileName);
        }

        $response = clone $this;

        return $response->file($file, $contentType)->withHeader('Content-Disposition', $disposition);
    }

    /**
     * Display a file, such as an image or PDF, directly in the user's browser instead of initiating a download.
     *
     * @param string|resource|StreamInterface $file
     * @param bool|string $contentType
     *
     * @throws InvalidArgumentException If the mode is invalid.
     */
    public function file($file, $contentType = true): ResponseInterface
    {
        $response = self::create();

        if (is_resource($file)) {
            $response = $response->withBody(self::$factory->createStreamFromResource($file));
        } elseif (is_string($file)) {
            $response = $response->withBody(self::$factory->createStreamFromFile($file));
        } elseif ($file instanceof StreamInterface) {
            $response = $response->withBody($file);
        } else {
            throw new InvalidArgumentException(
                'Parameter 1 of Response::withFile() must be a resource, a string ' .
                'or an instance of Psr\Http\Message\StreamInterface.'
            );
        }

        if ($contentType === true) {
            $contentType = is_string($file) ? mime_content_type($file) : 'application/octet-stream';
        }

        if (is_string($contentType)) {
            $response = $response->withHeader('Content-Type', $contentType);
        }

        return $response;
    }

    /**
     * Convert response to string.
     */
    public function __toString(): string
    {
        $response = $this;

        $output = sprintf(
            'HTTP/%s %s %s%s',
            $response->getProtocolVersion(),
            $response->getStatusCode(),
            $response->getReasonPhrase(),
            "\r\n"
        );

        foreach ($response->getHeaders() as $name => $values) {
            $output .= sprintf('%s: %s', $name, $response->getHeaderLine($name)) . "\r\n";
        }

        $output .= "\r\n";
        $output .= (string) $response->getBody();

        return $output;
    }
}
