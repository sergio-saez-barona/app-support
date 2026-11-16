<?php
namespace AppSupport\Console\Commands;

use AppSupport\Config\ConfigTemplate;
use AppSupport\Console\Argument;
use AppSupport\Console\Command;
use AppSupport\Console\Console;
use AppSupport\Console\Terminal;
use AppSupport\Support\Facades\Log;

/**
 * Writes a template of a TOML configuration file for a command and their default values
 * (commented), to a file or to the standard output.
 */
class DumpConfigCommand extends Command
{
    public function __construct()
    {
        parent::__construct();
        $this->setName("dump-config");
        $this->setDescription("Writes a template of a TOML configuration file for a command");
        $this->setUsage("dump-config [--force] <command> [file]");
        $this->setHelp("The template has the description and the default value of each option, commented. " .
            "Without a file it is written to the standard output, so it can be redirected.");
    }

    public function boot()
    {
        $this->addArgument(new Argument("command", "The command name"));
        $this->addArgument(new Argument("file", "File to write (default: the standard output)", false));
        $this->loadFlags([
            "force" => ["description" => "Overwrite the file if it exists"],
        ]);
    }

    public function execute()
    {
        /** @var Console $console */
        $console = app();
        $command = $console->findCommand($this->actualArguments[0]);
        $template = ConfigTemplate::for($command);

        if (count($this->actualArguments) < 2) {
            echo $template;
            return;
        }

        $file = $this->actualArguments[1];
        if (file_exists($file) && !$this->config->get("force")) {
            Log::critical("File '{$file}' already exists. Use --force to overwrite it");
        }
        if (@file_put_contents($file, $template) === false) {
            Log::critical("Cannot write the file '{$file}'");
        }
        Terminal::println("Template of '{$command->getName()}' written to {$file}", Terminal::Green);
    }
}
