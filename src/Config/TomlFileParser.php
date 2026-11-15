<?php

namespace AppSupport\Config;

use PhpCollective\Toml\Exception\ParseException;
use PhpCollective\Toml\Exception\TomlException;
use PhpCollective\Toml\Toml;

/**
 * Reads TOML files (see https://toml.io), with the php-collective/toml library
 */
class TomlFileParser implements FileParser
{
    public function parse(string $contents): array
    {
        try {
            return Toml::decode($contents);
        } catch (ParseException $error) {
            $where = $error->span !== null ? "line {$error->span->line}, column {$error->span->column}: " : '';

            throw new ConfigFileError($where . $error->getMessage() . ($error->hint !== null ? " ({$error->hint})" : ''), 0, $error);
        } catch (TomlException $error) {
            throw new ConfigFileError($error->getMessage(), 0, $error);
        }
    }
}
