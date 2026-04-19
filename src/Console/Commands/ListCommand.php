<?php


namespace AppSupport\Console\Commands;


use AppSupport\Console\Command;
use AppSupport\Console\Console;
use AppSupport\Console\Terminal;

class ListCommand extends Command
{
    public function __construct()
    {
        parent::__construct();

        $this->setName("list");
        $this->setDescription("Lists available commands");
        $this->setUsage("list");
    }

    public function boot()
    {

    }

    public function execute()
    {
        /** @var Console $console */
        $console = app();
        $console->showInfo();

        Terminal::println("Usage:", Terminal::Yellow);
        echo "  command [options] [arguments]\n\n";

        Terminal::println("Available commands:", Terminal::Yellow);

        $commands = $console->getCommands();
        asort($commands);
        foreach ($commands as $name => $classname) {

            /** @var Command $cmd */
            $cmd = new $classname();

            echo sprintf("  %s%-20s%s %s\n",
                Terminal::getColor(Terminal::Green),
                $name,
                Terminal::getReset(),
                $cmd->getDescription());
        }

        echo "\n";
    }

}