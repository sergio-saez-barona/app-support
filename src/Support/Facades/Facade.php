<?php

namespace AppSupport\Support\Facades;

use AppSupport\Contracts\Container;
use RuntimeException;

abstract class Facade
{
    /**
     * The application instance being facaded.
     */
    protected static Container $app;

    /**
     * The resolved object instances.
     */
    protected static array $resolvedInstance;

    /**
     * Hotswap the underlying instance behind the facade.
     *
     * @param  object  $instance
     * @return void
     */
    public static function swap(object $instance): void
    {
        static::$resolvedInstance[static::getFacadeAccessor()] = $instance;

        if (isset(static::$app)) {
            static::$app->instance(static::getFacadeAccessor(), $instance);
        }
    }

    /**
     * Get the root object behind the facade.
     *
     * @return object
     */
    public static function getFacadeRoot(): object
    {
        return static::resolveFacadeInstance(static::getFacadeAccessor());
    }

    /**
     * Get the registered name of the component.
     *
     * @return string
     *
     * @throws \RuntimeException
     */
    protected static function getFacadeAccessor(): string
    {
        throw new RuntimeException('Facade does not implement getFacadeAccessor method.');
    }

    /**
     * Resolve the facade root instance from the container.
     *
     * @param string $name
     * @return object
     */
    protected static function resolveFacadeInstance(string $name): object
    {
        if (is_object($name)) {
            return $name;
        }

        if (isset(static::$resolvedInstance[$name])) {
            return static::$resolvedInstance[$name];
        }

        return static::$resolvedInstance[$name] = static::$app->resolve($name);
    }

    /**
     * Get the root object behind the facade.
     */
    public static function clearInstance(): void
    {
        static::clearResolvedInstance(static::getFacadeAccessor());
    }

    /**
     * Clear a resolved facade instance.
     *
     * @param string $name
     * @return void
     */
    public static function clearResolvedInstance(string $name): void
    {
        unset(static::$resolvedInstance[$name]);
    }

    /**
     * Clear all of the resolved instances.
     *
     * @return void
     */
    public static function clearResolvedInstances(): void
    {
        static::$resolvedInstance = [];
    }

    /**
     * Get the application instance behind the facade.
     *
     * @return Container
     */
    public static function getFacadeApplication(): Container
    {
        return static::$app;
    }

    /**
     * Set the application instance.
     *
     * @param  $app
     * @return void
     */
    public static function setFacadeApplication($app): void
    {
        static::$app = $app;
    }

    /**
     * Handle dynamic, static calls to the object.
     *
     * @param string $method
     * @param array $args
     * @return mixed
     *
     * @throws \RuntimeException
     */
    public static function __callStatic(string $method, array $args): mixed
    {
        $instance = static::getFacadeRoot();

        if (! $instance) {
            throw new RuntimeException('A facade root has not been set.');
        }

        return $instance->$method(...$args);
    }
}
