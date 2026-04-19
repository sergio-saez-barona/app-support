<?php

namespace AppSupport\Config;

use ArrayAccess;
use AppSupport\Contracts\Config;

class Repository implements ArrayAccess, Config
{
    const SEP = ".";

    private array $items = [];

    /**
     * @param array $items
     */
    public function __construct(array $items = [])
    {
        $this->items = $items;
    }

    /**
     * @throws \Exception
     */
    public function reference(string $path): static
    {
        if (!$this->isset($path)) {
            $this->set($path, []);
        }

        $ref = $this->get($path);
        if (!is_array($ref)) {
            throw new \Exception("Invalid path for reference");
        }

        return new static($ref);
    }

    private function getIndex(string $key): string
    {
        if (($pos = strrpos($key, self::SEP)) !== false) {
            return substr($key, $pos + 1);
        } else {
            return $key;
        }
    }

    private function &findPlace(string $key, &$array, bool $create = false): array
    {
        if (str_contains($key, self::SEP)) {
            list($index, $offset) = explode(self::SEP, $key, 2);

            if (!isset($array[$index])) {
                if ($create) {
                    $array[$index] = [];
                } else {
                    return $array;
                }
            }
            return $this->findPlace($offset, $array[$index], $create);
        } else {
            return $array;
        }
    }

    public function set(string $key, mixed $value)
    {
        if ($key == "") {
            $this->items[] = $value;
        } else {
            $place = &$this->findPlace($key, $this->items, true);
            $index = $this->getIndex($key);

            $place[$index] = $value;
        }
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $place = $this->findPlace($key, $this->items);
        $index = $this->getIndex($key);

        return $place[$index] ?? $default;
    }

    public function isset(string $key): bool
    {
        $place = $this->findPlace($key, $this->items);
        $index = $this->getIndex($key);

        return isset($place[$index]);
    }

    public function unset(string $key)
    {
        $place = &$this->findPlace($key, $this->items);
        $index = $this->getIndex($key);

        if (isset($place[$index])) {
            unset($place[$index]);
        }
    }

    public function offsetExists(mixed $offset): bool
    {
        return $this->isset($offset);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->get($offset);
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->set($offset, $value);
    }

    public function offsetUnset(mixed $offset): void
    {
        $this->unset($offset);
    }

    public function load(string $config, string $alias = ""): mixed
    {
        $value = load_config($config);

        if ($alias == "") {
            $alias = $config;
        }

        $this->set($alias, $value);

        return $value;
    }

}