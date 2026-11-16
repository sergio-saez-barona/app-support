<?php

namespace AppSupport\Tests\Console;

use AppSupport\Tests\AppTestCase;
use AppSupport\Tests\Support\AlphaCommand;
use AppSupport\Tests\Support\BetaCommand;
use AppSupport\Tests\Support\GammaCommand;
use AppSupport\Tests\Support\ZetaCommand;

/**
 * The list command: commands grouped in sections, and the descriptions wrapped to the width of the terminal
 */
final class ListCommandTest extends AppTestCase
{
    /** @return string[] lines of the list, from the first title */
    private function lines(string $output, string $first): array
    {
        $lines = explode("\n", $output);
        $start = array_search(true, array_map(fn($line) => str_starts_with($line, $first), $lines), true);
        $this->assertNotFalse($start, "'{$first}' is not in the output");

        return array_slice($lines, $start);
    }

    private function sections(): array
    {
        return [
            ['title' => 'First', 'description' => 'The first section', 'commands' => ['beta' => BetaCommand::class, 'alpha' => AlphaCommand::class]],
            ['title' => 'Second', 'sorted' => true, 'commands' => ['zeta' => ZetaCommand::class, 'gamma' => GammaCommand::class]],
        ];
    }

    public function testSectionsAreListedInOrderWithTheirDescription(): void
    {
        $this->boot(['sections' => $this->sections()]);
        $this->columns(120);

        $lines = $this->lines($this->runCommand(['list']), 'First:');

        $this->assertSame('First:', $lines[0]);
        $this->assertSame('  The first section', $lines[1]);
        $this->assertSame('', $lines[2], 'A blank line after the description of the section');
        // The name takes 20 columns, and a space follows
        $this->assertMatchesRegularExpression('/^  beta {17}Does the second thing/', $lines[3], 'The commands of a section are in the order of the configuration');
        // The description of beta does not fit in 100 columns: its second line comes before alpha
        $this->assertSame(str_repeat(' ', 23) . 'lines in a narrow terminal', $lines[4]);
        $this->assertMatchesRegularExpression('/^  alpha {16}Does the first thing$/', $lines[5]);
        $this->assertSame('', $lines[6]);
        $this->assertSame('Second:', $lines[7]);
    }

    public function testSortedSectionsListTheirCommandsAlphabetically(): void
    {
        $this->boot(['sections' => $this->sections()]);
        $this->columns(120);

        $lines = $this->lines($this->runCommand(['list']), 'Second:');

        $this->assertMatchesRegularExpression('/^  gamma /', $lines[1]);
        $this->assertMatchesRegularExpression('/^  zeta /', $lines[2]);
    }

    public function testASectionWithoutDescriptionHasNoBlankLine(): void
    {
        $this->boot(['sections' => $this->sections()]);
        $this->columns(120);

        $lines = $this->lines($this->runCommand(['list']), 'Second:');

        $this->assertMatchesRegularExpression('/^  gamma /', $lines[1]);
    }

    public function testCommandsOutsideTheSectionsAreListedAtTheEnd(): void
    {
        $this->boot(['sections' => $this->sections()]);
        $this->columns(120);

        $lines = $this->lines($this->runCommand(['list']), 'Other commands:');

        $this->assertSame('Other commands:', $lines[0]);
        $this->assertMatchesRegularExpression('/^  about /', $lines[1]);
        $this->assertMatchesRegularExpression('/^  dump-config /', $lines[2]);
        $this->assertMatchesRegularExpression('/^  help /', $lines[3]);
        $this->assertMatchesRegularExpression('/^  list /', $lines[4]);
    }

    public function testNoOtherCommandsWhenEverythingIsInASection(): void
    {
        $this->boot(['sections' => [['title' => 'All', 'commands' => [
            'alpha' => AlphaCommand::class,
            'help' => \AppSupport\Console\Commands\HelpCommand::class,
            'list' => \AppSupport\Console\Commands\ListCommand::class,
            'about' => \AppSupport\Console\Commands\AboutCommand::class,
            'dump-config' => \AppSupport\Console\Commands\DumpConfigCommand::class,
        ]]]]);
        $this->columns(120);

        $output = $this->runCommand(['list']);

        $this->assertStringContainsString('All:', $output);
        $this->assertStringNotContainsString('Other commands:', $output);
    }

    public function testFlatListHasAllTheCommands(): void
    {
        $this->boot(['commands' => ['alpha' => AlphaCommand::class, 'zeta' => ZetaCommand::class]]);
        $this->columns(120);

        $output = $this->runCommand(['list']);

        $this->assertStringContainsString('Available commands:', $output);
        foreach (['alpha', 'zeta', 'help', 'list', 'about', 'dump-config'] as $name) {
            $this->assertMatchesRegularExpression("/^  {$name} /m", $output);
        }
    }

    public function testLongDescriptionsWrapUnderThemselves(): void
    {
        $this->boot(['sections' => $this->sections()]);
        $this->columns(60);

        $lines = $this->lines($this->runCommand(['list']), 'First:');

        // beta: the description starts in the column 23, so it has 37 columns
        $this->assertSame('  beta                 Does the second thing, with a', $lines[3]);
        $this->assertSame(str_repeat(' ', 23) . 'description that is long enough to', $lines[4]);
        $this->assertSame(str_repeat(' ', 23) . 'need several lines in a narrow', $lines[5]);
        $this->assertSame(str_repeat(' ', 23) . 'terminal', $lines[6]);
        foreach (array_slice($lines, 0, 8) as $line) {
            $this->assertLessThanOrEqual(60, mb_strlen($line));
        }
    }

    public function testDescriptionsThatFitAreNotWrapped(): void
    {
        $this->boot(['sections' => $this->sections()]);
        $this->columns(200);

        $lines = $this->lines($this->runCommand(['list']), 'First:');

        $this->assertSame('  alpha                Does the first thing', $lines[5]);
    }

    public function testWidthIsLimitedToOneHundredColumns(): void
    {
        $this->boot(['sections' => $this->sections()]);
        $this->columns(300);

        $lines = $this->lines($this->runCommand(['list']), 'First:');

        $this->assertLessThanOrEqual(100, mb_strlen($lines[3]));
        $this->assertGreaterThan(80, mb_strlen($lines[3]));
        $this->assertMatchesRegularExpression('/^ {23}\S/', $lines[4], 'It wraps at 100 columns, not at 300');
    }
}
