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
}