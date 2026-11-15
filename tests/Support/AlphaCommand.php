<?php

namespace AppSupport\Tests\Support;

use AppSupport\Console\Command;

/**
 * Command of the tests that does nothing, to have commands to list
 */
final class AlphaCommand extends Command
{
    public function __construct()
    {
        parent::__construct();

        $this->setName("alpha");
        $this->setDescription("Does the first thing");
    }

    public function boot()
    {
    }

    public function execute()
    {
    }
}
