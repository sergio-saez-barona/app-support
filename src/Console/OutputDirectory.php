<?php


namespace AppSupport\Console;


use AppSupport\Contracts\Config;
use AppSupport\Support\Facades\Log;

class OutputDirectory extends Value
{
    private bool $errorIfExist = false;

    /**
     * OutputDirectory constructor.
     */
    public function __construct(string $longName, string $shortName = "", bool $errorIfExist = false)
    {
        parent::__construct($longName, $shortName);

        $this->errorIfExist = $errorIfExist;
        $this->setDescription("Output directory");
    }

    /**
     * @return bool
     */
    public function forceErrorIfExist(): bool
    {
        return $this->errorIfExist;
    }

    /**
     * @param bool $errorIfExist
     */
    public function setErrorIfExist(bool $errorIfExist): void
    {
        $this->errorIfExist = $errorIfExist;
    }

    public function parse(string $optionStr, array &$args, bool $longName, Config $config, string $prefix): bool
    {
        if (!parent::parse($optionStr, $args, $longName, $config, $prefix)) {
            return false;
        }

        $value = $config->get($this->getKey());

        if ($this->forceErrorIfExist() && is_dir($value)) {
            Log::error("Output directory '$value' already exists");
            return false;
        }

        return true;
    }

}