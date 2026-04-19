<?php

use AppSupport\Console\Console;

if (!function_exists('app')) {
    /**
     * Get the available container instance or an instance of a registered service
     *
     * @param string|null $abstract
     * @return Console | object
     */
    function app(string $abstract = null)
    {
        if (!is_null($abstract)) {
            return Console::getInstance()->resolve($abstract);
        } else {
            return Console::getInstance();
        }
    }
}

if (!function_exists('env')) {
    /**
     * Gets the value of an environment variable.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    function env(string $key, $default = null)
    {
        if (!isset($_ENV[$key])) {
            return $default;
        }

        $value = $_ENV[$key];

        switch (strtolower($value)) {
            case 'true':
            case '(true)':
                return true;
            case 'false':
            case '(false)':
                return false;
            case 'empty':
            case '(empty)':
                return '';
            case 'null':
            case '(null)':
                return null;
        }

        if (strlen($value) > 1 && str_starts_with($value, '"') && str_ends_with($value, '"')) {
            return substr($value, 1, -1);
        }

        return $value;
    }
}

if (!function_exists('load_config')) {
    function load_config($config)
    {
        $configPath = app("config")->get("app.config.path",
            app()->getBasePath() . DIRECTORY_SEPARATOR . "config");

        $filename = "{$configPath}/{$config}.php";

        if (file_exists($filename)) {
            return require $filename;
        } else {
            throw new \LogicException("Configuration file '{$filename}' not found");
        }
    }
}
