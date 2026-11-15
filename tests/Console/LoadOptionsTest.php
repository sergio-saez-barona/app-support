<?php

namespace AppSupport\Tests\Console;

use AppSupport\Console\Value;
use AppSupport\Tests\AppTestCase;
use AppSupport\Tests\Support\ConfigurableCommand;
use AppSupport\Tests\Support\LoggedError;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Command::loadOptions(): the positional format (default, description, multiple) and the associative one
 * (description, default, required, multiple, validValues, label)
 */
final class LoadOptionsTest extends AppTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->boot(['commands' => []]);
    }

    /**
     * Creates a command with the options, and parses the arguments
     *
     * @param string[] $args
     */
    private function command(array $definitions, array $args = []): ConfigurableCommand
    {
        $command = new ConfigurableCommand($definitions);
        $command->boot();
        $command->parse($args, true);

        return $command;
    }

    private function option(ConfigurableCommand $command, string $name): Value
    {
        return $command->getOptionList()->getOptions()[$name];
    }

    // ---- Positional format

    public function testPositionalFormat(): void
    {
        $definitions = [
            'policy' => 'fixed',
            'length' => ['0', 'Simulation length'],
            'o|output' => ['', 'Output file'],
            'include' => [null, 'Include path', true],
            'plain' => null,
        ];

        $config = $this->command($definitions)->config();

        $this->assertSame('fixed', $config->get('policy'));
        $this->assertSame('0', $config->get('length'), '"0" is a default like any other');
        $this->assertSame('', $config->get('output'), '"" is a default like any other');
        $this->assertFalse($config->isset('include'), 'null means that there is no default');
        $this->assertFalse($config->isset('plain'));
    }

    public function testPositionalOptionsAreParsed(): void
    {
        $command = $this->command(['policy' => 'fixed', 'length' => ['0', 'Length'], 'o|output' => ['', 'Output'], 'include' => [null, 'Include', true]],
            ['-o', 'out.txt', '--length', '5', '--include', 'a', '--include', 'b', '--policy', 'edf']);

        $config = $command->config();
        $this->assertSame('out.txt', $config->get('output'));
        $this->assertSame('5', $config->get('length'));
        $this->assertSame('edf', $config->get('policy'));
        $this->assertSame(['a', 'b'], $config->get('include'));
        $this->assertSame('o', $this->option($command, 'output')->getShortName());
        $this->assertSame('Length', $this->option($command, 'length')->getDescription());
        $this->assertTrue($this->option($command, 'include')->isMultiple());
    }

    public function testPositionalMultipleMustBeABoolean(): void
    {
        $this->expectException(LoggedError::class);
        $this->expectExceptionMessage("Multiple flag in 'include' requires a boolean value");

        $this->command(['include' => [null, 'Include', 'yes']]);
    }

    // ---- Associative format

    public function testAssociativeDefinitionWithOnlyADescription(): void
    {
        $command = $this->command(['opt' => ['description' => 'An option']]);

        $option = $this->option($command, 'opt');
        $this->assertSame('An option', $option->getDescription());
        $this->assertNull($option->getDefault());
        $this->assertFalse($option->isRequired());
        $this->assertFalse($option->isMultiple());
        $this->assertSame([], $option->getValidValues());
        $this->assertSame('value', $option->getLabel());
        $this->assertFalse($command->config()->isset('opt'));
    }

    public function testAssociativeDefinitionWithEveryField(): void
    {
        $command = $this->command(['s|policy' => [
            'description' => 'Scheduling policy',
            'default' => 'fixed',
            'validValues' => ['none', 'fixed', 'edf'],
            'label' => 'policy',
        ]]);

        $option = $this->option($command, 'policy');
        $this->assertSame('s', $option->getShortName());
        $this->assertSame('policy', $option->getLongName());
        $this->assertSame('fixed', $option->getDefault());
        $this->assertSame(['none', 'fixed', 'edf'], $option->getValidValues());
        $this->assertSame('policy', $option->getLabel());
        $this->assertSame('fixed', $command->config()->get('policy'));
    }

    public function testDefaultsThatLookEmpty(): void
    {
        $config = $this->command(['zero' => ['description' => 'd', 'default' => '0'], 'empty' => ['description' => 'd', 'default' => '']])->config();

        $this->assertSame('0', $config->get('zero'));
        $this->assertSame('', $config->get('empty'));
    }

    public function testShortAndLongNames(): void
    {
        $short = $this->command(['o|output' => ['description' => 'd']], ['-o', 'a'])->config();
        $long = $this->command(['o|output' => ['description' => 'd']], ['--output', 'b'])->config();

        $this->assertSame('a', $short->get('output'));
        $this->assertSame('b', $long->get('output'));
    }

    public function testRequiredOptionMustBeGiven(): void
    {
        $command = new ConfigurableCommand(['config' => ['description' => 'Config file', 'required' => true]]);
        $command->boot();
        $args = [];

        try {
            $command->parse($args, true);
            $this->fail('A required option that is missing has to be an error');
        } catch (LoggedError $error) {
            $this->assertStringContainsString('Required options are missed', $error->getMessage());
            $this->assertTrue($this->logger->has('error', "Option 'config' is required"));
        }
    }

    public function testRequiredOptionGiven(): void
    {
        $command = $this->command(['config' => ['description' => 'Config file', 'required' => true]], ['--config', 'a.ini']);

        $this->assertSame('a.ini', $command->config()->get('config'));
        $this->assertTrue($this->option($command, 'config')->isRequired());
        $this->assertTrue($this->option($command, 'config')->isSet());
    }

    public function testValidValues(): void
    {
        $definitions = ['policy' => ['description' => 'd', 'default' => 'fixed', 'validValues' => ['none', 'fixed', 'edf']]];

        $this->assertSame('edf', $this->command($definitions, ['--policy', 'edf'])->config()->get('policy'));

        $command = new ConfigurableCommand($definitions);
        $command->boot();
        $args = ['--policy', 'zzz'];
        try {
            $command->parse($args, true);
            $this->fail('A value that is not valid has to be an error');
        } catch (LoggedError $error) {
            $this->assertStringContainsString("Error while parsing option 'policy'", $error->getMessage());
            $this->assertTrue($this->logger->has('error', "Invalid value 'zzz'"));
            $this->assertTrue($this->logger->has('info', 'Valid values are: none, fixed, edf'));
        }
    }

    public function testEveryValueOfAMultipleOptionIsChecked(): void
    {
        $definitions = ['inc' => ['description' => 'd', 'multiple' => true, 'validValues' => ['a', 'b']]];

        $this->assertSame(['a', 'b'], $this->command($definitions, ['--inc', 'a', '--inc', 'b'])->config()->get('inc'));

        $this->expectException(LoggedError::class);
        $this->command($definitions, ['--inc', 'a', '--inc', 'z']);
    }

    public function testMultipleOptionCollectsTheValues(): void
    {
        $definitions = ['inc' => ['description' => 'd', 'multiple' => true]];

        $this->assertSame(['x', 'y', 'z'], $this->command($definitions, ['--inc', 'x', '--inc', 'y', '--inc', 'z'])->config()->get('inc'));
        $this->assertFalse($this->command($definitions)->config()->isset('inc'));
    }

    public function testDefaultOfAMultipleOptionIsAListThatTheGivenValuesReplace(): void
    {
        $definitions = ['inc' => ['description' => 'd', 'multiple' => true, 'default' => 'src']];

        $this->assertSame(['src'], $this->command($definitions)->config()->get('inc'));
        $this->assertSame(['x'], $this->command($definitions, ['--inc', 'x'])->config()->get('inc'));
        $this->assertSame(['x', 'y'], $this->command($definitions, ['--inc', 'x', '--inc', 'y'])->config()->get('inc'));
    }

    public function testAnOptionThatIsNotMultipleCannotBeRepeated(): void
    {
        $this->expectException(LoggedError::class);
        $this->expectExceptionMessage("Option 'opt' does not support multiple values");

        $this->command(['opt' => ['description' => 'd']], ['--opt', 'a', '--opt', 'b']);
    }

    public function testAListIsThePositionalFormat(): void
    {
        // Without string keys it is not an associative definition: [default, description]
        $command = $this->command(['opt' => ['x', 'The description']]);

        $this->assertSame('x', $command->config()->get('opt'));
        $this->assertSame('The description', $this->option($command, 'opt')->getDescription());
    }

    /** @return array<string, array{array, string}> */
    public static function invalidDefinitions(): array
    {
        return [
            'no description' => [['default' => 'x'], "requires a 'description' string"],
            'description is not a string' => [['description' => 5], "requires a 'description' string"],
            'unknown field' => [['description' => 'd', 'colour' => 'red'], "Unknown field 'colour' in option 'opt'"],
            'required is not a boolean' => [['description' => 'd', 'required' => 'yes'], "Field 'required' in option 'opt' requires a boolean value"],
            'multiple is not a boolean' => [['description' => 'd', 'multiple' => 1], "Field 'multiple' in option 'opt' requires a boolean value"],
            'valid values is not an array' => [['description' => 'd', 'validValues' => 'a,b'], "Field 'validValues' in option 'opt' requires an array"],
            'required with a default' => [['description' => 'd', 'required' => true, 'default' => 'x'], 'cannot be required and have a default value'],
            'default is not a valid value' => [['description' => 'd', 'validValues' => ['a', 'b'], 'default' => 'c'], 'not one of its valid values'],
        ];
    }

    #[DataProvider('invalidDefinitions')]
    public function testInvalidDefinitions(array $definition, string $message): void
    {
        $this->expectException(LoggedError::class);
        $this->expectExceptionMessage($message);

        (new ConfigurableCommand(['opt' => $definition]))->boot();
    }
}
