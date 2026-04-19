<?php

namespace AppSupport\Console;

use AppSupport\Contracts\Config;

class Flag extends Option
{
    private $default = false;

    /**
     * @return bool
     */
    public function getDefault(): bool
    {
        return $this->default;
    }

    /**
     * @param bool $default
     * @return static
     */
    public function setDefault(bool $default): static
    {
        $this->default = $default;

        return $this;
    }

    /**
     * @param string $optionStr
     * @param array $args
     * @param bool $longName
     * @param Config $config
     * @param string $prefix
     * @return bool
     */
    public function parse(string $optionStr, array &$args, bool $longName,
                          Config $config, string $prefix): bool
    {
        $key = "{$prefix}{$this->path}";

        if (!$longName) {
            $value = true;
        } else {
            $value = (!str_starts_with($optionStr, "no-"));
        }

        $config->set($key, $value);

        return true;
    }

    /**
     * @param array $longOptions
     * @param array $shortOptions
     * @return void
     */
    public function register(array &$longOptions, array &$shortOptions): void
    {
        parent::register($longOptions, $shortOptions);

        $longOptions["no-{$this->longName}"] = $this;
    }

    /**
     * @param Config $config
     * @param string $prefix
     * @return void
     */
    public function configDefault(Config $config, string $prefix): void
    {
        $key = "{$prefix}{$this->path}";
        $config->set($key, $this->getDefault());
    }

}
