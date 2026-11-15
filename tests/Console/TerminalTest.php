<?php

namespace AppSupport\Tests\Console;

use AppSupport\Console\Terminal;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TerminalTest extends TestCase
{
    private string|false $columns;

    protected function setUp(): void
    {
        $this->columns = getenv('COLUMNS');
    }

    protected function tearDown(): void
    {
        $this->columns === false ? putenv('COLUMNS') : putenv("COLUMNS={$this->columns}");
    }

    // ---- wrap

    /** @return array<string, array{string, int, string, string}> text, width, indent and the wrapped text */
    public static function wraps(): array
    {
        return [
            'fits in a line' => ['aaa bbb', 10, '', 'aaa bbb'],
            'wraps at the width' => ['aaa bbb ccc', 7, '', "aaa bbb\nccc"],
            'the line can have exactly the width' => ['aaa bbb', 7, '', 'aaa bbb'],
            'indentation counts in the width' => ['aaa bbb ccc', 9, '> ', "> aaa bbb\n> ccc"],
            'multibyte characters count once' => ['ñandú ñandú ñandú', 11, '', "ñandú ñandú\nñandú"],
            'a long word is not split' => ['abcdefghij kl', 5, '', "abcdefghij\nkl"],
            'line breaks are kept' => ["aaa\nbbb ccc", 20, '', "aaa\nbbb ccc"],
            'blank lines are kept without indentation' => ["a\n\nb", 10, '  ', "  a\n\n  b"],
            'spaces are collapsed' => ["aaa    bbb\t ccc", 20, '', 'aaa bbb ccc'],
            'empty text' => ['', 10, '', ''],
        ];
    }

    #[DataProvider('wraps')]
    public function testWrap(string $text, int $width, string $indent, string $expected): void
    {
        $this->assertSame($expected, Terminal::wrap($text, $width, $indent));
    }

    // ---- wrapHanging

    // The text of every line has at least 20 columns, so the examples leave room for them

    public function testFirstLineStartsInItsColumnAndItsContinuationsGoThere(): void
    {
        // The first line starts in the column 20: it has 20 columns, and so do its continuations
        $this->assertSame("aaaa bbbb cccc dddd\n" . str_repeat(' ', 20) . 'eeee',
            Terminal::wrapHanging('aaaa bbbb cccc dddd eeee', 40, 20, 22));
    }

    public function testOtherLinesAreIndentedAndTheirContinuationsHang(): void
    {
        // The second line starts in the column 6 (30 columns) and its continuation in the column 8
        $this->assertSame(
            "first\n" . str_repeat(' ', 6) . "sub line one two three four\n" . str_repeat(' ', 8) . 'five six',
            Terminal::wrapHanging("first\nsub line one two three four five six", 36, 4, 6, 2),
        );
    }

    public function testLeadingSpacesOfALineAreKept(): void
    {
        $this->assertSame("x\n" . str_repeat(' ', 7) . "Default: 'a'", Terminal::wrapHanging("x\n   Default: 'a'", 40, 0, 4, 2));
    }

    public function testBlankLinesAreKept(): void
    {
        $this->assertSame("a\n\n  b", Terminal::wrapHanging("a\n\nb", 40, 0, 2, 0));
    }

    public function testAHangOfZero(): void
    {
        $this->assertSame("a\n" . str_repeat(' ', 4) . "bbbb cccc dddd eeee\n" . str_repeat(' ', 4) . 'ffff',
            Terminal::wrapHanging("a\nbbbb cccc dddd eeee ffff", 24, 0, 4, 0));
    }

    public function testLongWordsAreNotSplit(): void
    {
        $this->assertSame("abcdefghijklmnopqrstuvwxyz\nab", Terminal::wrapHanging('abcdefghijklmnopqrstuvwxyz ab', 20, 0, 0));
    }

    public function testTheTextHasAMinimumWidthOfTwentyColumns(): void
    {
        // Even if there is no room (width 10, starting in the column 4), a line has 20 columns of text
        $lines = explode("\n", Terminal::wrapHanging('aaaa bbbb cccc dddd eeee ffff gggg hhhh', 10, 4, 4));

        $this->assertSame('aaaa bbbb cccc dddd', $lines[0]);
        $this->assertSame(str_repeat(' ', 4) . 'eeee ffff gggg hhhh', $lines[1]);
    }

    public function testHasNoTrailingNewline(): void
    {
        $this->assertStringEndsNotWith("\n", Terminal::wrapHanging('aaaa bbbb cccc dddd eeee', 20, 0, 0));
    }

    public function testMultibyteCharactersCountOnce(): void
    {
        // 8 characters each (16 bytes): 17 characters fit in 20 columns, 26 do not
        $this->assertSame("ñandúñan ñandúñan\nñandúñan", Terminal::wrapHanging('ñandúñan ñandúñan ñandúñan', 20, 0, 0));
    }

    // ---- Width of the terminal

    public function testWidthComesFromTheColumnsVariable(): void
    {
        putenv('COLUMNS=120');

        $this->assertSame(120, Terminal::getWidth());
        $this->assertSame(120, Terminal::getWidth(55), 'The default is only used if the width is unknown');
    }

    public function testTextWidthIsLimited(): void
    {
        putenv('COLUMNS=300');
        $this->assertSame(100, Terminal::getTextWidth());
        $this->assertSame(150, Terminal::getTextWidth(150));

        putenv('COLUMNS=70');
        $this->assertSame(70, Terminal::getTextWidth());
        $this->assertSame(70, Terminal::getTextWidth(150));
    }

    /** @return array<string, array{string}> */
    public static function invalidColumns(): array
    {
        return ['text' => ['abc'], 'zero' => ['0'], 'negative' => ['-5'], 'empty' => ['']];
    }

    #[DataProvider('invalidColumns')]
    public function testTheDefaultWidthIsUsedWhenItIsNotKnown(string $columns): void
    {
        // The output of a process that is not a terminal and has no usable COLUMNS
        $this->assertSame('55', $this->width(['COLUMNS' => $columns]));
    }

    public function testTheDefaultWidthIsEightyColumns(): void
    {
        $this->assertSame('80', $this->width([], false));
    }

    /**
     * Width that Terminal::getWidth() gives in a process whose output is a pipe (not a terminal)
     *
     * @param array<string, string> $environment variables to add (COLUMNS is removed before)
     */
    private function width(array $environment, bool $withDefault = true): string
    {
        $env = getenv();
        unset($env['COLUMNS']);
        $code = 'require ' . var_export(APP_SUPPORT_TESTS_AUTOLOAD, true) . '; echo \AppSupport\Console\Terminal::getWidth(' . ($withDefault ? '55' : '') . ');';
        $process = proc_open([PHP_BINARY, '-r', $code], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, [...$env, ...$environment]);
        $output = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);

        return $output;
    }

    // ---- Colors

    public function testColorSequences(): void
    {
        $this->assertSame("\e[32;49m", Terminal::getColor(Terminal::Green));
        $this->assertSame("\e[0m", Terminal::getReset());
        $this->assertSame("\e[31;42m", Terminal::getColor(Terminal::Red, Terminal::Green));
    }

    public function testPrintlnColorsTheTextAndResets(): void
    {
        ob_start();
        Terminal::println('hello', Terminal::Yellow);
        $output = ob_get_clean();

        $this->assertSame(Terminal::getColor(Terminal::Yellow) . "hello\e[0m\n", $output);
    }
}
