<?php
namespace AppSupport\Tests\Support;

use AppSupport\Console\Argument;
use AppSupport\Console\Command;

/**
 * Command to check the template of the configuration file: every kind of option
 */
final class TemplateCommand extends Command
{
    public function __construct()
    {
        parent::__construct();
        $this->setName("template");
        $this->setDescription("Command of the tests");
    }

    public function boot()
    {
        $this->loadOptions([
            'c|config' => ['description' => 'Configuration file', 'type' => 'config-file', 'multiple' => true],
            'n|count' => ['description' => 'Number of tasks', 'required' => true, 'label' => 'n'],
            'periods' => ['description' => "Period range\nin granularity units", 'label' => 'min:max'],
            'policy' => ['description' => 'Policy', 'default' => 'fixed', 'validValues' => ['none', 'fixed', 'edf']],
            'granularity' => ['description' => 'Granularity', 'default' => '1000'],
            'zero' => ['description' => 'Starts with zero', 'default' => '007'],
            'name' => ['description' => 'Name', 'default' => "say \"hi\"\\\n"],
            'I|include' => ['description' => 'Include paths', 'multiple' => true, 'default' => 'src'],
            'tag' => ['description' => 'Tags', 'multiple' => true],
        ]);
        $this->loadFlags([
            'grid' => ['description' => 'Draw the grid', 'default' => true],
            'v|verbose' => ['description' => 'Verbose output'],
        ]);
        $this->addArgument(new Argument('source', 'Source file'));
    }

    public function execute()
    {
    }
}
