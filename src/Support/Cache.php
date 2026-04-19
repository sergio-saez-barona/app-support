<?php

namespace AppSupport\Support;

use AppSupport\Contracts\CacheInterface;

class Cache implements CacheInterface
{
    private $cache = [];

    public function set(string $key, $value)
    {
        $this->cache[$key] = $value;
    }

    public function get(string $key, $default = null)
    {
        return $this->cache[$key] ?? $default;
    }

    public function isset(string $key): bool
    {
        return isset($this->cache[$key]);
    }

    public function unset(string $key)
    {
        unset($this->cache[$key]);
    }

    public function flush()
    {
        $this->cache = [];
    }
}