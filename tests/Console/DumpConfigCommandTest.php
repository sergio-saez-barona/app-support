<?php
namespace AppSupport\Tests\Console;

use AppSupport\Config\TomlFileParser;
use AppSupport\Tests\AppTestCase;
use AppSupport\Tests\Support\LoggedError;
use AppSupport\Tests\Support\TemplateCommand;

/**
 * The dump-config command: a template of a configuration file with the options of a command, commented
 */
final class DumpConfigCommandTest extends AppTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->boot(['commands' => ['template' => TemplateCommand::class]]);
    }

    private function template(): string
    {
        return $this->runCommand(['dump-config', 'template']);
    }

    public function testWritesToTheStandardOutputWithoutFile(): void
    {
        $output = $this->template();
        $this->assertStringStartsWith("# Values for the options of the command 'template'", $output);
        $this->assertStringContainsString("\n[template]\n", $output);
        $this->assertStringEndsWith("\n", $output);
    }

    public function testEveryOptionHasItsDescriptionAndIsCommented(): void
    {
        $output = $this->template();
        $this->assertStringContainsString("# Number of tasks\n# Required\n# count = \"n\"\n", $output);
        $this->assertStringContainsString("# Period range\n# in granularity units\n# periods = \"min:max\"\n", $output);
        $this->assertStringContainsString("# Policy\n# Values: none, fixed, edf\n# policy = \"fixed\"\n", $output);
        // The only lines that are not comments are the table
        foreach (explode("\n", trim($output)) as $line) {
            $this->assertTrue($line === '' || $line[0] === '#' || $line === '[template]', "Not commented: {$line}");
        }
    }

    public function testDefaultsAreWrittenAsTomlValues(): void
    {
        $output = $this->template();
        $this->assertStringContainsString("# granularity = 1000\n", $output, 'A number');
        $this->assertStringContainsString("# zero = \"007\"\n", $output, 'Leading zeros are not a TOML number');
        $this->assertStringContainsString("# name = \"say \\\"hi\\\"\\\\\\n\"\n", $output, 'Escaped');
        $this->assertStringContainsString("# include = [\"src\"]\n", $output, 'A list');
        $this->assertStringContainsString("# tag = [\"value\"]\n", $output, 'A list with the label');
        $this->assertStringContainsString("# grid = true\n", $output);
        $this->assertStringContainsString("# verbose = false\n", $output);
    }

    public function testTheOptionOfTheConfigurationFileAndTheArgumentsAreNotInTheTemplate(): void
    {
        $output = $this->template();
        $this->assertStringNotContainsString('config', str_replace("configuration file option", '', $output));
        $this->assertStringNotContainsString('source', $output);
    }

    public function testTheTemplateIsValidTomlAlsoWhenEverythingIsUncommented(): void
    {
        $parser = new TomlFileParser();
        $output = $this->template();
        $this->assertSame(['template' => []], $parser->parse($output));

        $uncommented = preg_replace('/^# (?=[a-z-]+ = )/m', '', $output);
        $values = $parser->parse($uncommented)['template'];
        $this->assertSame(['count', 'periods', 'policy', 'granularity', 'zero', 'name', 'include', 'tag', 'grid', 'verbose'],
            array_keys($values));
        $this->assertSame("say \"hi\"\\\n", $values['name']);
        $this->assertSame(['src'], $values['include']);
        $this->assertTrue($values['grid']);
    }

    public function testWritesTheFile(): void
    {
        $file = $this->base . '/template.toml';
        $output = $this->runCommand(['dump-config', 'template', $file]);
        $this->assertSame($this->template(), file_get_contents($file));
        $this->assertStringContainsString("written to {$file}", $output);
        $this->assertStringNotContainsString('[template]', $output, 'The template is not printed');
        unlink($file);
    }

    public function testDoesNotOverwriteAFileWithoutForce(): void
    {
        $file = $this->base . '/template.toml';
        file_put_contents($file, 'mine');
        try {
            $this->runCommand(['dump-config', 'template', $file]);
            $this->fail('It should fail');
        } catch (LoggedError $e) {
            $this->assertStringContainsString('already exists', $e->getMessage());
        }
        $this->assertSame('mine', file_get_contents($file));

        $this->runCommand(['dump-config', '--force', 'template', $file]);
        $this->assertStringContainsString('[template]', file_get_contents($file));
        unlink($file);
    }

    public function testFailsIfTheFileCannotBeWritten(): void
    {
        $this->expectException(LoggedError::class);
        $this->expectExceptionMessage('Cannot write');
        $this->runCommand(['dump-config', 'template', $this->base . '/no-dir/template.toml']);
    }

    public function testFailsWithAnUnknownCommand(): void
    {
        $this->expectException(LoggedError::class);
        $this->expectExceptionMessage("Command 'nothing' not found");
        $this->runCommand(['dump-config', 'nothing']);
    }

    public function testTheCommandIsRequired(): void
    {
        $this->expectException(LoggedError::class);
        $this->runCommand(['dump-config']);
    }
}
