<?php

namespace AppSupport\Console;

use AppSupport\Contracts\Config;
use AppSupport\Support\Facades\Log;

abstract class Command extends CLIItem
{
    protected ?Config $config = null;
    protected array $actualArguments = [];
    private ?OptionList $optionList = null;
    private array $argumentsList = [];

    private ?Argument $last = null;
    private string $help = "";
    private string $usage = "command [options] [arguments]";


    /**
     * Command constructor.
     */
    public function __construct()
    {
        $this->optionList = new OptionList();
    }

    /**
     * @return OptionList|null
     */
    public function getOptionList(): ?OptionList
    {
        return $this->optionList;
    }

    /**
     * @param OptionList $optionList
     * @return Command
     */
    public function setOptionList(OptionList $optionList): static
    {
        $this->optionList = $optionList;

        return $this;
    }

    public function addArgument(Argument $argument): void
    {
        if (!is_null($this->last) && $this->last->isMultiple()) {
            Log::alert("No more arguments can be added after a multivalued one ('{$this->last->getName()}')");
        }

        $this->argumentsList[$argument->getName()] = $argument;
        $this->last = $argument;
    }

    /**
     * @return Argument[]
     */
    public function getArguments(): array
    {
        return $this->argumentsList;
    }

    /**
     * @return array
     */
    public function getActualArguments(): array
    {
        return $this->actualArguments;
    }

    /**
     * @return string
     */
    public function getHelp(): string
    {
        return $this->help;
    }

    /**
     * @param string $help
     */
    public function setHelp(string $help): void
    {
        $this->help = $help;
    }

    /**
     * @return string
     */
    public function getUsage(): string
    {
        return $this->usage;
    }

    /**
     * @param string $usage
     */
    public function setUsage(string $usage): void
    {
        $this->usage = $usage;
    }


    /**
     * Parse the list of arguments for this command
     * @param array $args
     * @param bool $useInternalConfig
     * @return void
     */
    public function parse(array &$args, bool $useInternalConfig = false): void
    {
        if ($useInternalConfig) {
            // Creates a local repository
            $this->config = app("repository");
            // Local repository requires no prefix
            $configPrefix = "";
        } else {
            $this->config = app("config");
            $configPrefix = OptionList::defaultPrefix . "{$this->name}.";
        }

        $this->optionList->setConfigPrefix($configPrefix);

        // Set default values in the repository
        $this->optionList->configDefaults($this->config);

        // Parse provided options first
        while (count($args) > 0 && OptionList::isOption($args)) {
            $this->optionList->parseOption($args, $this->config);
        }

        $argList = $this->argumentsList;

        // Parse provided arguments
        while (count($args) > 0) {
            if (OptionList::isOption($args)) {
                $argStr = array_shift($args);
                Log::critical("Unexpected option '$argStr'");
            } else {
                $argStr = array_shift($args);
                if (count($argList) == 0) {
                    Log::critical("Unexpected argument '$argStr'");
                }

                // Arguments are parsed in order
                /** @var Argument $argSpec */
                $argSpec = array_shift($argList);
                if ($argSpec->parse($argStr)) {
                    $this->actualArguments[] = $argStr;
                } else {
                    Log::critical("Invalid argument '$argStr'");
                }

                // It accepts more instances, so push back into the list
                if ($argSpec->isMultiple()) {
                    array_unshift($argList, $argSpec);
                }
            }
        }

        if (count($argList)) {
            $error = false;
            foreach ($argList as $argSpec) {
                if ($argSpec->isRequired() && !$argSpec->isSet()) {
                    $error = true;
                    Log::error("Argument '{$argSpec->getName()}' is required");
                }
            }
            if ($error) {
                Log::critical("Required arguments are missed in command '{$this->name}'. See 'help {$this->name}' for details");
            }
        }

        // Check if all required options have been provided
        $error = $this->optionList->checkRequired();
        if ($error) {
            Log::critical("Required options are missed in command '{$this->name}'. See 'help {$this->name}' for details");
        }

        if (!$useInternalConfig) {
            $this->config = $this->config->reference(rtrim($configPrefix, "."));
        }

    }

    public function loadOptions(array $definitions): void
    {
        foreach ($definitions as $definitionKey => $definitionData) {
            // Support "short|long" syntax for short name aliases
            $shortName = "";
            $longName = $definitionKey;
            if (str_contains($definitionKey, '|')) {
                [$shortName, $longName] = explode('|', $definitionKey, 2);
            }

            $definition = new Value($longName, $shortName);

            if (is_array($definitionData)) {
                $definitionDefault = $definitionData[0];
                $definition->setDescription($definitionData[1]);
                if (isset($definitionData[2])) {
                    $multiple = $definitionData[2];
                    if (gettype($multiple) !== "boolean") {
                        Log::critical("Multiple flag in '$definitionKey' requires a boolean value");
                    }
                    $definition->setMultiple($multiple);
                }
            } else {
                $definitionDefault = $definitionData;
            }
            if (!empty($definitionDefault)) {
                $definition->setDefault($definitionDefault);
            }

            $this->getOptionList()->addOption($definition);
        }
    }

    public function loadFlags(array $flags): void
    {
        foreach ($flags as $flagKey => $flagData) {
            $flag = new Flag($flagKey);
            if (is_array($flagData)) {
                $flagDefault = $flagData[0];
                $flag->setDescription($flagData[1]);
            } else {
                $flagDefault = $flagData;
            }

            if (gettype($flagDefault) !== "boolean") {
                Log::critical("Flag '$flagKey' requires a boolean value");
            }
            $flag->setDefault($flagDefault);

            $this->getOptionList()->addOption($flag);
        }
    }

    /**
     * Set up the internal command parameters
     */
    abstract public function boot();

    /**
     * Execute the command once the arguments have been parsed
     */
    abstract public function execute();
}
