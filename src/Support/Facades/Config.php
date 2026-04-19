<?php

namespace AppSupport\Support\Facades;

/**
 * Class Config
 * @package AppSupport\Support\Facades
 *
 * @method static set(string $key, $value);
 * @method static get(string $key, $default = null);
 * @method static isset(string $key): bool;
 * @method static unset(string $key);
 * @method static load(string $config, string $alias = null);
 */
class Config extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return \AppSupport\Contracts\Config::class;
    }
}
