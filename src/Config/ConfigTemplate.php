<?php
namespace AppSupport\Config;

use AppSupport\Console\ConfigFile;
use AppSupport\Console\Flag;
use AppSupport\Console\Command;
use AppSupport\Console\Option;
use AppSupport\Console\Value;

/**
 * Builds the template of a configuration file (TOML) for a command: a table with the name of the command
 * and, for each of its options, its description and its default value, all of it commented, so the file
 * only fixes what the user uncomments.
 *
 * The options of type config-file are not in the template (they cannot be in a configuration file), and
 * neither are the arguments of the command.
 */
class ConfigTemplate
{
    public static function for(Command $command): string
    {
        $lines = [
            "# Values for the options of the command '{$command->getName()}', for its configuration file option.",
            "# Uncomment and edit what is needed: the command line has priority over this file.",
            "",
            "[{$command->getName()}]",
        ];
        foreach ($command->getOptionList()->getOptions() as $option) {
            if ($option instanceof ConfigFile) {
                continue;
            }
            $lines[] = "";
            array_push($lines, ...self::describe($option));
        }
        return implode("\n", $lines) . "\n";
    }

    /** @return string[] */
    private static function describe(Option $option): array
    {
        $lines = [];
        foreach (explode("\n", trim($option->getDescription())) as $line) {
            $lines[] = rtrim("# " . trim($line));
        }
        if ($option instanceof Value && count($option->getValidValues()) > 0) {
            $lines[] = "# Values: " . implode(", ", $option->getValidValues());
        }
        if ($option->isRequired()) {
            $lines[] = "# Required";
        }
        $lines[] = "# {$option->getLongName()} = " . self::value($option);
        return $lines;
    }

    /** The default value of the option, or a placeholder (its label) if it has none */
    private static function value(Option $option): string
    {
        if ($option instanceof Flag) {
            return $option->getDefault() ? "true" : "false";
        }
        /** @var Value $option */
        $default = $option->getDefault();
        $item = self::scalar($default ?? $option->getLabel());
        return $option->isMultiple() ? "[{$item}]" : $item;
    }

    /** A text as a TOML value: a number if it is written like one, a string otherwise */
    public static function scalar(string $text): string
    {
        if (preg_match('/^-?(0|[1-9]\d*)(\.\d+)?$/', $text)) {
            return $text;
        }
        $escaped = preg_replace_callback(
            '/[\\\\"\x00-\x1f\x7f]/',
            fn(array $m) => match ($m[0]) {
                '\\' => '\\\\',
                '"' => '\\"',
                "\n" => '\\n',
                "\t" => '\\t',
                "\r" => '\\r',
                default => sprintf('\\u%04X', ord($m[0])),
            },
            $text,
        );
        return "\"{$escaped}\"";
    }
}
