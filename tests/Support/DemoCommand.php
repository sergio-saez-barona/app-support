<?php

namespace AppSupport\Tests\Support;

use AppSupport\Console\Argument;
use AppSupport\Console\Command;

/**
 * Command to check the help: labels of several lengths, descriptions with several lines and long ones
 */
final class DemoCommand extends Command
{
    public function __construct()
    {
        parent::__construct();

        $this->setName("demo");
        $this->setDescription("Demonstrates the help of the commands, with a description that is long enough to need more than one line in a narrow terminal");
        $this->setUsage("demo [options] <source> [files ...]");
    }

    public function boot()
    {
        $this->loadOptions([
            'n|count' => [
                'description' => 'Number of tasks in the set, which has to be a positive number and is limited by the size of the system',
                'required' => true,
                'label' => 'n',
            ],
            'U|utilization' => [
                'description' => 'Total utilization',
                'required' => true,
                'label' => 'u',
            ],
            'm|mutation' => [
                'description' => 'Percentages of the tasks that change',
                'required' => true,
                'label' => 'unchanged:modified',
            ],
            'p|periods' => [
                'description' => "Period range\n"
                    . "log-uniform: uniform in the exponent;\n"
                    . "harmonic: every period divides the longer ones, so the hyperperiod is the longest period of the set;\n"
                    . "power: powers of the base",
                'label' => 'min:max',
            ],
            'mechanism' => [
                'description' => 'How periods are chosen',
                'default' => 'log-uniform',
                'validValues' => ['log-uniform', 'harmonic', 'power'],
            ],
        ]);

        $this->loadFlags([
            'grid' => ['description' => 'Draw the background grid, which is useful to read the values in the chart', 'default' => true],
            'v|verbose' => ['description' => 'Verbose output'],
        ]);

        $this->addArgument(new Argument('source', 'Source file with a description that is long enough to wrap in a narrow terminal', true));
        $this->addArgument(new Argument('files', 'More files', false, true));

        $this->setHelp("Some help text that is long enough to be wrapped in a narrow terminal, to check that the help is wrapped too.\nSecond paragraph.");
    }

    public function execute()
    {
    }
}
