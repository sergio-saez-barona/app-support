<?php

namespace AppSupport\Tests\Console;

use AppSupport\Tests\AppTestCase;

final class AboutCommandTest extends AppTestCase
{
    private const BRIEF = 'A tool to generate synthetic task sets, to schedule them and to analyze the processor demand of the tasks in several operating modes.';

    private function about(int $columns): array
    {
        $this->boot(['commands' => []], ['info' => [
            'name' => 'Test application',
            'version' => '1.2.3',
            'date' => '2026-01-01',
            'brief' => self::BRIEF,
            'authors' => ['Somebody <somebody@example.com>', 'Another One'],
        ]]);
        $this->columns($columns);

        $lines = explode("\n", $this->runCommand(['about']));
        $start = array_search('Copyright:', $lines, true);
        $this->assertNotFalse($start);

        return array_slice($lines, $start);
    }

    public function testShowsTheAuthorsAndTheDescription(): void
    {
        $lines = $this->about(200);

        $this->assertSame('Copyright:', $lines[0]);
        $this->assertSame('  Somebody <somebody@example.com>', $lines[1]);
        $this->assertSame('  Another One', $lines[2]);
        $this->assertSame('Description:', $lines[4]);
    }

    public function testLongDescriptionIsWrappedAndIndented(): void
    {
        $lines = $this->about(50);

        $description = array_slice($lines, 5, 4);
        $this->assertGreaterThan(1, count(array_filter($description)), 'The description needs several lines in 50 columns');
        foreach (array_filter($description) as $line) {
            $this->assertStringStartsWith('  ', $line);
            $this->assertLessThanOrEqual(50, mb_strlen($line));
        }
        $this->assertSame(self::BRIEF, implode(' ', array_map('trim', array_filter($description))));
    }

    public function testWidthIsLimitedToOneHundredColumns(): void
    {
        $lines = $this->about(300);

        // 140 characters in 100 columns: two lines, the first one as long as it can be
        $this->assertLessThanOrEqual(100, mb_strlen($lines[5]));
        $this->assertGreaterThan(90, mb_strlen($lines[5]));
        $this->assertNotSame('', $lines[6]);
    }

    public function testShortDescriptionTakesOneLine(): void
    {
        $this->boot(['commands' => []]);
        $this->columns(80);

        $output = $this->runCommand(['about']);

        $this->assertStringContainsString("Description:\n  A short description.\n", $output);
    }
}
