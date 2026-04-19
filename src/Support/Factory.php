<?php

namespace AppSupport\Support;

use LogicException;

class Factory
{
    /**
     * The container's shared instances.
     *
     * @var array
     */
    protected array $instances = [];

    /**
     * The container's shared classes.
     *
     * @var array
     */
    protected array $classes = [];

    /**
     * The registered type aliases.
     *
     * @var array
     */
    protected array $aliases = [];

    /**
     * Determine if the given abstract type has been bound.
     *
     * @param string $abstract
     * @return bool
     */
    public function isBound(string $abstract): bool
    {
        return isset($this->instances[$abstract]) ||
            isset($this->classes[$abstract]) ||
            $this->isAlias($abstract);
    }

    /**
     * Determine if the given abstract type has been resolved.
     *
     * @param string $abstract
     * @return bool
     */
    public function isResolved(string $abstract): bool
    {
        if ($this->isAlias($abstract)) {
            $abstract = $this->getAlias($abstract);
        }

        return isset($this->instances[$abstract]) && $this->instances[$abstract] !== null;
    }

    /**
     * Determine if a given string is an alias.
     *
     * @param string $name
     * @return bool
     */
    public function isAlias(string $name): bool
    {
        return isset($this->aliases[$name]);
    }

    /**
     * Register an existing instance as shared in the container.
     *
     * @param string $abstract
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
     * @param boolean $singleton = false
     * @return void
     */
    public function register(string $abstract, string $classname, bool $singleton = false): void
    {
        unset($this->aliases[$abstract]);

        $this->classes[$abstract] = $classname;
        if ($singleton) {
            $this->instances[$abstract] = null;
        } else {
            unset($this->instances[$abstract]);
        }
    }

    /**
     * Register an existing class as a singleton in the container.
     *
     * @param string $abstract
     * @param string $classname
     * @return void
     */
    public function singleton(string $abstract, string $classname): void
    {
        $this->register($abstract, $classname, true);
    }

    /**
     * Alias a type to a different name.
     *
     * @param string $abstract
     * @param string $alias
     * @return void
     */
    public function alias(string $abstract, string $alias): void
    {
        $this->aliases[$alias] = $abstract;
    }

    /**
     * Resolves a given abstract type or alias
     */
    public function resolve(string $abstract): mixed
    {
        if ($this->isAlias($abstract)) {
            $abstract = $this->getAlias($abstract);
        }

        if ($this->isResolved($abstract)) {
            return $this->instances[$abstract];
        } else {

            if (isset($this->classes[$abstract])) {
                $classname = $this->classes[$abstract];
                $instance = new $classname();

                if (array_key_exists($abstract, $this->instances)) {
                    // The first instance of a singleton class
                    $this->instances[$abstract] = $instance;
                }

                return $instance;
            }

            throw new LogicException("[{$abstract}] is not resolved.");
        }

    }

    /**
     * Get the abstract for an alias if available.
     *   Returns the alias itself otherwise.
     *
     * @param string $alias
     * @return string
     *
     * @throws \LogicException
     */
    public function getAlias(string $alias): string
    {
        if (!isset($this->aliases[$alias])) {
            return $alias;
        }

        if ($this->aliases[$alias] === $alias) {
            throw new LogicException("[{$alias}] is aliased to itself.");
        }

        return $this->aliases[$alias];
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
     * @param string $abstract
     * @return void
     */
    protected function dropStaleInstances(string $abstract): void
    {
        unset($this->instances[$abstract], $this->aliases[$abstract]);
    }

    /**
     * Remove a resolved instance from the instance cache.
     *
     * @param string $abstract
     * @return void
     */
    public function forgetInstance(string $abstract): void
    {
        unset($this->instances[$abstract]);
    }

    /**
     * Clear all of the instances from the factory.
     *
     * @return void
     */
    public function forgetInstances(): void
    {
        $this->instances = [];
    }

    /**
     * Flush the factory of all bindings and resolved instances.
     *
     * @return void
     */
    public function flush(): void
    {
        $this->aliases = [];
        $this->instances = [];
    }

}
