<?php

namespace AppSupport\Contracts;

interface Config
{
    public function reference(string $path): static;

    public function set(string $key, mixed $value);

    public function get(string $key, mixed $default = null);

    public function isset(string $key): bool;

    public function unset(string $key);

    public function load(string $config, string $alias = "");
}