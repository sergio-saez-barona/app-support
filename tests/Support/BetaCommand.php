<?php

namespace AppSupport\Tests\Support;

use AppSupport\Console\Command;

/**
 * Command of the tests that does nothing, to have commands to list
 */
final class BetaCommand extends Command
{
    public function __construct()
    {
        parent::__construct();

        $this->setName("beta");
        $this->setDescription("Does the second thing, with a description that is long enough to need several lines in a narrow terminal");
    }

    public function boot()
    {
    }

    public function execute()
    {
    }
}
