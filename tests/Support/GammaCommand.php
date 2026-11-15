<?php

namespace AppSupport\Tests\Support;

use AppSupport\Console\Command;

/**
 * Command of the tests that does nothing, to have commands to list
 */
final class GammaCommand extends Command
{
    public function __construct()
    {
        parent::__construct();

        $this->setName("gamma");
        $this->setDescription("Does the third thing");
    }

    public function boot()
    {
    }

    public function execute()
    {
    }
}
