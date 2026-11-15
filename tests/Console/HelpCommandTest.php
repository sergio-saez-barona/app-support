<?php

namespace AppSupport\Tests\Console;

use AppSupport\Tests\AppTestCase;
use AppSupport\Tests\Support\DemoCommand;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The help of a command: the descriptions of the options and the arguments start in the same column (36),
 * they are wrapped under their own text, and the extra lines are indented 2 columns more
 */
final class HelpCommandTest extends AppTestCase
{
    private const COLUMN = 36;

    protected function setUp(): void
    {
        parent::setUp();
        $this->boot(['commands' => ['demo' => DemoCommand::class]]);
    }

    /**
     * Lines of the help of the demo command, from the line of the usage
     *
     * @return string[]
     */
    private function help(int $columns): array
    {
        $this->columns($columns);
        $lines = explode("\n", $this->runCommand(['help', 'demo']));
        $start = array_search('Usage:', $lines, true);
        $this->assertNotFalse($start);

        return array_slice($lines, $start);
    }

    /** Index of the first line that starts with the text (after the indentation of two) */
    private function line(array $lines, string $startsWith): int
    {
        foreach ($lines as $index => $line) {
            if (str_starts_with($line, $startsWith)) {
                return $index;
            }
        }
        $this->fail("There is no line that starts with '{$startsWith}'");
    }

    // ---- Columns

    public function testDescriptionsOfOptionsAndArgumentsStartInTheSameColumn(): void
    {
        $lines = $this->help(100);

        foreach (['  -n, --count <n> [required]' => 'Number of tasks',
                     '  -U, --utilization <u> [required]' => 'Total utilization',
                     '  -p, --periods <min:max>' => 'Period range',
                     '      --mechanism <value>' => 'How periods are chosen',
                     '      --[no-]grid' => 'Draw the background grid',
                     '  -v, --[no-]verbose' => 'Verbose output',
                     '  source [required]' => 'Source file',
                     '  files' => 'More files'] as $label => $description) {
            $line = $lines[$this->line($lines, $label)];
            $this->assertSame(self::COLUMN, strpos($line, $description), "Column of '{$description}' in: {$line}");
        }
    }

    public function testALabelThatFitsExactlyStaysInTheLine(): void
    {
        // "-U, --utilization <u> [required]" has 32 characters, the width of the column of the labels
        $lines = $this->help(100);

        $this->assertStringContainsString('[required]  Total utilization', $lines[$this->line($lines, '  -U, --utilization')]);
    }

    public function testALabelThatDoesNotFitMovesTheDescriptionToTheNextLine(): void
    {
        $lines = $this->help(100);

        $index = $this->line($lines, '  -m, --mutation');
        $this->assertSame('  -m, --mutation <unchanged:modified> [required]', $lines[$index], 'The label is alone, with no trailing spaces');
        $this->assertSame(str_repeat(' ', self::COLUMN) . 'Percentages of the tasks that change', $lines[$index + 1]);
    }

    // ---- Wrapping

    public function testLongDescriptionsWrapUnderTheirOwnText(): void
    {
        $lines = $this->help(80);

        $index = $this->line($lines, '  -n, --count');
        $this->assertSame(self::COLUMN, strpos($lines[$index], 'Number of tasks'));
        $this->assertLessThanOrEqual(80, mb_strlen($lines[$index]));
        // The next line goes under the first one, in the same column
        $this->assertMatchesRegularExpression('/^ {36}\S/', $lines[$index + 1]);
        $this->assertDoesNotMatchRegularExpression('/^ {37}/', $lines[$index + 1]);
    }

    public function testNothingIsWiderThanTheTerminal(): void
    {
        // Narrower terminals do not leave 20 columns for the text of the most indented lines
        foreach ([70, 80, 120] as $width) {
            $lines = $this->help($width);
            $start = $this->line($lines, 'Arguments:');
            foreach (array_slice($lines, $start) as $line) {
                $this->assertLessThanOrEqual($width, mb_strlen($line), "Width {$width}: {$line}");
            }
        }
    }

    public function testWidthIsLimitedToOneHundredColumns(): void
    {
        $lines = $this->help(300);

        $start = $this->line($lines, 'Arguments:');
        $longest = max(array_map('mb_strlen', array_slice($lines, $start)));
        $this->assertLessThanOrEqual(100, $longest);
        $this->assertGreaterThan(90, $longest, 'The lines use the width that there is');
    }

    public function testExtraLinesAreIndentedTwoColumnsMoreAndTheirContinuationsTwoMore(): void
    {
        $lines = $this->help(80);

        $index = $this->line($lines, '  -p, --periods');
        $this->assertSame(self::COLUMN, strpos($lines[$index], 'Period range'));
        $this->assertSame(str_repeat(' ', 38) . 'log-uniform: uniform in the exponent;', $lines[$index + 1]);
        $this->assertSame(str_repeat(' ', 38) . 'harmonic: every period divides the longer', $lines[$index + 2]);
        $this->assertMatchesRegularExpression('/^ {40}ones, so the hyperperiod is the longest/', $lines[$index + 3]);
    }

    public function testDefaultAndValuesAreShown(): void
    {
        $lines = $this->help(100);

        $index = $this->line($lines, '      --mechanism');
        $this->assertSame(str_repeat(' ', 41) . "Default: 'log-uniform'", rtrim($lines[$index + 1]));
        $this->assertSame(str_repeat(' ', 41) . 'Values: log-uniform, harmonic, power', $lines[$index + 2]);
    }

    public function testFlagsShowTheirStatus(): void
    {
        $lines = $this->help(100);

        // The description of the grid has two lines at this width
        $this->assertSame(str_repeat(' ', 41) . 'Status: true', $lines[$this->line($lines, '      --[no-]grid') + 2]);
        $this->assertSame(str_repeat(' ', 41) . 'Status: false', $lines[$this->line($lines, '  -v, --[no-]verbose') + 1]);
    }

    // ---- Sections

    public function testSectionsAndTheirOrder(): void
    {
        $lines = $this->help(100);

        $arguments = $this->line($lines, 'Arguments:');
        $options = $this->line($lines, 'Options:');
        $flags = $this->line($lines, 'Flags:');
        $this->assertLessThan($options, $arguments);
        $this->assertLessThan($flags, $options);

        // The required options come first
        $this->assertLessThan($this->line($lines, '  -p, --periods'), $this->line($lines, '  -m, --mutation'));
        $this->assertLessThan($this->line($lines, '  -p, --periods'), $this->line($lines, '  -n, --count'));
        $this->assertLessThan($this->line($lines, '      --mechanism'), $this->line($lines, '  -p, --periods'));
    }

    public function testUsageIsNotWrapped(): void
    {
        $lines = $this->help(40);

        $this->assertSame('  demo [options] <source> [files ...]', $lines[1]);
    }

    public function testDescriptionOfTheCommandAndHelpTextAreWrapped(): void
    {
        $this->columns(60);

        $output = $this->runCommand(['help', 'demo']);

        // 60 columns, and the help is indented 2 columns
        $this->assertStringContainsString("Demonstrates the help of the commands, with a description\nthat is long enough to need more than one line in a narrow\nterminal\n\nUsage:", $output);
        $this->assertStringContainsString("Help:\n  Some help text that is long enough to be wrapped in a\n  narrow terminal, to check that the help is wrapped too.\n  Second paragraph.\n", $output);
    }

    #[DataProvider('commandsWithoutOptions')]
    public function testCommandsWithoutOptionsHaveNoOptionSections(string $command): void
    {
        $this->columns(80);

        $output = $this->runCommand(['help', $command]);

        $this->assertStringNotContainsString('Options:', $output);
        $this->assertStringNotContainsString('Flags:', $output);
    }

    /** @return array<string, array{string}> */
    public static function commandsWithoutOptions(): array
    {
        return ['list' => ['list'], 'about' => ['about']];
    }
}
