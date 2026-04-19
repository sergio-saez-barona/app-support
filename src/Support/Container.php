<?php

namespace AppSupport\Support;

use LogicException;

class Container implements \AppSupport\Contracts\Container
{
    /**
     * The current globally available container (if any).
     */
    protected static Container $instance;

    /**
     * The container's shared instances.
     */
    protected array $instances = [];

    /**
     * The container's shared classes.
     */
    protected array $classes = [];

    /**
     * The registered type aliases.
     */
    protected array $aliases = [];

    /**
     * Indicates if a given class is a singleton
     * @var bool[]
     */
    protected array $shared = [];

    /**
     * Determine if the given abstract type has been bound.
     *
     * @param  string $abstract
     * @return bool
     */
    public function bound(string $abstract): bool
    {
        return isset($this->instances[$abstract]) || isset($this->classes[$abstract]) ||
            $this->isAlias($abstract);
    }

    /**
     * Determine if the given abstract type has been resolved.
     *
     * @param  string $abstract
     * @return bool
     */
    public function resolved(string $abstract): bool
    {
        if ($this->isAlias($abstract)) {
            $abstract = $this->getAlias($abstract);
        }

        return isset($this->instances[$abstract]);
    }

    /**
     * Determine if a given string is an alias.
     *
     * @param  string $name
     * @return bool
     */
    public function isAlias(string $name): bool
    {
        return isset($this->aliases[$name]);
    }

    /**
     * Register an existing instance as shared in the container.
     *
     * @param  string $abstract
     * @param  mixed $instance
     * @return void
     */
    public function instance(string $abstract, mixed $instance): void
    {
        unset($this->aliases[$abstract]);

        $this->instances[$abstract] = $instance;
    }

    /**
     * Register an existing class in the container.
     *
     * @param string $abstract
     * @param string $classname
     * @param bool $shared
     * @return void
     */
    public function register(string $abstract, string $classname, bool $shared = false): void
    {
        unset($this->instances[$abstract]);

        $this->classes[$abstract] = $classname;
        $this->shared[$abstract] = $shared;
    }

    /**
     * Register an existing class as shared in the container.
     *
     * @param  string $abstract
     * @param  string $classname
     * @return void
     */
    public function singleton(string $abstract, string $classname): void
    {
        $this->register($abstract, $classname, true);
    }

    /**
     * Alias a type to a different name.
     *
     * @param  string $abstract
     * @param  string $alias
     * @return void
     */
    public function alias(string $abstract, string $alias): void
    {
        $this->aliases[$alias] = $abstract;
    }

    /**
     * Resolves a given abstract type or alias
     * @param string $abstract
     * @return mixed
     */
    public function resolve(string $abstract): mixed
    {
        if ($this->isAlias($abstract)) {
            $abstract = $this->getAlias($abstract);
        }

        if (!$this->resolved($abstract)) {
            if (isset($this->classes[$abstract])) {
                $classname = $this->classes[$abstract];
                $instance = new $classname();

                if ($this->shared[$abstract]) {
                    $this->instances[$abstract] = $instance;
                }

                return $instance;
            }

            throw new LogicException("[{$abstract}] is not resolved.");
        }

        return $this->instances[$abstract];
    }

    /**
     * Get the alias for an abstract if available.
     *
     * @param  string $abstract
     * @return string
     *
     * @throws \LogicException
     */
    public function getAlias(string $abstract): string
    {
        if (!isset($this->aliases[$abstract])) {
            return $abstract;
        }

        if ($this->aliases[$abstract] === $abstract) {
            throw new LogicException("[{$abstract}] is aliased to itself.");
        }

        return $this->aliases[$abstract];
    }

    /**
     * Get the array of alias
     *
     * @return array
     */
    public function getAliases(): array
    {
        return $this->aliases;
    }

    /**
     * Drop all of the stale instances and aliases.
     *
     * @param  string $abstract
     * @return void
     */
    protected function dropStaleInstances(string $abstract): void
    {
        unset($this->instances[$abstract], $this->aliases[$abstract]);
    }

    /**
     * Remove a resolved instance from the instance cache.
     *
     * @param  string $abstract
     * @return void
     */
    public function forgetInstance(string $abstract): void
    {
        unset($this->instances[$abstract]);
    }

    /**
     * Clear all of the instances from the container.
     *
     * @return void
     */
    public function forgetInstances(): void
    {
        $this->instances = [];
    }

    /**
     * Flush the container of all bindings and resolved instances.
     *
     * @return void
     */
    public function flush(): void
    {
        $this->aliases = [];
        $this->instances = [];
    }

    /**
     * Get the globally available instance of the container.
     *
     * @return static
     */
    public static function getInstance(): static
    {
        if (is_null(static::$instance)) {
            static::$instance = new static;
        }

        return static::$instance;
    }

    /**
     * Set the shared instance of the container.
     *
     * @param Container|null $container
     * @return Container|null
     */
    public static function setInstance(Container $container = null): ?static
    {
        return static::$instance = $container;
    }

}
