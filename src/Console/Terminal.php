<?php
/*
 * app-support
 * @file: Terminal.php
 * @author: ssaez
 */

namespace AppSupport\Console;


class Terminal
{
    const Bold = 1;
    const Dim = 2;
    const Underline = 4;
    const Blink = 5;
    const Reverse = 7;

    const Default = 39;
    const Black = 30;
    const Red = 31;
    const Green = 32;
    const Yellow = 33;
    const Blue = 34;
    const Magenta = 35;
    const Cyan = 36;
    const LightGray = 37;
    const DarkGray = 90;
    const LightRed = 91;
    const LightGreen = 92;
    const LightYellow = 93;
    const LightBlue = 94;
    const LightMagenta = 95;
    const LightCyan = 96;
    const White = 97;



    static public function foreground(int $color): int
    {
        return $color;
    }

    static public function background(int $color): int
    {
        return $color + 10;
    }

    static public function getColor(int $foreground, int $background = self::Default): string
    {
        $f = self::foreground($foreground);
        $b = self::background($background);
        return "\e[{$f};{$b}m";
    }

    static public function getReset(): string
    {
        return "\e[0m";
    }

    static public function getDefault(): string
    {
        $f = self::foreground(self::Default);
        $b = self::background(self::Default);
        return "\e[{$f};{$b}m";
    }

    static public function color(int $foreground, int $background = self::Default)
    {
        echo self::getColor($foreground, $background);
    }

    static public function reset() {
        echo self::getReset();
    }

    static public function default() {
        echo self::getDefault();
    }

    static public function print(string $text, int $foreground = self::Default, int $background = self::Default)
    {
        self::color($foreground, $background);
        echo $text;
        self::reset();
    }

    static public function println(string $text, int $foreground = self::Default, int $background = self::Default)
    {
        self::print($text, $foreground, $background);
        echo "\n";
    }

    /**
     * Width of the terminal in columns. It uses the COLUMNS environment variable, then the
     * terminal size when the output is a terminal, and finally the given default.
     *
     * @param int $default Width used when it cannot be determined (e.g. output redirected)
     * @return int
     */
    static public function getWidth(int $default = 80): int
    {
        $columns = getenv("COLUMNS");
        if ($columns !== false && ctype_digit($columns) && (int)$columns > 0) {
            return (int)$columns;
        }

        if (function_exists("stream_isatty") && defined("STDOUT") && stream_isatty(STDOUT)) {
            $size = @shell_exec("stty size 2>/dev/null");
            if (is_string($size) && preg_match('/^\d+\s+(\d+)/', trim($size), $matches) && (int)$matches[1] > 0) {
                return (int)$matches[1];
            }
        }

        return $default;
    }

    /**
     * Word-wraps a text (multibyte safe). Existing line breaks are kept. Words longer than the
     * available width are not split.
     *
     * @param string $text Text to wrap
     * @param int $width Maximum line width, including the indentation
     * @param string $indent String prepended to every line
     * @return string The wrapped text, without trailing newline
     */
    static public function wrap(string $text, int $width, string $indent = ""): string
    {
        $available = max(1, $width - mb_strlen($indent));

        $lines = [];
        foreach (preg_split('/\R/', $text) as $paragraph) {
            $line = "";
            foreach (preg_split('/\s+/', trim($paragraph), -1, PREG_SPLIT_NO_EMPTY) as $word) {
                if ($line === "") {
                    $line = $word;
                } elseif (mb_strlen($line) + 1 + mb_strlen($word) <= $available) {
                    $line .= " " . $word;
                } else {
                    $lines[] = $indent . $line;
                    $line = $word;
                }
            }
            $lines[] = rtrim($indent . $line);
        }

        return implode("\n", $lines);
    }
}
