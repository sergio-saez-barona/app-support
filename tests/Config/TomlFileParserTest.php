<?php

namespace AppSupport\Tests\Config;

use AppSupport\Config\ConfigFileError;
use AppSupport\Config\TomlFileParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TomlFileParserTest extends TestCase
{
    public function testScalarsListsAndTables(): void
    {
        $toml = <<<'TOML'
        # A comment
        granularity = 1000
        ratio = 0.8
        name = "a text"
        grid = false
        modes = ["a", "b"]
        numbers = [1, 2, 3]
        empty = []

        [gen-gmf]
        frames = "2:5"   # another comment
        count = 20
        TOML;

        $this->assertSame([
            'granularity' => 1000,
            'ratio' => 0.8,
            'name' => 'a text',
            'grid' => false,
            'modes' => ['a', 'b'],
            'numbers' => [1, 2, 3],
            'empty' => [],
            'gen-gmf' => ['frames' => '2:5', 'count' => 20],
        ], (new TomlFileParser())->parse($toml));
    }

    public function testAnEmptyFile(): void
    {
        $this->assertSame([], (new TomlFileParser())->parse(''));
        $this->assertSame([], (new TomlFileParser())->parse("# only a comment\n"));
    }

    public function testKeysWithDashes(): void
    {
        $this->assertSame(['period-mechanism' => 'harmonic'], (new TomlFileParser())->parse('period-mechanism = "harmonic"'));
    }

    /** @return array<string, array{string, string}> */
    public static function invalidFiles(): array
    {
        return [
            'no value' => ["a = 1\nb = \nc = 3\n", 'line 2'],
            'duplicate key' => ["a = 1\na = 2\n", "Duplicate key 'a'"],
            'text without closing quote' => ["a = \"x\n", 'line 1'],
            'not a key' => ["= 1\n", 'line 1'],
        ];
    }

    #[DataProvider('invalidFiles')]
    public function testInvalidFilesSayWhereTheErrorIs(string $toml, string $expected): void
    {
        $this->expectException(ConfigFileError::class);
        $this->expectExceptionMessage($expected);

        (new TomlFileParser())->parse($toml);
    }
}
