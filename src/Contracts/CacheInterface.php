<?php

namespace AppSupport\Contracts;

interface CacheInterface
{
    public function set(string $key, $value);

    public function get(string $key, $default = null);

    public function isset(string $key): bool;

    public function unset(string $key);

    public function flush();
}