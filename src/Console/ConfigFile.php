<?php

namespace AppSupport\Console;

use AppSupport\Config\ConfigFileError;
use AppSupport\Config\FileParser;
use AppSupport\Config\TomlFileParser;
use AppSupport\Contracts\Config;
use AppSupport\Support\Facades\Log;

/**
 * An option (e.g. --config <file>, the name is chosen by the application) with a file that gives values to the other options of the command, so the
 * options that are always the same do not have to be written every time. The values of the file are used
 * only for the options that are not in the command line, and the options that are required are given by the file
 * too. See Command::parse() and OptionList::setFromValues().
 *
 * The option can be multiple (--config base.toml --config project.toml): every file is a layer, and the files that
 * come later in the command line have priority over the ones before them.
 *
 * The file is a TOML file (.toml). Its keys are the long names of the options, and a table with the name of a
 * command has the values of that command only. The keys that are not in a table are common to all the commands:
 *
 *     granularity = 1000          # for every command that has this option
 *
 *     [gen-gmf]                   # only for the command gen-gmf
 *     frames = "2:5"
 *     count = 20
 */
class ConfigFile extends Value
{
    /** @var array<string, FileParser> the parser of every extension of file */
    private array $parsers;

    /** @var string[] the files that were given, in the order of the command line */
    private array $files = [];

    /** @var array<string, array<string, mixed>> the contents of the files that have been read, by file */
    private array $contents = [];

    public function __construct(string $longName, string $shortName = "")
    {
        parent::__construct($longName, $shortName);
        $this->setDescription("Configuration file with values for the options (the command line has priority)");
        $this->setLabel("file");
        $this->parsers = ['toml' => new TomlFileParser()];
    }

    /**
     * Adds a parser for the files with an extension (e.g. "json"), or replaces the one that there is
     */
    public function addParser(string $extension, FileParser $parser): static
    {
        $this->parsers[strtolower($extension)] = $parser;

        return $this;
    }

    /**
     * @return string[] the extensions of the files that can be read
     */
    public function getExtensions(): array
    {
        return array_keys($this->parsers);
    }

    public function parse(string $optionStr, array &$args, bool $longName, Config $config, string $prefix): bool
    {
        if (!parent::parse($optionStr, $args, $longName, $config, $prefix)) {
            return false;
        }

        $value = $config->get($this->getKey());
        $file = is_array($value) ? end($value) : $value;
        if (!is_file($file) || !is_readable($file)) {
            Log::error("Configuration file '$file' cannot be read");

            return false;
        }
        if ($this->parserOf($file) === null) {
            Log::error("Configuration file '$file' has a format that is not supported (supported: ." . implode(", .", $this->getExtensions()) . ")");

            return false;
        }

        $this->files[] = $file;

        return true;
    }

    /**
     * The files that were given in the command line, in the order of the command line
     *
     * @return string[]
     */
    public function getFiles(): array
    {
        return $this->files;
    }

    /**
     * The values of the files that are for a command, with the file that has priority first: the last one in the
     * command line
     *
     * @param string $command name of the command
     * @param string[] $commands names of all the commands, to tell the sections of commands from other tables
     * @return array<int, array{file: string, section: array<string, mixed>, common: array<string, mixed>, unknownSections: string[]}>
     *         for every file: the values of the table of the command, the values that are not in a table, and the
     *         names of the tables that are not a command
     */
    public function valuesFor(string $command, array $commands): array
    {
        $layers = [];
        foreach (array_reverse($this->files) as $file) {
            $layer = ['file' => $file, 'section' => [], 'common' => [], 'unknownSections' => []];
            foreach ($this->read($file) as $key => $value) {
                $key = (string)$key;
                if (is_array($value) && !array_is_list($value)) {
                    // A table: the values of a command
                    if ($key === $command) {
                        $layer['section'] = $value;
                    } elseif (!in_array($key, $commands, true)) {
                        $layer['unknownSections'][] = $key;
                    }
                } else {
                    $layer['common'][$key] = $value;
                }
            }
            $layers[] = $layer;
        }

        return $layers;
    }

    /**
     * @return array<string, mixed>
     */
    private function read(string $file): array
    {
        if (!isset($this->contents[$file])) {
            try {
                $this->contents[$file] = $this->parserOf($file)->parse((string)file_get_contents($file));
            } catch (ConfigFileError $error) {
                Log::critical("Configuration file '{$file}': " . $error->getMessage());
            }
        }

        return $this->contents[$file] ?? [];
    }

    private function parserOf(string $file): ?FileParser
    {
        return $this->parsers[strtolower(pathinfo($file, PATHINFO_EXTENSION))] ?? null;
    }
}
