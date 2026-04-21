<?php

declare(strict_types=1);

namespace Horizom\Http;

use Horizom\Http\Collection\FileCollection;
use Horizom\Http\Collection\ServerCollection;
use Illuminate\Support\Collection;

trait RequestInputTrait
{
    /**
     * Access all of the user POST input.
     *
     * @param mixed $default
     * @return mixed|Collection
     */
    public function post(?string $name = null, $default = null)
    {
        $post = new Collection($_POST);

        if ($name !== null) {
            return $post->get($name, $default);
        }

        return $post;
    }

    /**
     * Access values from the query string.
     *
     * @param mixed $default
     * @return mixed|Collection
     */
    public function query(?string $name = null, $default = null)
    {
        $queries = $this->parseQueryParams($this->getUri()->getQuery());
        $query = new Collection($queries);

        if ($name !== null) {
            return $query->get($name, $default);
        }

        return $query;
    }

    /**
     * Access uploaded files from the request.
     *
     * @return mixed|FileCollection
     */
    public function files(?string $name = null)
    {
        $files = new FileCollection($_FILES);

        if ($name !== null) {
            return $files->get($name);
        }

        return $files;
    }

    /**
     * Access all of the user COOKIE input.
     *
     * @param mixed $default
     * @return mixed|Collection
     */
    public function cookie(?string $name = null, $default = null)
    {
        $cookie = new Collection($_COOKIE);

        if ($name !== null) {
            return $cookie->get($name, $default);
        }

        return $cookie;
    }

    /**
     * Access server parameters.
     *
     * @param mixed $default
     * @return mixed|ServerCollection
     */
    public function server(?string $name = null, $default = null)
    {
        $server = new ServerCollection($_SERVER);

        if ($name !== null) {
            return $server->get($name, $default);
        }

        return $server;
    }

    /**
     * Get a collection of all scalar input data ($_GET, $_POST, and raw body for PUT/PATCH).
     * Uploaded files are excluded; use files() for those.
     */
    public function collect(): Collection
    {
        $post = $this->post()->all();
        $query = $this->query()->all();
        $put = [];

        // For PUT/PATCH requests with form-encoded bodies, parse the raw body.
        $contentType = $this->getHeaderLine('Content-Type');
        if (str_contains($contentType, 'application/x-www-form-urlencoded')) {
            $rawBody = (string) $this->getBody();
            $put = $this->parseQueryParams($rawBody);
        }

        return new Collection(array_merge($query, $post, $put));
    }

    /**
     * Get all input data as an array.
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->collect()->all();
    }

    /**
     * Retrieve a single input item from the request.
     *
     * @param mixed $default
     * @return mixed
     */
    public function input(?string $id = null, $default = null)
    {
        $input = $this->collect();

        if ($id === null) {
            return $input->all();
        }

        return $input->get($id, $default);
    }

    /**
     * Check if a key exists in the input data.
     */
    public function has(string $key): bool
    {
        return $this->collect()->has($key);
    }

    /**
     * Check if a key is missing from the input data.
     */
    public function missing(string $key): bool
    {
        return !$this->has($key);
    }

    /**
     * Get the items with the specified keys.
     *
     * @param array<int, string>|string $keys
     */
    public function only($keys): Collection
    {
        return $this->collect()->only($keys);
    }

    /**
     * Get the items except for those with the specified keys.
     *
     * @param array<int, string>|string $keys
     */
    public function except($keys): Collection
    {
        return $this->collect()->except($keys);
    }

    /**
     * Get an item from the input data as a string.
     */
    public function string(string $key, ?string $default = null): string
    {
        return (string) $this->collect()->get($key, $default);
    }

    /**
     * Get an item from the input data as an integer.
     */
    public function integer(string $key, int $default = 0): int
    {
        return (int) $this->collect()->get($key, $default);
    }

    /**
     * Get an item from the input data as a float.
     */
    public function float(string $key, float $default = 0.0): float
    {
        return (float) $this->collect()->get($key, $default);
    }

    /**
     * Get an item from the input data as a boolean.
     */
    public function boolean(string $key, bool $default = false): bool
    {
        return (bool) $this->collect()->get($key, $default);
    }

    /**
     * Merge the input data with the given array.
     *
     * @param array<string, mixed> $input
     */
    public function merge(array $input): Collection
    {
        return $this->collect()->merge($input);
    }

    /**
     * Check if the input data has a file with the given key.
     */
    public function hasFile(string $key): bool
    {
        return $this->files()->has($key);
    }

    /**
     * Parse a query/form-encoded string into an associative array.
     *
     * @return array<string, mixed>
     */
    private function parseQueryParams(string $parse): array
    {
        $params = [];
        if ($parse !== '') {
            parse_str($parse, $params);
        }
        return $params;
    }
}
