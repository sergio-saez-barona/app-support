<?php

namespace AppSupport\Tests\Console;

use AppSupport\Console\Commands\AboutCommand;
use AppSupport\Console\Commands\HelpCommand;
use AppSupport\Console\Commands\ListCommand;
use AppSupport\Tests\AppTestCase;
use AppSupport\Tests\Support\AlphaCommand;
use AppSupport\Tests\Support\BetaCommand;
use AppSupport\Tests\Support\GammaCommand;
use AppSupport\Tests\Support\LoggedError;
use AppSupport\Tests\Support\ZetaCommand;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * config/console.php with 'sections' instead of 'commands': the hierarchy is flattened to resolve the commands
 */
final class SectionsTest extends AppTestCase
{
    private function sections(): array
    {
        return [
            ['title' => 'First', 'description' => 'The first section', 'commands' => ['beta' => BetaCommand::class, 'alpha' => AlphaCommand::class]],
            ['title' => 'Second', 'sorted' => true, 'commands' => ['zeta' => ZetaCommand::class, 'gamma' => GammaCommand::class]],
        ];
    }

    public function testSectionsAreNormalized(): void
    {
        $this->boot(['sections' => $this->sections()]);

        $this->assertSame([
            ['title' => 'First', 'description' => 'The first section', 'sorted' => false,
                'commands' => ['beta' => BetaCommand::class, 'alpha' => AlphaCommand::class]],
            ['title' => 'Second', 'description' => '', 'sorted' => true,
                'commands' => ['zeta' => ZetaCommand::class, 'gamma' => GammaCommand::class]],
        ], $this->console->getSections());
    }

    public function testCommandsAreFlattened(): void
    {
        $this->boot(['sections' => $this->sections()]);

        $commands = $this->console->getCommands();
        $this->assertSame(AlphaCommand::class, $commands['alpha']);
        $this->assertSame(ZetaCommand::class, $commands['zeta']);
        // The built-in commands are still there
        $this->assertSame(HelpCommand::class, $commands['help']);
        $this->assertSame(ListCommand::class, $commands['list']);
        $this->assertSame(AboutCommand::class, $commands['about']);
    }

    public function testACommandOfASectionIsFoundAndRun(): void
    {
        $this->boot(['sections' => $this->sections()]);

        $this->assertInstanceOf(GammaCommand::class, $this->console->findCommand('gamma'));
        $this->assertInstanceOf(GammaCommand::class, $this->console->parseCommand(['app', 'gamma']));
    }

    public function testFlatConfigurationHasNoSections(): void
    {
        $this->boot(['commands' => ['alpha' => AlphaCommand::class]]);

        $this->assertSame([], $this->console->getSections());
        $this->assertSame(AlphaCommand::class, $this->console->getCommands()['alpha']);
    }

    public function testNoCommandsAtAll(): void
    {
        $this->boot([]);

        $this->assertSame([], $this->console->getSections());
        $this->assertSame(['help', 'list', 'about', 'dump-config'], array_keys($this->console->getCommands()));
    }

    /** @return array<string, array{array, string}> */
    public static function invalidConfigurations(): array
    {
        $commands = ['alpha' => AlphaCommand::class];

        return [
            'commands and sections' => [['commands' => $commands, 'sections' => [['title' => 'T', 'commands' => []]]],
                "'commands' and 'sections' cannot be used together"],
            'sections is not an array' => [['sections' => 'x'], "'sections' requires an array"],
            'a section is not an array' => [['sections' => ['x']], 'section #0 requires an array'],
            'unknown field' => [['sections' => [['title' => 'T', 'commands' => [], 'colour' => 'red']]], "unknown field 'colour' in section #0"],
            'no title' => [['sections' => [['commands' => $commands]]], "section #0 requires a 'title' string"],
            'title is not a string' => [['sections' => [['title' => 5, 'commands' => $commands]]], "requires a 'title' string"],
            'description is not a string' => [['sections' => [['title' => 'T', 'description' => 5, 'commands' => $commands]]],
                "'description' in section 'T' requires a string"],
            'sorted is not a boolean' => [['sections' => [['title' => 'T', 'sorted' => 'yes', 'commands' => $commands]]],
                "'sorted' in section 'T' requires a boolean"],
            'no commands' => [['sections' => [['title' => 'T']]], "section 'T' requires a 'commands' array"],
            'commands is not an array' => [['sections' => [['title' => 'T', 'commands' => 'x']]], "section 'T' requires a 'commands' array"],
            'a command in two sections' => [['sections' => [
                ['title' => 'One', 'commands' => $commands],
                ['title' => 'Two', 'commands' => $commands],
            ]], "command 'alpha' is defined in sections 'One' and 'Two'"],
        ];
    }

    #[DataProvider('invalidConfigurations')]
    public function testInvalidConfigurations(array $configuration, string $message): void
    {
        $this->expectException(LoggedError::class);
        $this->expectExceptionMessage($message);

        $this->boot($configuration);
    }
}
