<?php


namespace AppSupport\Console\Commands;


use AppSupport\Console\Argument;
use AppSupport\Console\Command;
use AppSupport\Console\Console;
use AppSupport\Console\Flag;
use AppSupport\Console\Option;
use AppSupport\Console\Terminal;
use AppSupport\Console\Value;
use AppSupport\Support\Facades\Config;

class HelpCommand extends Command
{
    const COLUMN = 35;

    public function __construct()
    {
        parent::__construct();

        $this->setName("help");
        $this->setDescription("Displays help for a command");
        $this->setUsage("help [command]");
    }

    public function boot()
    {
        $this->addArgument(new Argument("command", "The command name", false));
    }

    public function execute()
    {
        /** @var Console $console */
        $console = app();
        $console->showInfo();

        $appName = Config::get("app.info.executable");

        if (count($this->actualArguments) != 1) {
            $cmd = $this;
        } else {
            $cmd = $console->findCommand($this->actualArguments[0]);
        }
        $this->showDescription($cmd);
        $this->showUsage($cmd);
        $this->showArguments($cmd);
        $this->showOptions($cmd);
        $this->showHelp($cmd);

    }

    /**
     * @param Option $option
     * @return void
     */
    public function showOptionInfo(Option $option): void
    {
        $str = "";
        /** @var Option $option */
        if (($short = $option->getShortName()) != "") {
            $str .= "-{$option->getShortName()}, ";
        } else {
            $str .= "    ";
        }

        $prefix = "";
        if ($option instanceof Flag) {
            $prefix = "[no-]";
        }
        $str .= "--{$prefix}{$option->getLongName()}";

        if ($option instanceof Value) {
            $str .= " <" . $option->getLabel() . ">";
        }

        if (!$option->isRequired()) {
            $format = sprintf("  %%-%ds  ", self::COLUMN - 2);
        } else {
            $color = Terminal::getColor(Terminal::Red);
            $format = sprintf("  %%-%ds  ", self::COLUMN - 2 + strlen($color));
            $str .= " {$color}[required]";
        }

        Terminal::color(Terminal::Green);
        $text = sprintf($format, $str);
        echo $text;
        Terminal::reset();

        $description = $option->getDescription();
        if ($option instanceof Flag) {
            $description .= "\n   Status: " . (($option->getDefault() ? "true" : "false"));
        } elseif ($option instanceof Value) {
            $default = $option->getDefault();
            if ($default !== null && trim($default) !== "") {
                $description .= "\n   Default: '$default' ";
            }
            $values = $option->getValidValues();
            if (count($values) > 0) {
                $description .= "\n   Values: " . implode(", ", $values);
            }
        }

        $description = str_replace("\n",
            "\n" . str_repeat(" ", self::COLUMN + 4),
            $description);
        echo $description;
        echo "\n";
    }

    private function showUsage(Command $cmd)
    {
        Terminal::println("Usage:", Terminal::Yellow);
        echo "  " . trim($cmd->getUsage()) . "\n";
        echo "\n";
    }

    private function showDescription(Command $cmd)
    {
//        Terminal::println("Description:", Terminal::Yellow);
//        echo "  " . trim($cmd->getDescription()) . "\n\n";
        echo trim($cmd->getDescription()) . "\n\n";
    }

    private function showHelp(Command $cmd)
    {
        if ($cmd->getHelp() != "") {
            Terminal::println("Help:", Terminal::Yellow);
            echo "  " . str_replace("\n", "\n  ", trim($cmd->getHelp())) . "\n\n";
        }
    }

    private function showArguments(Command $cmd)
    {
        $arguments = $cmd->getArguments();
        if (count($arguments) > 0) {
            Terminal::println("Arguments:", Terminal::Yellow);
            foreach ($arguments as $arg) {
                $str = $arg->getName();

                if (!$arg->isRequired()) {
                    $format = sprintf("  %%-%ds  ", self::COLUMN);
                } else {
                    $color = Terminal::getColor(Terminal::Red);
                    $format = sprintf("  %%-%ds  ", self::COLUMN + strlen($color));
                    $str .= " {$color}[required]";
                }

                Terminal::color(Terminal::Green);
                echo sprintf($format, $str);
                Terminal::reset();

                $description = str_replace("\n",
                    "\n" . str_repeat(" ", self::COLUMN + 4),
                    $arg->getDescription());

                echo $description;
                echo "\n";
            }
            echo "\n";
        }
    }

    private function showOptions(Command $cmd)
    {
        $options = $cmd->getOptionList()->getOptions();
        if (count($options) > 0) {
            $noOptions = true;
            $noFlags = true;

            /** @var Option $option */
            // Required options with parameters
            foreach ($options as $option) {
                if ($option->isRequired() && !($option instanceof Flag)) {
                    if ($noOptions) {
                        $noOptions = false;
                        Terminal::println("Options:", Terminal::Yellow);
                    }
                    $this->showOptionInfo($option);
                }
            }
            // Not required options with parameters
            foreach ($options as $option) {
                if (!$option->isRequired() && !($option instanceof Flag)) {
                    if ($noOptions) {
                        $noOptions = false;
                        Terminal::println("Options:", Terminal::Yellow);
                    }
                    $this->showOptionInfo($option);
                }
            }
            // Required flags
            foreach ($options as $option) {
                if ($option->isRequired() && $option instanceof Flag) {
                    if ($noFlags) {
                        $noFlags = false;
                        if (!$noOptions) echo "\n";
                        Terminal::println("Flags:", Terminal::Yellow);
                    }
                    $this->showOptionInfo($option);
                }
            }
            // Not required flags
            foreach ($options as $option) {
                if (!$option->isRequired() && $option instanceof Flag) {
                    if ($noFlags) {
                        $noFlags = false;
                        if (!$noOptions) echo "\n";
                        Terminal::println("Flags:", Terminal::Yellow);
                    }
                    $this->showOptionInfo($option);
                }
            }
            echo "\n";
        }
    }


}