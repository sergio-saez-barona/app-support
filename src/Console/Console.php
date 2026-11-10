<?php
/**
 * Created by PhpStorm.
 * User: ssaez
 * Date: 28/11/17
 * Time: 15:48
 */

namespace AppSupport\Console;

use AppSupport\Config\Repository;
use AppSupport\Support\Container;
use AppSupport\Support\Facades\Config;
use AppSupport\Support\Facades\Facade;
use AppSupport\Support\Facades\Log;

class Console extends Container
{
    private ?string $basePath = null;
    private string $defaultCommand = "list";
    /**
     * Normalized command sections (empty when the flat "commands" configuration is used)
     * @var array<int, array{title: string, description: string, sorted: bool, commands: array<string, string>}>
     */
    private array $sections = [];
    private array $commands = [
        "help" => \AppSupport\Console\Commands\HelpCommand::class,
        "list" => \AppSupport\Console\Commands\ListCommand::class,
        "about" => \AppSupport\Console\Commands\AboutCommand::class,
    ];

    /**
     * Console constructor.
     * @param string $basePath
     */
    public function __construct(string $basePath)
    {
        $this->instance("app", $this);
        Container::setInstance($this);
        Facade::setFacadeApplication($this);

        $this->basePath = $basePath;

        $this->registerBuiltInServices();
        $this->processConsoleConfiguration();
        $this->processAppConfiguration();
    }

    private function registerBuiltInServices()
    {
        // Builtin services
        $this->register("repository", Repository::class);

        // Config interface and singleton to support Config facade
        $this->alias(\AppSupport\Contracts\Config::class, "config");
        $this->singleton(\AppSupport\Contracts\Config::class, Repository::class);

        // Config interface and singleton to support Log facade
        $this->alias(\Psr\Log\LoggerInterface::class, "logger");
        $this->singleton(\Psr\Log\LoggerInterface::class, \AppSupport\Support\Logger::class);


    }

    private function processConsoleConfiguration()
    {
        // Load console configuration
        $config = Config::load("console");

        if (isset($config["sections"])) {
            if (isset($config["commands"])) {
                Log::critical("Console configuration: 'commands' and 'sections' cannot be used together");
            }

            // The hierarchy is flattened for command resolution
            $this->sections = $this->normalizeSections($config["sections"]);
            $flat = [];
            foreach ($this->sections as $section) {
                $flat = array_merge($flat, $section["commands"]);
            }
            $this->commands = array_merge($this->commands, $flat);
        } else {
            $this->commands = array_merge($this->commands, $config["commands"] ?? []);
        }
    }

    /**
     * Validates the 'sections' configuration and returns it in a normalized form
     *
     * @param mixed $sections
     * @return array
     */
    private function normalizeSections(mixed $sections): array
    {
        if (!is_array($sections)) {
            Log::critical("Console configuration: 'sections' requires an array");
        }

        $result = [];
        $seen = [];
        foreach ($sections as $index => $section) {
            if (!is_array($section)) {
                Log::critical("Console configuration: section #$index requires an array");
            }
            foreach (array_keys($section) as $field) {
                if (!in_array($field, ["title", "description", "sorted", "commands"], true)) {
                    Log::critical("Console configuration: unknown field '$field' in section #$index");
                }
            }
            if (!isset($section["title"]) || !is_string($section["title"])) {
                Log::critical("Console configuration: section #$index requires a 'title' string");
            }
            $title = $section["title"];
            if (isset($section["description"]) && !is_string($section["description"])) {
                Log::critical("Console configuration: 'description' in section '$title' requires a string");
            }
            if (isset($section["sorted"]) && !is_bool($section["sorted"])) {
                Log::critical("Console configuration: 'sorted' in section '$title' requires a boolean");
            }
            if (!isset($section["commands"]) || !is_array($section["commands"])) {
                Log::critical("Console configuration: section '$title' requires a 'commands' array");
            }

            foreach ($section["commands"] as $name => $classname) {
                if (isset($seen[$name])) {
                    Log::critical("Console configuration: command '$name' is defined in sections '{$seen[$name]}' and '$title'");
                }
                $seen[$name] = $title;
            }

            $result[] = [
                "title" => $title,
                "description" => $section["description"] ?? "",
                "sorted" => $section["sorted"] ?? false,
                "commands" => $section["commands"],
            ];
        }

        return $result;
    }

    private function processAppConfiguration()
    {
        // Load app configuration
        $config = Config::load("app");

        // -----------------------------
        // Register lazy loaded services, classes and aliases
        // These services cannot have boot methods
        // -----------------------------

        // Classes registration
        foreach ($config["singletons"] ?? [] as $alias => $classname) {
            $this->singleton($alias, $classname);
        }

        // Classes registration
        foreach ($config["classes"] ?? [] as $alias => $classname) {
            $this->register($alias, $classname);
        }

        // Classes registration
        foreach ($config["aliases"] ?? [] as $alias => $abstract) {
            $this->alias($abstract, $alias);
        }

        // -----------------------------
        // Main services registration
        // -----------------------------
        foreach ($config["providers"] ?? [] as $alias => $classname) {
            // Register an instance and the alias used by the service
            $this->instance($alias, new $classname());
        }

        // Call boot methods, if any
        foreach (array_keys($config["providers"] ?? []) as $alias) {
            $object = $this->resolve($alias);
            if (method_exists($object, "boot")) {
                $object->boot();
            }
        }

    }

    /**
     * @return string
     */
    public function getBasePath(): string
    {
        return $this->basePath;
    }

    /**
     * @param string[] $args
     * @return Command
     */
    public function parseCommand(array $args, bool $useInternalConfig = true): Command
    {
        // Informs about the name of the used executable and removes it from the arguments array
        $appName = basename(array_shift($args));
        Config::set("app.info.executable", $appName);

        // Check if a default command is being used
        if (count($args) == 0 || OptionList::isOption($args)) {
            $commandName = $this->getDefaultCommand();

            if ($commandName == "") {
                $this->showInfo();
                Log::critical("A command has to be provided. Try '{$appName} list'");
            }
        } else {
            $commandName = array_shift($args);
        }

        $command = $this->findCommand($commandName);

        $command->parse($args, $useInternalConfig);

        return $command;
    }

    /**
     * @return string
     */
    public function getDefaultCommand(): string
    {
        return $this->defaultCommand;
    }

    /**
     * @param string $defaultCommand
     * @return Console
     */
    public function setDefaultCommand(string $defaultCommand): Console
    {
        if ($this->commandExist($defaultCommand)) {
            throw new \LogicException ("Default command '{$defaultCommand}' not found");
        }

        $this->defaultCommand = $defaultCommand;

        return $this;
    }

    public function showInfo()
    {
        $this->showLogo();
        Terminal::print(Config::get("app.info.name"), Terminal::Green);
        echo " version ";
        Terminal::print(Config::get("app.info.version"), Terminal::Yellow);
        echo " ";
        echo Config::get("app.info.date");
        echo "\n\n";
    }

    public function showLogo()
    {
        // To be overwritten
        Terminal::color(Terminal::Blue);
        echo <<<EOT
    ___                ___            __  _           
   /   |  ____  ____  / (_)________  / /_(_)___  ____ 
  / /| | / __ \/ __ \/ / / ___/ __ \/ __/ / __ \/ __ \
 / ___ |/ /_/ / /_/ / / / /__/ /_/ / /_/ / /_/ / / / /
/_/  |_/ ____/ ____/_/_/\___/\__/\_\__/_/\____/_/ /_/ 
      /_/   /_/
EOT;
        Terminal::reset();
        echo "\n";
        echo "Font: Slant, URL: https://www.ascii-art-generator.org/\n";
        echo "Change the logo overwriting the Console::showLogo method.\n";

    }

    /**
     * @param string $name
     * @return Command
     */
    public function findCommand(string $name): ?Command
    {
        if ($this->commandExist($name)) {
            // Creates the command and sets up its internal configuration
            /**
             * @var Command $command
             */
            $classname = $this->commands[$name];
            $command = new $classname();

            $command->boot();

            return $command;
        } else {
            $appName = Config::get("app.info.executable");
            Log::critical("Command '{$name}' not found. Try '{$appName} list'");

            // This is not reachable
            return null;
        }
    }

    /**
     * @param string $name
     * @return bool
     */
    private function commandExist(string $name): bool
    {
        return isset($this->commands[$name]);
    }

    /**
     * Returns the normalized command sections, in configuration order. Each section has the keys
     * title, description, sorted and commands (name => classname). It is empty when the
     * configuration uses the flat 'commands' array.
     *
     * @return array
     */
    public function getSections(): array
    {
        return $this->sections;
    }

    /**
     * @return array
     */
    public function getCommands(): array
    {
        return $this->commands;
    }
}
