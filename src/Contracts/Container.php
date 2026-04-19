<?php

namespace AppSupport\Contracts;

interface Container
{
    /**
     * Determine if the given abstract type has been bound.
     *
     * @param string $abstract
     * @return bool
     */
    public function bound(string $abstract): bool;

    /**
     * Determine if the given abstract type has been resolved.
     *
     * @param string $abstract
     * @return bool
     */
    public function resolved(string $abstract): bool;

    /**
     * Register an existing instance as shared in the container.
     *
     * @param string $abstract
     * @param mixed $instance
     * @return void
     */
    public function instance(string $abstract, mixed $instance): void;

    /**
     * Register an existing class in the container.
     *
     * @param string $abstract
     * @param string $classname
     * @param bool $shared
     * @return void
     */
    public function register(string $abstract, string $classname, bool $shared = false): void;

    /**
     * Register an existing class as shared in the container.
     *
     * @param string $abstract
     * @param string $classname
     * @return void
     */
    public function singleton(string $abstract, string $classname): void;

    /**
     * Alias a type to a different name.
     *
     * @param string $abstract
     * @param string $alias
     * @return void
     */
    public function alias(string $abstract, string $alias): void;

    /**
     * Resolves a given abstract type or alias
     * @param string $abstract
     * @return mixed
     */
    public function resolve(string $abstract): mixed;

    /**
     * Flush the container of all bindings and resolved instances.
     *
     * @return void
     */

    public function flush(): void;

    public static function getInstance();

}