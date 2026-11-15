<?php

namespace AppSupport\Tests\Tools;

use PHPUnit\Framework\TestCase;

/**
 * The executable that Composer installs in vendor/bin of the projects that use the package
 */
final class PhpBumpVersionBinaryTest extends TestCase
{
    private const BINARY = __DIR__ . '/../../bin/php-bump-version';

    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/bump-version-bin-' . bin2hex(random_bytes(6));
        mkdir($this->dir);
        file_put_contents($this->dir . '/composer.json', "{\n  \"name\": \"acme/tool\",\n  \"version\": \"1.4.2\"\n}\n");
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->dir . '/*'));
        rmdir($this->dir);
    }

    /** @return array{int, string, string} */
    private function cli(array $args): array
    {
        $process = proc_open([PHP_BINARY, self::BINARY, ...$args], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $this->dir);
        $out = stream_get_contents($pipes[1]);
        $err = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return [proc_close($process), $out, $err];
    }

    public function testIsExecutableWithAShebang(): void
    {
        $this->assertTrue(is_executable(self::BINARY));
        $this->assertSame("#!/usr/bin/env php\n", fgets(fopen(self::BINARY, 'r')));
    }

    public function testComposerInstallsItInVendorBin(): void
    {
        $composer = json_decode(file_get_contents(__DIR__ . '/../../composer.json'), true);

        $this->assertSame(['bin/php-bump-version'], $composer['bin']);
    }

    public function testPrintsTheVersion(): void
    {
        $this->assertSame([0, "1.4.2\n", ''], $this->cli([]));
    }

    public function testBumpsTheVersionOfTheCurrentDirectory(): void
    {
        [$code, $out, $err] = $this->cli(['--minor']);

        $this->assertSame([0, "Bumped version: 1.4.2 -> 1.5.0   (composer.json)\n", ''], [$code, $out, $err]);
        $this->assertStringContainsString('"version": "1.5.0"', file_get_contents($this->dir . '/composer.json'));
    }

    public function testTheExitCodeIsTheOneOfTheErrors(): void
    {
        [$code, $out, $err] = $this->cli(['--huge']);

        $this->assertSame(1, $code);
        $this->assertSame('', $out);
        $this->assertStringStartsWith('error: unknown argument: --huge', $err);
    }
}
