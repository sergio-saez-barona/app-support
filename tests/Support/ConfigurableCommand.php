<?php

namespace AppSupport\Tests\Support;

use AppSupport\Console\Argument;
use AppSupport\Console\Command;
use AppSupport\Contracts\Config;

/**
 * A command whose options, flags and arguments are given by the test. It is created directly (not by
 * the console), and its parsed values are available through config().
 */
final class ConfigurableCommand extends Command
{
    /**
     * @param array $options definitions for loadOptions()
     * @param array $flags definitions for loadFlags()
     * @param array<int, array> $arguments [name, description, required, multiple] of each argument
     */
    public function __construct(private array $options = [], private array $flags = [], private array $arguments = [])
    {
        parent::__construct();

        $this->setName("configurable");
        $this->setDescription("Command of the tests");
    }

    public function boot()
    {
        if ($this->options) {
            $this->loadOptions($this->options);
        }
        if ($this->flags) {
            $this->loadFlags($this->flags);
        }
        foreach ($this->arguments as $argument) {
            $this->addArgument(new Argument(...$argument));
        }
    }

    public function execute()
    {
    }

    /** Values of the options, the flags and their defaults, once the arguments have been parsed */
    public function config(): Config
    {
        return $this->config;
    }
}
