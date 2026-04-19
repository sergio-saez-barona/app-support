<?php

namespace AppSupport\Console;

use AppSupport\Contracts\Config;
use AppSupport\Support\Facades\Log;

class Value extends Option
{
    private bool $multiple = false;
    private string $default = "";
    private string $key = "";
    private string $label = "value";

    private bool $isSet = false;

    private array $validValues = [];

    /**
     * @return bool
     */
    public function isMultiple(): bool
    {
        return $this->multiple;
    }

    /**
     * @param bool $multiple
     * @return Value
     */
    public function setMultiple(bool $multiple): static
    {
        $this->multiple = $multiple;

        return $this;
    }

    /**
     * @return string
     */
    public function getDefault(): string
    {
        return $this->default;
    }

    /**
     * @param string $default
     * @return Value
     */
    public function setDefault(string $default): static
    {
        $this->default = $default;

        return $this;
    }

    /**
     * @param bool $required
     * @return Value
     */
    public function setRequired(bool $required): static
    {
        parent::setRequired($required);
        if ($required) {
            // If required, the default value is not needed anymore
            $this->default = "";
        }

        return $this;
    }

    /**
     * @return array
     */
    public function getValidValues(): array
    {
        return $this->validValues;
    }

    /**
     * @param array $validValues
     */
    public function setValidValues(array $validValues): void
    {
        $this->validValues = $validValues;
    }

    /**
     * @return bool
     */
    public function isSet(): bool
    {
        return $this->isSet;
    }

    /**
     * @return string
     */
    protected function getKey(): string
    {
        return $this->key;
    }

    /**
     * @return string
     */
    public function getLabel(): string
    {
        return $this->label;
    }

    /**
     * @param string $label
     */
    public function setLabel(string $label): void
    {
        $this->label = $label;
    }

    /**
     * @param string $optionStr
     * @param array $args
     * @param bool $longName
     * @param Config $config
     * @param string $prefix
     * @return bool
     */
    public function parse(string $optionStr, array &$args, bool $longName, Config $config, string $prefix): bool
    {
        $this->key = "{$prefix}{$this->path}";

        $value = array_shift($args);

        if ($this->multiple) {
            $prev = $config->get($this->key, []);
            $prev[] = $value;
            $value = $prev;
        } else {
            if ($this->isSet) {
                Log::critical("Option '{$this->name}' does not support multiple values");
            }
        }

        if (count($this->getValidValues()) > 0 && !in_array($value, $this->getValidValues())) {
            Log::error("Invalid value '$value' for option '{$this->getName()}'");
            Log::info("Valid values are: " . implode(", ", $this->getValidValues()));
            return false;
        }

        $this->isSet = true;
        $config->set($this->key, $value);

        return true;
    }

    /**
     * @param Config $config
     * @param string $prefix
     * @return void
     */
    public function configDefault(Config $config, string $prefix): void
    {
        if ($this->default != "") {
            $key = "{$prefix}{$this->path}";
            $config->set($key, $this->getDefault());
        }
    }

}
