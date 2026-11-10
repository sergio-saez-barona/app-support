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

        $sections = $console->getSections();
        if (count($sections) == 0) {
            Terminal::println("Available commands:", Terminal::Yellow);
            $commands = $console->getCommands();
            asort($commands);
            $this->printCommands($commands);
        } else {
            // Commands that do not belong to any section (e.g. built-in ones)
            $grouped = [];
            foreach ($sections as $section) {
                $grouped += $section["commands"];
            }
            $others = array_diff_key($console->getCommands(), $grouped);
            if (count($others) > 0) {
                $sections[] = [
                    "title" => "Other commands",
                    "description" => "",
                    "sorted" => true,
                    "commands" => $others,
                ];
            }

            foreach ($sections as $section) {
                Terminal::println($section["title"] . ":", Terminal::Yellow);
                if ($section["description"] !== "") {
                    echo "  {$section["description"]}\n\n";
                }

                $commands = $section["commands"];
                if ($section["sorted"]) {
                    ksort($commands);
                }
                $this->printCommands($commands);
                echo "\n";
            }

            return;
        }

        echo "\n";
    }

    /**
     * @param array<string, string> $commands name => classname
     */
    private function printCommands(array $commands): void
    {
        foreach ($commands as $name => $classname) {
            /** @var Command $cmd */
            $cmd = new $classname();

            echo sprintf("  %s%-20s%s %s\n",
                Terminal::getColor(Terminal::Green),
                $name,
                Terminal::getReset(),
                $cmd->getDescription());
        }
    }

}
