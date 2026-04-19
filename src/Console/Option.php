<?php

namespace AppSupport\Console;

use AppSupport\Contracts\Config;

abstract class Option extends CLIItem
{
    protected string $longName = "";
    protected string $shortName = "";
    protected string $path = "";

    protected bool $required = false;

    /**
     * Option constructor.
     * @param string $longName
     * @param string $shortName
     * @param string $path
     */
    public function __construct(string $longName, string $shortName = "", string $path = "")
    {
        $this->setName($longName);

        $this->longName = $longName;
        $this->shortName = $shortName;
        $this->path = ($path != "") ? $path : $longName;
    }

    /**
     * @return string
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * @return string
     */
    public function getLongName(): string
    {
        return $this->longName;
    }

    /**
     * @return string
     */
    public function getShortName(): string
    {
        return $this->shortName;
    }

    /**
     * @return bool
     */
    public function isRequired(): bool
    {
        return $this->required;
    }

    /**
     * @param bool $required
     * @return Option
     */
    public function setRequired(bool $required): static
    {
        $this->required = $required;

        return $this;
    }

    /**
     * @param array $longOptions
     * @param array $shortOptions
     */
    public function register(array &$longOptions, array &$shortOptions)
    {
        $longOptions[$this->longName] = $this;
        if ($this->shortName != "") {
            $shortOptions[$this->shortName] = $this;
        }
    }

    /**
     * Try to parse current Item. It returns true on success.
     * @param string $optionStr
     * @param string[] &$args
     * @param bool $longName
     * @param Config $config
     * @param string $prefix
     * @return bool
     */
    abstract public function parse(string $optionStr,
                                   array &$args,
                                   bool $longName,
                                   Config $config,
                                   string $prefix): bool;


    abstract public function configDefault(Config $config, string $prefix);
}
