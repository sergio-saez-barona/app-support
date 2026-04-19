<?php
/*
 * app-support
 * @file: Argument.php
 * @author: ssaez
 */

namespace AppSupport\Console;


use AppSupport\Support\Facades\Log;

class Argument extends CLIItem
{
    private bool $isSet = false;
    private bool $required = true;
    private bool $multiple = false;

    /**
     * Argument constructor.
     * @param string $name
     * @param string $description
     * @param bool $required
     * @param bool $multiple
     */
    public function __construct(string $name, string $description, bool $required = true, bool $multiple = false)
    {
        parent::setName($name);
        parent::setDescription($description);

        $this->required = $required;
        $this->multiple = $multiple;
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
     */
    public function setRequired(bool $required): void
    {
        $this->required = $required;
    }

    /**
     * @return bool
     */
    public function isMultiple(): bool
    {
        return $this->multiple;
    }

    /**
     * @return bool
     */
    public function isSet(): bool
    {
        return $this->isSet;
    }

    /**
     * @param bool $multiple
     */
    public function setMultiple(bool $multiple): void
    {
        $this->multiple = $multiple;
    }

    public function parse(string $argumentStr): bool
    {
        if ($this->isSet() && !$this->isMultiple()) {
            Log::error("Argument '{$this->getName()}' does not support multiple values");
            return false;
        }

        $this->isSet = true;

        return true;
    }
}