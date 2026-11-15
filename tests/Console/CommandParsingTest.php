<?php

namespace AppSupport\Tests\Console;

use AppSupport\Tests\AppTestCase;
use AppSupport\Tests\Support\ConfigurableCommand;
use AppSupport\Tests\Support\LoggedError;

/**
 * Command::parse(): options before arguments, the arguments in order, and the errors
 */
final class CommandParsingTest extends AppTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->boot(['commands' => []]);
    }

    /**
     * @param array<int, array> $arguments [name, description, required, multiple]
     * @param string[] $args
     */
    private function parse(array $arguments, array $args, array $options = [], array $flags = []): ConfigurableCommand
    {
        $command = new ConfigurableCommand($options, $flags, $arguments);
        $command->boot();
        $command->parse($args, true);

        return $command;
    }

    public function testArgumentsAreKeptInOrder(): void
    {
        $command = $this->parse([['source', 'Source'], ['target', 'Target']], ['a.txt', 'b.txt']);

        $this->assertSame(['a.txt', 'b.txt'], $command->getActualArguments());
    }

    public function testMultipleArgumentTakesTheRest(): void
    {
        $command = $this->parse([['files', 'Files', true, true]], ['a', 'b', 'c']);

        $this->assertSame(['a', 'b', 'c'], $command->getActualArguments());
    }

    public function testOptionsComeBeforeTheArguments(): void
    {
        $command = $this->parse([['files', 'Files', true, true]], ['--mode', 'fast', '--no-quiet', 'a', 'b'],
            ['mode' => ['description' => 'Mode']], ['quiet' => ['description' => 'Quiet', 'default' => true]]);

        $this->assertSame(['a', 'b'], $command->getActualArguments());
        $this->assertSame('fast', $command->config()->get('mode'));
        $this->assertFalse($command->config()->get('quiet'));
    }

    public function testOptionalArgumentMayBeMissing(): void
    {
        $command = $this->parse([['source', 'Source'], ['target', 'Target', false]], ['a.txt']);

        $this->assertSame(['a.txt'], $command->getActualArguments());
    }

    public function testRequiredArgumentMustBeGiven(): void
    {
        try {
            $this->parse([['source', 'Source'], ['target', 'Target']], ['a.txt']);
            $this->fail('A required argument that is missing has to be an error');
        } catch (LoggedError $error) {
            $this->assertStringContainsString('Required arguments are missed', $error->getMessage());
            $this->assertTrue($this->logger->has('error', "Argument 'target' is required"));
        }
    }

    public function testTooManyArguments(): void
    {
        $this->expectException(LoggedError::class);
        $this->expectExceptionMessage("Unexpected argument 'c'");

        $this->parse([['source', 'Source'], ['target', 'Target']], ['a', 'b', 'c']);
    }

    public function testOptionAfterAnArgument(): void
    {
        $this->expectException(LoggedError::class);
        $this->expectExceptionMessage("Unexpected option '--mode'");

        $this->parse([['files', 'Files', true, true]], ['a', '--mode', 'fast'], ['mode' => ['description' => 'Mode']]);
    }

    public function testUnknownOption(): void
    {
        $this->expectException(LoggedError::class);
        $this->expectExceptionMessage("Option 'mode' not found");

        ob_start();
        try {
            $this->parse([], ['--mode', 'fast']);
        } finally {
            ob_end_clean();
        }
    }

    public function testNoMoreArgumentsAfterAMultipleOne(): void
    {
        $this->expectException(LoggedError::class);
        $this->expectExceptionMessage("No more arguments can be added after a multivalued one ('files')");

        $this->parse([['files', 'Files', true, true], ['last', 'Last']], ['a']);
    }
}
