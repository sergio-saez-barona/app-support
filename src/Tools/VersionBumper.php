<?php

namespace AppSupport\Tools;

/**
 * Reads and changes the version of a PHP application or library, in the places where it is written:
 * the "version" of composer.json and the 'version' and 'date' of the 'info' array of config/app.php.
 *
 * The files are edited in place, changing only the values, so their format, order and comments stay
 * as they are (a file is never decoded and encoded again).
 */
final class VersionBumper
{
    public const LEVELS = ['major', 'minor', 'patch'];

    /**
     * @return int[] major, minor and patch
     * @throws \InvalidArgumentException if it is not of the form MAJOR.MINOR.PATCH
     */
    public static function parse(string $version): array
    {
        if (!preg_match('/^(\d+)\.(\d+)\.(\d+)$/', $version, $matches)) {
            throw new \InvalidArgumentException("version '{$version}' is not of the form MAJOR.MINOR.PATCH");
        }

        return [(int)$matches[1], (int)$matches[2], (int)$matches[3]];
    }

    /**
     * The next version: major (1.4.2 -> 2.0.0), minor (1.4.2 -> 1.5.0) or patch (1.4.2 -> 1.4.3)
     *
     * @throws \InvalidArgumentException
     */
    public static function bump(string $version, string $level): string
    {
        [$major, $minor, $patch] = self::parse($version);

        switch ($level) {
            case 'major':
                return ($major + 1) . '.0.0';
            case 'minor':
                return $major . '.' . ($minor + 1) . '.0';
            case 'patch':
                return $major . '.' . $minor . '.' . ($patch + 1);
        }

        throw new \InvalidArgumentException("unknown level '{$level}'. Expected: " . implode(', ', self::LEVELS));
    }

    /**
     * The "version" of the composer.json, or null if it has none
     *
     * @throws \InvalidArgumentException if the text is not a JSON object
     */
    public static function readComposerVersion(string $json): ?string
    {
        $data = json_decode($json, true);
        if (!is_array($data)) {
            throw new \InvalidArgumentException('composer.json is not valid JSON: ' . json_last_error_msg());
        }

        return is_string($data['version'] ?? null) ? $data['version'] : null;
    }

    /**
     * The text of the composer.json with another "version" (the one of the top level of the object)
     *
     * @throws \InvalidArgumentException if it has no "version"
     */
    public static function writeComposerVersion(string $json, string $version): string
    {
        if (self::readComposerVersion($json) === null) {
            throw new \InvalidArgumentException("composer.json has no \"version\"");
        }

        $key = '"version"(\s*:\s*)"[^"\\\\]*"';
        $new = null;

        // The keys of the top level are the ones with the least indentation
        if (preg_match_all('/^([ \t]*)"(?:[^"\\\\]|\\\\.)*"\s*:/m', $json, $indents) && $indents[1]) {
            $top = $indents[1][0];
            foreach ($indents[1] as $indent) {
                if (strlen($indent) < strlen($top)) {
                    $top = $indent;
                }
            }
            $new = preg_replace('/^' . preg_quote($top, '/') . $key . '/m', $top . '"version"${1}"' . $version . '"', $json, 1, $count);
            if ($count == 0) {
                $new = null;
            }
        }

        // Without lines (e.g. everything in one line): the first one, which is checked below
        $new ??= preg_replace('/' . $key . '/', '"version"${1}"' . $version . '"', $json, 1);

        if ($new === null || self::readComposerVersion($new) !== $version) {
            throw new \InvalidArgumentException('could not change the "version" of composer.json');
        }

        return $new;
    }

    /**
     * Changes the 'version' and the 'date' of the 'info' array of a config/app.php. They are strings
     * (a literal in quotes), and the rest of the file is not changed. A null value is not changed.
     *
     * @return array{source: string, version: array{found: bool, old: ?string}, date: array{found: bool, old: ?string}}
     *         the new text, and for each key if it was found and its old value
     */
    public static function updateAppConfig(string $source, ?string $version, ?string $date): array
    {
        $tokens = token_get_all($source);
        $wanted = ['version' => $version, 'date' => $date];
        $result = ['version' => ['found' => false, 'old' => null], 'date' => ['found' => false, 'old' => null]];
        $replace = [];

        $depth = 0;
        $infoDepth = null;      // depth of the elements of the 'info' array, while inside it
        $armed = false;         // after 'info' =>, until the array starts
        $done = false;
        foreach ($tokens as $index => $token) {
            if (is_string($token) && in_array($token, ['[', '(', '{'], true)) {
                $depth++;
                if ($armed && $token !== '{') {
                    $infoDepth = $depth;
                    $armed = false;
                }
                continue;
            }
            if (is_string($token) && in_array($token, [']', ')', '}'], true)) {
                $depth--;
                if ($infoDepth !== null && $depth < $infoDepth) {
                    $infoDepth = null;
                    $done = true;
                }
                continue;
            }
            if (is_array($token) && in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }
            if ($armed) {
                if (!(is_array($token) && in_array($token[0], [T_DOUBLE_ARROW, T_ARRAY], true))) {
                    // 'info' is not an array
                    $armed = false;
                    $done = true;
                }
                continue;
            }
            if ($done || !self::isString($token)) {
                continue;
            }

            $key = self::stringValue($token);
            if ($infoDepth === null) {
                if ($key === 'info' && self::isArrow($tokens, self::next($tokens, $index))) {
                    $armed = true;
                }
                continue;
            }
            if ($depth !== $infoDepth || !isset($wanted[$key])) {
                if ($depth === $infoDepth && array_key_exists($key, $wanted)) {
                    // Wanted, but with no new value: only its old value is read
                    $value = self::valueOf($tokens, $index);
                    if ($value !== null) {
                        $result[$key] = ['found' => true, 'old' => self::stringValue($tokens[$value])];
                    }
                }
                continue;
            }

            $value = self::valueOf($tokens, $index);
            if ($value !== null) {
                $result[$key] = ['found' => true, 'old' => self::stringValue($tokens[$value])];
                $replace[$value] = $wanted[$key];
            }
        }

        $text = '';
        foreach ($tokens as $index => $token) {
            $text .= is_array($token) ? $token[1] : $token;
            if (isset($replace[$index])) {
                $quote = $token[1][0];
                $text = substr($text, 0, -strlen($token[1])) . $quote . $replace[$index] . $quote;
            }
        }

        return ['source' => $text] + $result;
    }

    // ---- Tokens

    private static function isString(mixed $token): bool
    {
        return is_array($token) && $token[0] === T_CONSTANT_ENCAPSED_STRING;
    }

    /** The text of a string literal, without the quotes */
    private static function stringValue(array $token): string
    {
        return substr($token[1], 1, -1);
    }

    private static function isArrow(array $tokens, ?int $index): bool
    {
        return $index !== null && is_array($tokens[$index]) && $tokens[$index][0] === T_DOUBLE_ARROW;
    }

    /** Index of the next token that is not a blank or a comment */
    private static function next(array $tokens, int $index): ?int
    {
        for ($i = $index + 1; $i < count($tokens); $i++) {
            if (!is_array($tokens[$i]) || !in_array($tokens[$i][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                return $i;
            }
        }

        return null;
    }

    /**
     * Index of the string literal that is the whole value of the key at the index ('key' => 'value'), or null
     * (it is not if it is an expression, like 'a' . 'b')
     */
    private static function valueOf(array $tokens, int $key): ?int
    {
        $arrow = self::next($tokens, $key);
        if (!self::isArrow($tokens, $arrow)) {
            return null;
        }
        $value = self::next($tokens, $arrow);
        if ($value === null || !self::isString($tokens[$value])) {
            return null;
        }

        $end = self::next($tokens, $value);

        return $end !== null && in_array($tokens[$end], [',', ']', ')'], true) ? $value : null;
    }
}
