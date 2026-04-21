<?php

declare(strict_types=1);

namespace Horizom\Http\Collection;

use Horizom\Http\Exceptions\BadRequestException;
use Illuminate\Support\Collection as IlluminateCollection;

/**
 * HTTP parameter bag backed by Illuminate\Support\Collection.
 *
 * Extends the full Illuminate collection API with HTTP-specific helpers
 * (getAlpha, getAlnum, getDigits, getInt, getBoolean, filter-by-key).
 */
class Collection extends IlluminateCollection
{
    /**
     * Returns all parameters, or the array stored under a specific key.
     *
     * @return array<mixed>
     * @throws BadRequestException When the value for $key is not an array.
     */
    public function all(?string $key = null): array
    {
        if ($key === null) {
            return $this->items;
        }

        if (!\is_array($value = $this->items[$key] ?? [])) {
            throw new BadRequestException(sprintf(
                'Unexpected value for parameter "%s": expecting "array", got "%s".',
                $key,
                get_debug_type($value)
            ));
        }

        return $value;
    }

    /**
     * Returns the parameter keys as a plain array.
     *
     * Overrides Illuminate's keys() which returns a new Collection instance.
     *
     * @return string[]
     */
    public function keys(): array
    {
        return array_keys($this->items);
    }

    /**
     * Replaces all parameters with the given array (mutates in place).
     *
     * Overrides Illuminate's replace() which returns a new instance.
     *
     * @param mixed $parameters
     */
    public function replace($parameters = [])
    {
        $this->items = $this->getArrayableItems($parameters);
    }

    /**
     * Merges the given parameters into the current bag (mutates in place).
     *
     * Overrides Illuminate's add() which appends a single item without a key.
     *
     * @param mixed $parameters
     */
    public function add($parameters = [])
    {
        $this->items = array_replace($this->items, $this->getArrayableItems($parameters));
    }

    /**
     * Returns the value for a named parameter.
     *
     * @param mixed $default
     * @return mixed
     */
    public function get($key, $default = null): mixed
    {
        return \array_key_exists($key, $this->items) ? $this->items[$key] : $default;
    }

    /**
     * Sets a parameter by name (mutates in place).
     */
    public function set(string $key, mixed $value): void
    {
        $this->items[$key] = $value;
    }

    /**
     * Returns true if the named parameter exists (including null values).
     *
     * @param string|array $key
     */
    public function has($key): bool
    {
        return \array_key_exists($key, $this->items);
    }

    /**
     * Removes a parameter by name.
     */
    public function remove(string $key)
    {
        unset($this->items[$key]);
    }

    // -------------------------------------------------------------------------
    // HTTP-specific typed accessors
    // -------------------------------------------------------------------------

    public function getAlpha(string $key, string $default = ''): string
    {
        return (string) preg_replace('/[^[:alpha:]]/', '', $this->get($key, $default));
    }

    public function getAlnum(string $key, string $default = ''): string
    {
        return (string) preg_replace('/[^[:alnum:]]/', '', $this->get($key, $default));
    }

    public function getDigits(string $key, string $default = ''): string
    {
        return str_replace(['-', '+'], '', $this->param_filter($key, $default, \FILTER_SANITIZE_NUMBER_INT));
    }

    public function getInt(string $key, int $default = 0): int
    {
        return (int) $this->get($key, $default);
    }

    public function getBoolean(string $key, bool $default = false): bool
    {
        return (bool) $this->param_filter($key, $default, \FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Polymorphic filter method.
     *
     * - When called with a string $key (and optional PHP filter constant), acts as an
     *   HTTP parameter filter using filter_var() — same API as Symfony's ParameterBag.
     * - When called with a callable or no argument, delegates to Illuminate's
     *   Collection::filter() which returns a new filtered Collection.
     *
     * @param string|callable|null $key
     * @param mixed                $default
     * @param mixed                $options
     * @return mixed|static
     */
    public function filter($key = null, $default = null, int $filter = \FILTER_DEFAULT, $options = [])
    {
        if (!\is_string($key)) {
            // Delegate to Illuminate's collection filter (callable or no-arg)
            return parent::filter($key);
        }

        return $this->param_filter($key, $default, $filter, $options);
    }

    // -------------------------------------------------------------------------
    // Internal helpers
    // -------------------------------------------------------------------------

    /**
     * Applies a PHP filter_var() filter to a named parameter value.
     *
     * @param mixed $default
     * @param mixed $options
     * @return mixed
     */
    private function param_filter(string $key, $default = null, int $filter = \FILTER_DEFAULT, $options = [])
    {
        $value = $this->get($key, $default);

        if (!\is_array($options) && $options) {
            $options = ['flags' => $options];
        }

        if (\is_array($value) && !isset($options['flags'])) {
            $options['flags'] = \FILTER_REQUIRE_ARRAY;
        }

        return filter_var($value, $filter, $options);
    }
}
