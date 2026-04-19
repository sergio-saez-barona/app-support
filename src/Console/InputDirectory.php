<?php


namespace AppSupport\Console;


use AppSupport\Contracts\Config;
use AppSupport\Support\Facades\Log;

class InputDirectory extends Value
{
    /**
     * OutputDirectory constructor.
     */
    public function __construct(string $longName, string $shortName = "")
    {
        parent::__construct($longName, $shortName);

        $this->setDescription("Input directory");
    }

    public function parse(string $optionStr, array &$args, bool $longName, Config $config, string $prefix): bool
    {
        $retval = parent::parse($optionStr, $args, $longName, $config, $prefix);
        if (!$retval) {
            return $retval;
        }

        $directories = (array)$config->get($this->getKey());

        foreach ($directories as $directory) {
            if (!is_dir($directory) || !is_readable($directory)) {
                Log::error("Input directory '$directory' does not exist or cannot be read");
                return false;
            }
        }

        return true;
    }
}