<?php

namespace AppSupport\Tests\Console;

use AppSupport\Console\Flag;
use AppSupport\Tests\AppTestCase;
use AppSupport\Tests\Support\ConfigurableCommand;
use AppSupport\Tests\Support\LoggedError;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Command::loadFlags(): the scalar and positional formats, and the associative one (description, default)
 */
final class LoadFlagsTest extends AppTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->boot(['commands' => []]);
    }

    /** @param string[] $args */
    private function command(array $flags, array $args = []): ConfigurableCommand
    {
        $command = new ConfigurableCommand([], $flags);
        $command->boot();
        $command->parse($args, true);

        return $command;
    }

    public function testDefaults(): void
    {
        $config = $this->command([
            'quiet' => false,
            'force' => [false, 'Overwrite'],
            'grid' => ['description' => 'Draw the grid', 'default' => true],
            'dry-run' => ['description' => 'Do not write'],
        ])->config();

        $this->assertFalse($config->get('quiet'));
        $this->assertFalse($config->get('force'));
        $this->assertTrue($config->get('grid'));
        $this->assertFalse($config->get('dry-run'), 'The default of a flag is false');
    }

    public function testFlagAndItsNegation(): void
    {
        $flags = ['grid' => ['description' => 'Grid', 'default' => true], 'dry-run' => ['description' => 'Dry run']];

        $config = $this->command($flags, ['--no-grid', '--dry-run'])->config();

        $this->assertFalse($config->get('grid'));
        $this->assertTrue($config->get('dry-run'));
        $this->assertFalse($this->command($flags, ['--no-dry-run'])->config()->get('dry-run'));
    }

    public function testShortAlias(): void
    {
        $command = $this->command(['v|verbose' => ['description' => 'Verbose output']], ['-v']);

        $this->assertTrue($command->config()->get('verbose'));
        /** @var Flag $flag */
        $flag = $command->getOptionList()->getOptions()['verbose'];
        $this->assertSame('v', $flag->getShortName());
        $this->assertSame('Verbose output', $flag->getDescription());
    }

    public function testDescriptionInThePositionalFormat(): void
    {
        $command = $this->command(['force' => [true, 'Overwrite the files']]);

        $this->assertSame('Overwrite the files', $command->getOptionList()->getOptions()['force']->getDescription());
        $this->assertTrue($command->config()->get('force'));
    }

    /** @return array<string, array{array, string}> */
    public static function invalidDefinitions(): array
    {
        return [
            'default is not a boolean' => [['description' => 'd', 'default' => 'yes'], "Flag 'flag' requires a boolean value"],
            'scalar is not a boolean' => ['yes', "Flag 'flag' requires a boolean value"],
            'no description' => [['default' => true], "Flag 'flag' requires a 'description' string"],
            'unknown field' => [['description' => 'd', 'required' => true], "Unknown field 'required' in flag 'flag'"],
        ];
    }

    #[DataProvider('invalidDefinitions')]
    public function testInvalidDefinitions(mixed $definition, string $message): void
    {
        $this->expectException(LoggedError::class);
        $this->expectExceptionMessage($message);

        (new ConfigurableCommand([], ['flag' => $definition]))->boot();
    }
}
