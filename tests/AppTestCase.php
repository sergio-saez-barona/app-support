<?php

namespace AppSupport\Tests;

use AppSupport\Console\Console;
use AppSupport\Support\Facades\Facade;
use AppSupport\Support\Facades\Log;
use AppSupport\Tests\Support\TestLogger;
use PHPUnit\Framework\TestCase;

/**
 * Base of the tests that need an application: a console with the configuration that the test gives, in a
 * temporary directory, and a logger that throws an exception on fatal errors (see TestLogger).
 */
abstract class AppTestCase extends TestCase
{
    protected string $base;
    protected Console $console;
    protected TestLogger $logger;

    /** @var array<string, string|false> environment variables to restore */
    private array $environment = [];

    protected function setUp(): void
    {
        $this->base = sys_get_temp_dir() . '/app-support-tests-' . bin2hex(random_bytes(6));
        mkdir($this->base . '/config', 0777, true);
    }

    protected function tearDown(): void
    {
        foreach ($this->environment as $name => $value) {
            $value === false ? putenv($name) : putenv("{$name}={$value}");
        }
        foreach (glob($this->base . '/config/*') as $file) {
            unlink($file);
        }
        rmdir($this->base . '/config');
        rmdir($this->base);
    }

    /**
     * Creates the application with the given configuration of the console (config/console.php)
     *
     * @param array $console what config/console.php returns: 'commands' or 'sections'
     * @param array|null $app what config/app.php returns (the information of the application by default)
     */
    protected function boot(array $console, ?array $app = null): Console
    {
        $app ??= ['info' => [
            'name' => 'Test application',
            'version' => '1.2.3',
            'date' => '2026-01-01',
            'brief' => 'A short description.',
            'authors' => ['Somebody <somebody@example.com>'],
        ]];
        file_put_contents($this->base . '/config/console.php', '<?php return ' . var_export($console, true) . ';');
        file_put_contents($this->base . '/config/app.php', '<?php return ' . var_export($app, true) . ';');

        // The logger goes first: the console can log errors while it is created
        Facade::clearResolvedInstances();
        $this->logger = new TestLogger();
        Log::swap($this->logger);

        return $this->console = new Console($this->base);
    }

    /**
     * Runs a command of the console and returns what it prints, without the color sequences
     *
     * @param string[] $args name of the command and its arguments
     */
    protected function runCommand(array $args): string
    {
        ob_start();
        try {
            $this->console->parseCommand(['app', ...$args])->execute();
        } finally {
            $output = ob_get_clean();
        }

        return $this->withoutColors($output);
    }

    protected function withoutColors(string $text): string
    {
        return preg_replace('/\e\[[0-9;]*m/', '', $text);
    }

    /** Sets the width of the terminal (the COLUMNS variable) until the end of the test */
    protected function columns(int $width): void
    {
        $this->environment['COLUMNS'] ??= getenv('COLUMNS');
        putenv("COLUMNS={$width}");
    }
}
