<?php

namespace AppSupport\Tests\Tools;

use AppSupport\Tools\VersionBumper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class VersionBumperTest extends TestCase
{
    // ---- Versions

    public function testParse(): void
    {
        $this->assertSame([1, 4, 2], VersionBumper::parse('1.4.2'));
        $this->assertSame([0, 0, 0], VersionBumper::parse('0.0.0'));
        $this->assertSame([10, 20, 30], VersionBumper::parse('10.20.30'));
    }

    /** @return array<string, array{string}> */
    public static function invalidVersions(): array
    {
        return ['two components' => ['1.4'], 'four components' => ['1.4.2.3'], 'with v' => ['v1.4.2'], 'not a number' => ['1.4.x'],
            'pre-release' => ['1.4.2-beta'], 'empty' => [''], 'with spaces' => [' 1.4.2'], 'negative' => ['1.-4.2']];
    }

    #[DataProvider('invalidVersions')]
    public function testParseRejectsWhatIsNotMajorMinorPatch(string $version): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('is not of the form MAJOR.MINOR.PATCH');

        VersionBumper::parse($version);
    }

    /** @return array<string, array{string, string, string}> */
    public static function bumps(): array
    {
        return [
            'major' => ['1.4.2', 'major', '2.0.0'],
            'minor' => ['1.4.2', 'minor', '1.5.0'],
            'patch' => ['1.4.2', 'patch', '1.4.3'],
            'patch from zero' => ['0.0.0', 'patch', '0.0.1'],
            'two digits' => ['9.9.9', 'patch', '9.9.10'],
            'minor with two digits' => ['1.9.9', 'minor', '1.10.0'],
            'major resets the others' => ['3.7.5', 'major', '4.0.0'],
        ];
    }

    #[DataProvider('bumps')]
    public function testBump(string $version, string $level, string $expected): void
    {
        $this->assertSame($expected, VersionBumper::bump($version, $level));
    }

    public function testBumpOfAnUnknownLevel(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("unknown level 'huge'");

        VersionBumper::bump('1.0.0', 'huge');
    }

    public function testBumpOfAnInvalidVersion(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        VersionBumper::bump('1.0', 'patch');
    }

    // ---- composer.json

    public function testReadComposerVersion(): void
    {
        $this->assertSame('1.2.3', VersionBumper::readComposerVersion('{"name": "a/b", "version": "1.2.3"}'));
    }

    public function testNoVersionInComposer(): void
    {
        $this->assertNull(VersionBumper::readComposerVersion('{"name": "a/b"}'));
        $this->assertNull(VersionBumper::readComposerVersion('{"extra": {"version": "9.9.9"}}'), 'Only the one of the top level counts');
        $this->assertNull(VersionBumper::readComposerVersion('{"version": 3}'));
    }

    public function testInvalidComposerJson(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('composer.json is not valid JSON');

        VersionBumper::readComposerVersion('{"version": ');
    }

    /** @return array<string, array{string}> */
    public static function composerFiles(): array
    {
        return [
            'two spaces' => ["{\n  \"name\": \"a/b\",\n  \"version\": \"1.0.0\",\n  \"require\": {\n    \"php\": \">=8.0\"\n  }\n}\n"],
            'four spaces' => ["{\n    \"name\": \"a/b\",\n    \"version\": \"1.0.0\",\n    \"require\": {\n        \"php\": \">=8.0\"\n    }\n}\n"],
            'tabs and no final newline' => ["{\n\t\"name\": \"a/b\",\n\t\"version\": \"1.0.0\"\n}"],
            'windows line endings' => ["{\r\n  \"name\": \"a/b\",\r\n  \"version\": \"1.0.0\"\r\n}\r\n"],
            'space before the colon' => ["{\n  \"name\": \"a/b\",\n  \"version\" : \"1.0.0\"\n}\n"],
            'slashes and unicode as they are' => ["{\n  \"name\": \"a/b\",\n  \"authors\": [\"Sáez <s@x.es>\"],\n  \"version\": \"1.0.0\",\n  \"repositories\": [{\"url\": \"git@github.com:a/b.git\"}]\n}\n"],
            'everything in a line' => ['{"name":"a/b","version":"1.0.0","require":{"php":">=8.0"}}'],
        ];
    }

    #[DataProvider('composerFiles')]
    public function testWriteComposerVersionChangesOnlyTheVersion(string $json): void
    {
        $new = VersionBumper::writeComposerVersion($json, '2.5.0');

        $this->assertSame(str_replace('1.0.0', '2.5.0', $json), $new);
        $this->assertSame('2.5.0', VersionBumper::readComposerVersion($new));
    }

    public function testOnlyTheVersionOfTheTopLevelIsChanged(): void
    {
        $json = "{\n  \"name\": \"a/b\",\n  \"extra\": {\n    \"version\": \"9.9.9\"\n  },\n  \"version\": \"1.0.0\",\n"
            . "  \"config\": {\n    \"platform\": {\n      \"version\": \"8.0.0\"\n    }\n  }\n}\n";

        $new = VersionBumper::writeComposerVersion($json, '1.0.1');

        $this->assertSame(str_replace('"version": "1.0.0"', '"version": "1.0.1"', $json), $new);
    }

    public function testWriteComposerVersionWithoutVersion(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('has no "version"');

        VersionBumper::writeComposerVersion('{"name": "a/b"}', '1.0.0');
    }

    // ---- config/app.php

    private const APP = <<<'PHP'
<?php
return [
    /**
     * Application Info: 'version' => '0.0.1'
     */
    'info' => [
        // Name
        'name' => 'My application',
        // Version
        'version' => '0.6.0',
        // Date
        'date' => '2026-11-10',
        'authors' => [
            "Somebody <somebody@example.com>",
        ],
    ],
    'log_level' => 'info',
];
PHP;

    public function testUpdateAppConfigChangesOnlyTheTwoValues(): void
    {
        $result = VersionBumper::updateAppConfig(self::APP, '1.0.0', '2027-01-02');

        $this->assertSame(str_replace(["'0.6.0'", "'2026-11-10'"], ["'1.0.0'", "'2027-01-02'"], self::APP), $result['source']);
        $this->assertSame(['found' => true, 'old' => '0.6.0'], $result['version']);
        $this->assertSame(['found' => true, 'old' => '2026-11-10'], $result['date']);
    }

    public function testNothingIsChangedWithoutNewValues(): void
    {
        $result = VersionBumper::updateAppConfig(self::APP, null, null);

        $this->assertSame(self::APP, $result['source']);
        $this->assertSame('0.6.0', $result['version']['old']);
        $this->assertSame('2026-11-10', $result['date']['old']);
    }

    public function testOnlyTheVersionOrOnlyTheDate(): void
    {
        $version = VersionBumper::updateAppConfig(self::APP, '1.0.0', null)['source'];
        $date = VersionBumper::updateAppConfig(self::APP, null, '2027-01-02')['source'];

        $this->assertSame(str_replace("'0.6.0'", "'1.0.0'", self::APP), $version);
        $this->assertSame(str_replace("'2026-11-10'", "'2027-01-02'", self::APP), $date);
    }

    public function testDoubleQuotedValuesKeepTheirQuotes(): void
    {
        $source = "<?php\nreturn ['info' => [\"version\" => \"0.6.0\", 'date' => \"2026-11-10\"]];\n";

        $result = VersionBumper::updateAppConfig($source, '1.0.0', '2027-01-02');

        $this->assertSame("<?php\nreturn ['info' => [\"version\" => \"1.0.0\", 'date' => \"2027-01-02\"]];\n", $result['source']);
    }

    public function testOldStyleArrays(): void
    {
        $source = "<?php\nreturn array('info' => array('version' => '0.6.0', 'date' => '2026-11-10'));\n";

        $result = VersionBumper::updateAppConfig($source, '1.0.0', '2027-01-02');

        $this->assertSame("<?php\nreturn array('info' => array('version' => '1.0.0', 'date' => '2027-01-02'));\n", $result['source']);
    }

    public function testOnlyTheKeysOfTheInfoArrayAreChanged(): void
    {
        $source = <<<'PHP'
<?php
return [
    'driver' => ['version' => '5.7', 'date' => 'before'],
    'info' => [
        'name' => 'version',
        'authors' => ['version' => 'nested', 'date' => 'nested'],
        'version' => '0.6.0',
        'date' => '2026-11-10',
    ],
    'later' => ['version' => '8.0', 'date' => 'after'],
];
PHP;

        $result = VersionBumper::updateAppConfig($source, '1.0.0', '2027-01-02');

        $this->assertSame(str_replace(["'0.6.0'", "'2026-11-10'"], ["'1.0.0'", "'2027-01-02'"], $source), $result['source']);
    }

    public function testKeysThatAreMissing(): void
    {
        $source = "<?php\nreturn ['info' => ['name' => 'x', 'version' => '0.6.0']];\n";

        $result = VersionBumper::updateAppConfig($source, '1.0.0', '2027-01-02');

        $this->assertSame("<?php\nreturn ['info' => ['name' => 'x', 'version' => '1.0.0']];\n", $result['source']);
        $this->assertTrue($result['version']['found']);
        $this->assertFalse($result['date']['found']);
        $this->assertNull($result['date']['old']);
    }

    public function testNoInfo(): void
    {
        $source = "<?php\nreturn ['version' => '0.6.0', 'date' => '2026-11-10'];\n";

        $result = VersionBumper::updateAppConfig($source, '1.0.0', '2027-01-02');

        $this->assertSame($source, $result['source']);
        $this->assertFalse($result['version']['found']);
        $this->assertFalse($result['date']['found']);
    }

    public function testInfoThatIsNotAnArray(): void
    {
        $source = "<?php\nreturn ['info' => 'text', 'other' => ['version' => '1.0.0']];\n";

        $result = VersionBumper::updateAppConfig($source, '2.0.0', null);

        $this->assertSame($source, $result['source']);
        $this->assertFalse($result['version']['found']);
    }

    /** @return array<string, array{string}> */
    public static function valuesThatAreNotLiterals(): array
    {
        return [
            'a constant' => ["<?php\nreturn ['info' => ['version' => APP_VERSION]];\n"],
            'a concatenation' => ["<?php\nreturn ['info' => ['version' => '0.' . '6.0']];\n"],
            'a call' => ["<?php\nreturn ['info' => ['version' => trim('0.6.0')]];\n"],
            'a number' => ["<?php\nreturn ['info' => ['version' => 6]];\n"],
        ];
    }

    #[DataProvider('valuesThatAreNotLiterals')]
    public function testValuesThatAreNotALiteralAreNotChanged(string $source): void
    {
        $result = VersionBumper::updateAppConfig($source, '1.0.0', null);

        $this->assertSame($source, $result['source']);
        $this->assertFalse($result['version']['found']);
    }

    public function testWindowsLineEndingsAreKept(): void
    {
        $source = str_replace("\n", "\r\n", self::APP);

        $result = VersionBumper::updateAppConfig($source, '1.0.0', '2027-01-02');

        $this->assertSame(str_replace(["'0.6.0'", "'2026-11-10'"], ["'1.0.0'", "'2027-01-02'"], $source), $result['source']);
    }

    public function testTheResultIsValidPhp(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'app') . '.php';
        file_put_contents($file, VersionBumper::updateAppConfig(self::APP, '1.0.0', '2027-01-02')['source']);

        $config = require $file;
        unlink($file);

        $this->assertSame('1.0.0', $config['info']['version']);
        $this->assertSame('2027-01-02', $config['info']['date']);
        $this->assertSame('info', $config['log_level']);
    }
}
