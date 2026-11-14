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
    /**
     * Column where the descriptions of the options and the arguments start. The other lines of a
     * description (e.g. the values of an option) are indented 2 columns more.
     */
    const COLUMN = 36;

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
            $format = sprintf("  %%-%ds  ", self::COLUMN - 4);
        } else {
            $color = Terminal::getColor(Terminal::Red);
            $format = sprintf("  %%-%ds  ", self::COLUMN - 4 + strlen($color));
            $str .= " {$color}[required]";
        }

        Terminal::color(Terminal::Green);
        $column = $this->printLabel(sprintf($format, $str), self::COLUMN);

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

        echo Terminal::wrapHanging($description, Terminal::getTextWidth(), $column, self::COLUMN + 2);
        echo "\n";
    }

    /**
     * Prints the label of an option or an argument (the color is already set) and leaves the cursor
     * where its description has to start: right after the label, or at the beginning of the column
     * of the descriptions in the next line if the label is too long to leave it there.
     *
     * @param string $label Label padded to the width of its column, may have color sequences
     * @param int $start Column where the descriptions start
     * @return int Column where the description starts
     */
    private function printLabel(string $label, int $start): int
    {
        if ($this->visibleLength($label) <= $start) {
            echo $label;
            Terminal::reset();

            return $this->visibleLength($label);
        }

        echo rtrim($label);
        Terminal::reset();
        echo "\n" . str_repeat(" ", $start);

        return $start;
    }

    /**
     * Number of columns that a text takes, without counting the color sequences
     */
    private function visibleLength(string $text): int
    {
        return mb_strlen(preg_replace('/\e\[[0-9;]*m/', '', $text));
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
        echo Terminal::wrapHanging(trim($cmd->getDescription()), Terminal::getTextWidth(), 0, 0, 0) . "\n\n";
    }

    private function showHelp(Command $cmd)
    {
        if ($cmd->getHelp() != "") {
            Terminal::println("Help:", Terminal::Yellow);
            echo "  " . Terminal::wrapHanging(trim($cmd->getHelp()), Terminal::getTextWidth(), 2, 2, 0) . "\n\n";
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
                    $format = sprintf("  %%-%ds  ", self::COLUMN - 4);
                } else {
                    $color = Terminal::getColor(Terminal::Red);
                    $format = sprintf("  %%-%ds  ", self::COLUMN - 4 + strlen($color));
                    $str .= " {$color}[required]";
                }

                Terminal::color(Terminal::Green);
                $column = $this->printLabel(sprintf($format, $str), self::COLUMN);

                echo Terminal::wrapHanging($arg->getDescription(), Terminal::getTextWidth(), $column, self::COLUMN + 2);
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