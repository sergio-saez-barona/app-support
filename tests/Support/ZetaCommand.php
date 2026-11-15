<?php

namespace AppSupport\Tests\Support;

use AppSupport\Console\Command;

/**
 * Command of the tests that does nothing, to have commands to list
 */
final class ZetaCommand extends Command
{
    public function __construct()
    {
        parent::__construct();

        $this->setName("zeta");
        $this->setDescription("Does the last thing");
    }

    public function boot()
    {
    }

    public function execute()
    {
    }
}
