<?php

namespace AppSupport\Tests\Tools;

use AppSupport\Tools\BumpVersionCli;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class BumpVersionCliTest extends TestCase
{
    private const COMPOSER = "{\n  \"name\": \"acme/tool\",\n  \"version\": \"1.4.2\",\n  \"require\": {\n    \"php\": \">=8.0\"\n  }\n}\n";

    private const APP = "<?php\nreturn [\n    'info' => [\n        // Version\n        'version' => '1.4.2',\n        // Date\n        'date' => '2026-11-10',\n        'name' => 'Tool',\n    ],\n];\n";

    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/bump-version-tests-' . bin2hex(random_bytes(6));
        mkdir($this->dir . '/config', 0777, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/{,config/}*', GLOB_BRACE) as $file) {
            is_file($file) && unlink($file);
        }
        @rmdir($this->dir . '/config');
        @rmdir($this->dir);
    }

    private function project(?string $composer = self::COMPOSER, ?string $app = self::APP): void
    {
        $composer === null || file_put_contents($this->dir . '/composer.json', $composer);
        $app === null || file_put_contents($this->dir . '/config/app.php', $app);
    }

    /**
     * Runs the program in the project
     *
     * @param string[] $args
     * @return array{int, string, string} exit code, what it printed and its errors
     */
    private function cli(array $args): array
    {
        $out = fopen('php://memory', 'w+');
        $err = fopen('php://memory', 'w+');
        $code = (new BumpVersionCli($out, $err))->run($args, $this->dir);
        rewind($out);
        rewind($err);

        return [$code, stream_get_contents($out), stream_get_contents($err)];
    }

    private function composer(): string
    {
        return file_get_contents($this->dir . '/composer.json');
    }

    private function app(): string
    {
        return file_get_contents($this->dir . '/config/app.php');
    }

    // ---- Printing the version

    public function testWithoutOptionsItPrintsTheVersion(): void
    {
        $this->project();

        $this->assertSame([0, "1.4.2\n", ''], $this->cli([]));
        $this->assertSame(self::COMPOSER, $this->composer());
        $this->assertSame(self::APP, $this->app());
    }

    public function testThePrintedVersionIsNotChecked(): void
    {
        $this->project(str_replace('1.4.2', '1.4', self::COMPOSER));

        $this->assertSame([0, "1.4\n", ''], $this->cli([]));
    }

    // ---- Bumping

    /** @return array<string, array{string, string}> */
    public static function levels(): array
    {
        return ['major' => ['--major', '2.0.0'], 'minor' => ['--minor', '1.5.0'], 'patch' => ['--patch', '1.4.3']];
    }

    #[DataProvider('levels')]
    public function testBumpChangesComposerAndTheConfiguration(string $option, string $new): void
    {
        $this->project();

        [$code, $out, $err] = $this->cli([$option, '--date', '2027-03-04']);

        $this->assertSame(0, $code);
        $this->assertSame('', $err);
        $this->assertSame("Bumped version: 1.4.2 -> {$new}   (composer.json)\n"
            . "Updated config/app.php: version 1.4.2 -> {$new}, date 2026-11-10 -> 2027-03-04\n", $out);
        $this->assertSame(str_replace('1.4.2', $new, self::COMPOSER), $this->composer());
        $this->assertSame(str_replace(["'1.4.2'", "'2026-11-10'"], ["'{$new}'", "'2027-03-04'"], self::APP), $this->app());
    }

    public function testTheDateIsTodayByDefault(): void
    {
        $this->project();

        $before = date('Y-m-d');
        $this->cli(['--patch']);
        $after = date('Y-m-d');

        preg_match("/'date' => '([^']*)'/", $this->app(), $matches);
        $this->assertContains($matches[1], [$before, $after]);
    }

    public function testTheDateCanBeGivenWithAnEqualsSign(): void
    {
        $this->project();

        $this->cli(['--patch', '--date=2030-12-31']);

        $this->assertStringContainsString("'date' => '2030-12-31'", $this->app());
    }

    public function testBumpWithoutConfiguration(): void
    {
        $this->project(self::COMPOSER, null);

        [$code, $out] = $this->cli(['--minor']);

        $this->assertSame(0, $code);
        $this->assertSame("Bumped version: 1.4.2 -> 1.5.0   (composer.json)\n", $out);
        $this->assertStringContainsString('"version": "1.5.0"', $this->composer());
        $this->assertFileDoesNotExist($this->dir . '/config/app.php');
    }

    public function testTheConfigurationWithoutVersionIsNotUpdated(): void
    {
        $app = "<?php\nreturn ['info' => ['name' => 'Tool']];\n";
        $this->project(self::COMPOSER, $app);

        [$code, $out] = $this->cli(['--patch']);

        $this->assertSame(0, $code);
        $this->assertStringContainsString("Note: config/app.php has no 'version' in 'info': not updated", $out);
        $this->assertStringContainsString('"version": "1.4.3"', $this->composer());
        $this->assertSame($app, $this->app());
    }

    public function testTheConfigurationWithoutDate(): void
    {
        $this->project(self::COMPOSER, "<?php\nreturn ['info' => ['version' => '1.4.2']];\n");

        [$code, $out] = $this->cli(['--patch']);

        $this->assertSame(0, $code);
        $this->assertStringContainsString("Note: config/app.php has no 'date' in 'info'", $out);
        $this->assertStringContainsString("Updated config/app.php: version 1.4.2 -> 1.4.3\n", $out);
        $this->assertSame("<?php\nreturn ['info' => ['version' => '1.4.3']];\n", $this->app());
    }

    public function testPermissionsOfTheFilesAreKept(): void
    {
        $this->project();
        chmod($this->dir . '/composer.json', 0640);
        chmod($this->dir . '/config/app.php', 0600);

        $this->cli(['--patch']);

        $this->assertSame(0640, fileperms($this->dir . '/composer.json') & 0777);
        $this->assertSame(0600, fileperms($this->dir . '/config/app.php') & 0777);
        $this->assertSame([], glob($this->dir . '/{,config/}*.tmp*', GLOB_BRACE), 'No temporary files are left');
    }

    // ---- Dry run

    public function testDryRunWritesNothing(): void
    {
        $this->project();

        [$code, $out] = $this->cli(['--minor', '--dry-run', '--date', '2027-03-04']);

        $this->assertSame(0, $code);
        $this->assertSame("(dry run) Bumped version: 1.4.2 -> 1.5.0   (composer.json)\n"
            . "(dry run) Updated config/app.php: version 1.4.2 -> 1.5.0, date 2026-11-10 -> 2027-03-04\n", $out);
        $this->assertSame(self::COMPOSER, $this->composer());
        $this->assertSame(self::APP, $this->app());
    }

    // ---- Sync

    public function testSyncDoesNothingIfTheVersionIsTheSame(): void
    {
        $this->project();

        [$code, $out] = $this->cli(['--sync']);

        $this->assertSame([0, "config/app.php already has the version 1.4.2\n"], [$code, $out]);
        $this->assertSame(self::COMPOSER, $this->composer());
        $this->assertSame(self::APP, $this->app());
    }

    public function testSyncCopiesTheVersionAndTheDate(): void
    {
        $this->project(str_replace('1.4.2', '2.0.0', self::COMPOSER));

        [$code, $out] = $this->cli(['--sync', '--date', '2027-03-04']);

        $this->assertSame(0, $code);
        $this->assertSame("Updated config/app.php: version 1.4.2 -> 2.0.0, date 2026-11-10 -> 2027-03-04\n", $out);
        $this->assertSame(str_replace(["'1.4.2'", "'2026-11-10'"], ["'2.0.0'", "'2027-03-04'"], self::APP), $this->app());
        $this->assertSame(str_replace('1.4.2', '2.0.0', self::COMPOSER), $this->composer(), 'composer.json is not changed');
    }

    public function testSyncWithADateAndTheSameVersionOnlyChangesTheDate(): void
    {
        $this->project();

        $this->cli(['--sync', '--date', '2027-03-04']);

        $this->assertSame(str_replace('2026-11-10', '2027-03-04', self::APP), $this->app());
    }

    // ---- Errors

    /** @return array<string, array{string[], string}> */
    public static function errors(): array
    {
        return [
            'two levels' => [['--major', '--minor'], 'only one of --major, --minor, --patch allowed'],
            'unknown argument' => [['--huge'], 'unknown argument: --huge (see --help)'],
            'date without a value' => [['--patch', '--date'], '--date needs a value'],
            'invalid date' => [['--patch', '--date', '2027-13-40'], "invalid date '2027-13-40'. Expected YYYY-MM-DD"],
            'date that is not a date' => [['--patch', '--date', 'tomorrow'], "invalid date 'tomorrow'. Expected YYYY-MM-DD"],
            'date without bump' => [['--date', '2027-01-01'], '--date needs --major, --minor, --patch or --sync'],
        ];
    }

    #[DataProvider('errors')]
    public function testErrors(array $args, string $message): void
    {
        $this->project();

        [$code, $out, $err] = $this->cli($args);

        $this->assertSame(1, $code);
        $this->assertSame('', $out);
        $this->assertSame("error: {$message}\n", $err);
        $this->assertSame(self::COMPOSER, $this->composer(), 'Nothing is written on an error');
        $this->assertSame(self::APP, $this->app());
    }

    public function testNoComposerJson(): void
    {
        $this->project(null, null);

        [$code, , $err] = $this->cli(['--patch']);

        $this->assertSame(1, $code);
        $this->assertStringContainsString('no composer.json here', $err);
    }

    public function testComposerWithoutVersion(): void
    {
        $this->project("{\n  \"name\": \"acme/tool\"\n}\n");

        [$code, , $err] = $this->cli([]);

        $this->assertSame(1, $code);
        $this->assertStringContainsString('could not read the "version" of composer.json', $err);
    }

    public function testComposerThatIsNotJson(): void
    {
        $this->project('{"name": ');

        [$code, , $err] = $this->cli([]);

        $this->assertSame(1, $code);
        $this->assertStringContainsString('composer.json is not valid JSON', $err);
    }

    public function testVersionThatIsNotMajorMinorPatchCannotBeBumped(): void
    {
        $bad = str_replace('1.4.2', '1.4', self::COMPOSER);
        $this->project($bad);

        [$code, , $err] = $this->cli(['--patch']);

        $this->assertSame(1, $code);
        $this->assertStringContainsString("version '1.4' is not of the form MAJOR.MINOR.PATCH", $err);
        $this->assertSame($bad, $this->composer());
        $this->assertSame(self::APP, $this->app());
    }

    // ---- Help

    public function testHelp(): void
    {
        $this->project();

        foreach (['-h', '--help'] as $option) {
            [$code, $out] = $this->cli([$option]);

            $this->assertSame(0, $code);
            $this->assertStringStartsWith('php-bump-version - bump the semantic version of a PHP project.', $out);
            $this->assertStringContainsString('--major', $out);
            $this->assertStringContainsString('--dry-run', $out);
        }
    }

    public function testTheProjectDirectoryMayEndWithASlash(): void
    {
        $this->project();
        $out = fopen('php://memory', 'w+');

        $code = (new BumpVersionCli($out, fopen('php://memory', 'w+')))->run(['--patch'], $this->dir . '/');

        $this->assertSame(0, $code);
        $this->assertStringContainsString('"version": "1.4.3"', $this->composer());
    }
}
