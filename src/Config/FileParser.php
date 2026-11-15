<?php

namespace AppSupport\Config;

/**
 * Reads the contents of a configuration file (the file of a ConfigFile option) as a PHP array
 */
interface FileParser
{
    /**
     * @param string $contents the text of the file
     * @return array<string, mixed> the keys of the file, with their values (texts, numbers, booleans and
     *         lists), and the tables as arrays with names
     * @throws ConfigFileError if the text is not valid
     */
    public function parse(string $contents): array;
}
