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

        $this->commands = array_merge($this->commands, $config["commands"]);
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
     * @return array
     */
    public function getCommands(): array
    {
        return $this->commands;
    }
}
