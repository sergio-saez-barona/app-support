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
     * Gives values to the options that have not been given (e.g. the values of a configuration file). The
     * values are checked like the ones of the command line: valid values, directories...
     *
     * @param array<string, mixed> $values long name of the option => its value: a text or a number, true or false
     *                                     for a flag, or a list of them for an option that can be repeated
     * @param Config $config
     * @param bool $strict if true an option that does not exist is an error, and if not it is ignored
     * @param string $origin where the values come from, for the messages
     */
    public function setFromValues(array $values, Config $config, bool $strict, string $origin): void
    {
        foreach ($values as $name => $value) {
            $name = (string)$name;
            $option = $this->options[$name] ?? null;
            if ($option === null) {
                if ($strict) {
                    Log::critical("Unknown option '{$name}' in {$origin}");
                }
                continue;
            }
            if ($option instanceof ConfigFile) {
                if ($strict) {
                    Log::critical("The option '{$name}' cannot be given in {$origin}");
                }
                continue;
            }
            if ($option->isSet()) {
                // What is in the command line has priority
                continue;
            }

            $this->setFromValue($option, $name, $value, $config, $origin);
        }
    }

    private function setFromValue(Option $option, string $name, mixed $value, Config $config, string $origin): void
    {
        $args = [];
        if ($option instanceof Flag) {
            if (!is_bool($value)) {
                Log::critical("The option '{$name}' in {$origin} requires true or false");
            }
            $option->parse($value ? $name : "no-{$name}", $args, true, $config, $this->configPrefix);

            return;
        }

        if (is_array($value) && !array_is_list($value)) {
            Log::critical("The option '{$name}' in {$origin} requires a value, not a table");
        }
        if (is_array($value) && !($option instanceof Value && $option->isMultiple())) {
            Log::critical("The option '{$name}' in {$origin} cannot have a list of values");
        }

        foreach (is_array($value) ? $value : [$value] as $item) {
            if (!is_string($item) && !is_int($item) && !is_float($item)) {
                Log::critical("The option '{$name}' in {$origin} requires a text or a number");
            }

            $args = [(string)$item];
            if (!$option->parse($name, $args, true, $config, $this->configPrefix)) {
                Log::critical("Invalid value for the option '{$name}' in {$origin}");
            }
        }
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
