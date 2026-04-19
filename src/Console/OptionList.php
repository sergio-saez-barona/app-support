<?php

namespace AppSupport\Console;

use AppSupport\Contracts\Config;
use AppSupport\Support\Facades\Log;

class OptionList
{
    public const defaultPrefix = "app.args.";
    private string $configPrefix = self::defaultPrefix;
    private array $options = [];
    private array $longOptions = [];
    private array $shortOptions = [];

    /**
     * @return string
     */
    public function getConfigPrefix(): string
    {
        return $this->configPrefix;
    }

    /**
     * @param string $configPrefix
     * @return OptionList
     */
    public function setConfigPrefix(string $configPrefix): static
    {
        $this->configPrefix = $configPrefix;

        return $this;
    }

    /**
     * @param Option $option
     * @return Option
     */
    public function addOption(Option $option): Option
    {
        $this->options[$option->getName()] = $option;
        $option->register($this->longOptions, $this->shortOptions);

        return $option;
    }

    /**
     * @return Option[]
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * It parses one option
     *
     * @param string[] $args
     * @param Config $config
     */
    public function parseOption(array &$args, Config $config): void
    {
        if (!self::isOption($args)) {
            throw new \LogicException("Item '{$args[0]}' is not an option");
        }

        $optionStr = array_shift($args);
        if (substr($optionStr, 0, 2) == "--") {
            // Long option name
            $key = substr($optionStr, 2);
            $option = $this->findOption($key, true);
        } else if (strlen($optionStr) == 2) {
            // Short option name
            $key = $optionStr[1];
            $option = $this->findOption($key, false);
        } else {
            /** @var Console $console */
            $console = app();
            $console->showInfo();

            Log::critical("Invalid option '{$optionStr}'");
            return;
        }

        $success = $option->parse($key, $args, (strlen($optionStr) > 2), $config, $this->configPrefix);
        if (!$success) {
            Log::critical("Error while parsing option '{$option->getName()}'");
        }
    }

    /**
     * @param array $args
     * @return bool
     */
    static public function isOption(array $args): bool
    {
        return ($args[0][0] == "-");
    }

    /**
     * @param string $key
     * @param bool $useLongName
     * @return Option
     */
    public function findOption(string $key, bool $useLongName): Option
    {
        if ($useLongName) {
            $option = $this->longOptions[$key] ?? null;
        } else {
            $option = $this->shortOptions[$key] ?? null;
        }

        if (is_null($option)) {
            /** @var Console $console */
            $console = app();
            $console->showInfo();

            Log::critical("Option '$key' not found");
        }

        return $option;
    }

    /**
     * @param Config $config
     * @return void
     */
    public function configDefaults(Config $config): void
    {
        foreach ($this->longOptions as $option) {
            /** @var Option $option */
            $option->configDefault($config, $this->configPrefix);
        }
    }

    /**
     * @return bool
     */
    public function checkRequired(): bool
    {
        $error = false;
        foreach ($this->longOptions as $option) {
            if ($option instanceof Value && $option->isRequired() && !$option->isSet()) {
                $error = true;
                Log::error("Option '{$option->getLongName()}' is required");
            }
        }

        return $error;
    }
}
