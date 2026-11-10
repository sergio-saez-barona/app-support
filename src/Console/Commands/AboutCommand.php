<?php


namespace AppSupport\Console\Commands;


use AppSupport\Console\Command;
use AppSupport\Console\Console;
use AppSupport\Console\Terminal;
use AppSupport\Support\Facades\Config;

class AboutCommand extends Command
{
    public function __construct()
    {
        parent::__construct();

        $this->setName("about");
        $this->setDescription("Shows the short information about " . Config::get("app.info.name"));
        $this->setUsage("about");
    }

    public function boot()
    {
    }

    public function execute()
    {
        /** @var Console $console */
        $console = app();
        $console->showInfo();

        Terminal::println("Copyright:", Terminal::Yellow);
        foreach (Config::get("app.info.authors") as $author) {
            echo "  {$author}\n";
        }
        echo "\n";

        Terminal::println("Description:", Terminal::Yellow);
        // Long descriptions are wrapped to the terminal width (at most 100 columns)
        echo Terminal::wrap(Config::get("app.info.brief"), min(Terminal::getWidth(), 100), "  ") . "\n";
        echo "\n";

    }

}