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

    /**
     * Bulk-loads Value options.
     *
     * Each entry is "long" => definition or "short|long" => definition, where definition is:
     *  - a scalar or null: the default value (null = no default);
     *  - a list [default, description, multiple?] (legacy positional format);
     *  - an associative array with the keys:
     *      description (string, required), default (string|null), required (bool),
     *      multiple (bool), validValues (string[]), label (string).
     *
     * @param array $definitions
     */
    public function loadOptions(array $definitions): void
    {
        foreach ($definitions as $definitionKey => $definitionData) {
            $definitionKey = (string)$definitionKey;

            // Support "short|long" syntax for short name aliases
            $shortName = "";
            $longName = $definitionKey;
            if (str_contains($definitionKey, '|')) {
                [$shortName, $longName] = explode('|', $definitionKey, 2);
            }

            $definition = new Value($longName, $shortName);

            if (is_array($definitionData) && self::isAssociative($definitionData)) {
                $this->loadValueFromAssociative($definition, $definitionKey, $definitionData);
            } else {
                $this->loadValueFromPositional($definition, $definitionKey, $definitionData);
            }

            $this->getOptionList()->addOption($definition);
        }
    }

    private static function isAssociative(array $array): bool
    {
        foreach (array_keys($array) as $key) {
            if (is_string($key)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Legacy format: scalar|null, or [default, description, multiple?]
     */
    private function loadValueFromPositional(Value $definition, string $key, mixed $data): void
    {
        if (is_array($data)) {
            $default = $data[0];
            $definition->setDescription($data[1]);
            if (isset($data[2])) {
                if (gettype($data[2]) !== "boolean") {
                    Log::critical("Multiple flag in '$key' requires a boolean value");
                }
                $definition->setMultiple($data[2]);
            }
        } else {
            $default = $data;
        }

        if ($default !== null) {
            $definition->setDefault($default);
        }
    }

    /**
     * Associative format with named fields
     */
    private function loadValueFromAssociative(Value $definition, string $key, array $data): void
    {
        $allowed = ["description", "default", "required", "multiple", "validValues", "label"];
        foreach (array_keys($data) as $field) {
            if (!in_array($field, $allowed, true)) {
                Log::critical("Unknown field '$field' in option '$key'. Allowed: " . implode(", ", $allowed));
            }
        }

        if (!isset($data["description"]) || !is_string($data["description"])) {
            Log::critical("Option '$key' requires a 'description' string");
        }
        $definition->setDescription($data["description"]);

        foreach (["required", "multiple"] as $field) {
            if (isset($data[$field]) && !is_bool($data[$field])) {
                Log::critical("Field '$field' in option '$key' requires a boolean value");
            }
        }

        if (isset($data["validValues"])) {
            if (!is_array($data["validValues"])) {
                Log::critical("Field 'validValues' in option '$key' requires an array");
            }
            $definition->setValidValues(array_values(array_map('strval', $data["validValues"])));
        }

        if (isset($data["label"])) {
            $definition->setLabel((string)$data["label"]);
        }

        $default = $data["default"] ?? null;
        if (!empty($data["required"])) {
            if ($default !== null) {
                Log::critical("Option '$key' cannot be required and have a default value");
            }
            $definition->setRequired(true);
        }

        if ($default !== null) {
            $default = (string)$default;
            $valid = $definition->getValidValues();
            if (count($valid) > 0 && !in_array($default, $valid)) {
                Log::critical("Default value '$default' of option '$key' is not one of its valid values");
            }
            $definition->setDefault($default);
        }

        if (isset($data["multiple"])) {
            $definition->setMultiple($data["multiple"]);
        }
    }

    /**
     * Bulk-loads Flag options (--name / --no-name).
     *
     * Each entry is "long" => definition or "short|long" => definition, where definition is:
     *  - a bool: the default value;
     *  - a list [default, description] (legacy positional format);
     *  - an associative array with the keys: description (string, required), default (bool, false if omitted).
     *
     * @param array $flags
     */
    public function loadFlags(array $flags): void
    {
        foreach ($flags as $flagKey => $flagData) {
            $flagKey = (string)$flagKey;

            // Support "short|long" syntax for short name aliases
            $shortName = "";
            $longName = $flagKey;
            if (str_contains($flagKey, '|')) {
                [$shortName, $longName] = explode('|', $flagKey, 2);
            }

            $flag = new Flag($longName, $shortName);

            if (is_array($flagData) && self::isAssociative($flagData)) {
                foreach (array_keys($flagData) as $field) {
                    if (!in_array($field, ["description", "default"], true)) {
                        Log::critical("Unknown field '$field' in flag '$flagKey'. Allowed: description, default");
                    }
                }
                if (!isset($flagData["description"]) || !is_string($flagData["description"])) {
                    Log::critical("Flag '$flagKey' requires a 'description' string");
                }
                $flag->setDescription($flagData["description"]);
                $flagDefault = $flagData["default"] ?? false;
            } elseif (is_array($flagData)) {
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
