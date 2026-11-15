<?php

namespace AppSupport\Tests\Console;

use AppSupport\Console\ConfigFile;
use AppSupport\Console\InputDirectory;
use AppSupport\Console\OutputDirectory;
use AppSupport\Console\Value;
use AppSupport\Support\Facades\Config;
use AppSupport\Tests\AppTestCase;
use AppSupport\Tests\Support\AlphaCommand;
use AppSupport\Tests\Support\ConfigurableCommand;
use AppSupport\Tests\Support\LoggedError;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The option of type config-file: a TOML file with values for the other options. The values are used only
 * if the option was not in the command line, the options that are required can be given by the file, and
 * a table with the name of the command has the values of that command only.
 */
final class ConfigFileTest extends AppTestCase
{
    private const OPTIONS = [
        'config' => ['description' => 'Configuration file', 'type' => 'config-file'],
        'n|count' => ['description' => 'Count', 'required' => true],
        'policy' => ['description' => 'Policy', 'default' => 'fixed', 'validValues' => ['none', 'fixed', 'edf']],
        'inc' => ['description' => 'Include', 'multiple' => true, 'default' => 'src'],
        'ratio' => ['description' => 'Ratio'],
    ];

    private const FLAGS = [
        'grid' => ['description' => 'Grid', 'default' => true],
        'quiet' => ['description' => 'Quiet'],
    ];

    protected function setUp(): void
    {
        parent::setUp();
        // The command of the tests is "configurable"; "other" is another command of the application
        $this->boot(['commands' => ['other' => AlphaCommand::class]]);
    }

    /** Writes a TOML file and returns its path */
    private function toml(string $contents, string $name = 'values.toml'): string
    {
        file_put_contents($this->base . '/config/' . $name, $contents);

        return $this->base . '/config/' . $name;
    }

    /**
     * @param string[] $args
     */
    private function parsed(array $args, array $options = self::OPTIONS, array $flags = self::FLAGS): ConfigurableCommand
    {
        $command = new ConfigurableCommand($options, $flags);
        $command->boot();
        $command->parse($args, true);

        return $command;
    }

    private function failsWith(array $args, string $message, array $options = self::OPTIONS): void
    {
        try {
            $this->parsed($args, $options);
            $this->fail("It should fail with: {$message}");
        } catch (LoggedError $error) {
            $this->assertStringContainsString($message, $error->getMessage());
        }
    }

    // ---- Values of the file

    public function testTheValuesOfTheFileReplaceTheDefaults(): void
    {
        $file = $this->toml("[configurable]\ncount = 5\npolicy = \"edf\"\ninc = [\"a\", \"b\"]\nratio = 0.8\n");

        $config = $this->parsed(['--config', $file])->config();

        $this->assertSame('5', $config->get('count'));
        $this->assertSame('edf', $config->get('policy'));
        $this->assertSame(['a', 'b'], $config->get('inc'));
        $this->assertSame('0.8', $config->get('ratio'));
    }

    public function testWhatTheFileDoesNotHaveKeepsItsDefault(): void
    {
        $file = $this->toml("[configurable]\ncount = 5\n");

        $config = $this->parsed(['--config', $file])->config();

        $this->assertSame('fixed', $config->get('policy'));
        $this->assertSame(['src'], $config->get('inc'));
        $this->assertTrue($config->get('grid'));
        $this->assertFalse($config->isset('ratio'));
    }

    public function testWithoutTheOptionNothingIsRead(): void
    {
        $this->toml("[configurable]\npolicy = \"edf\"\n");

        $config = $this->parsed(['-n', '3'])->config();

        $this->assertSame('fixed', $config->get('policy'));
    }

    public function testNumbersAreTextLikeInTheCommandLine(): void
    {
        $file = $this->toml("[configurable]\ncount = 12\nratio = 1e3\n");

        $config = $this->parsed(['--config', $file])->config();

        $this->assertSame('12', $config->get('count'));
        $this->assertSame('1000', $config->get('ratio'));
    }

    // ---- Priority

    /** @return array<string, array{bool}> */
    public static function orders(): array
    {
        return ['the file first' => [true], 'the file last' => [false]];
    }

    #[DataProvider('orders')]
    public function testTheCommandLineHasPriorityWhateverTheOrder(bool $fileFirst): void
    {
        $file = $this->toml("[configurable]\ncount = 5\npolicy = \"edf\"\ninc = [\"a\", \"b\"]\ngrid = false\n");
        $args = $fileFirst
            ? ['--config', $file, '-n', '9', '--policy', 'none', '--inc', 'x', '--grid']
            : ['-n', '9', '--policy', 'none', '--inc', 'x', '--grid', '--config', $file];

        $config = $this->parsed($args)->config();

        $this->assertSame('9', $config->get('count'));
        $this->assertSame('none', $config->get('policy'));
        $this->assertSame(['x'], $config->get('inc'), 'A list of the command line replaces the one of the file');
        $this->assertTrue($config->get('grid'));
    }

    #[DataProvider('orders')]
    public function testTheFileGivesWhatTheCommandLineDoesNot(bool $fileFirst): void
    {
        $file = $this->toml("[configurable]\ncount = 5\npolicy = \"edf\"\n");
        $args = $fileFirst ? ['--config', $file, '--policy', 'none'] : ['--policy', 'none', '--config', $file];

        $config = $this->parsed($args)->config();

        $this->assertSame('5', $config->get('count'));
        $this->assertSame('none', $config->get('policy'));
    }

    public function testTheTableOfTheCommandHasPriorityOverTheCommonValues(): void
    {
        $file = $this->toml("policy = \"none\"\nratio = 1.5\ncount = 1\n\n[configurable]\npolicy = \"edf\"\ncount = 7\n");

        $config = $this->parsed(['--config', $file])->config();

        $this->assertSame('edf', $config->get('policy'), 'The table of the command wins');
        $this->assertSame('1.5', $config->get('ratio'), 'A common value is used if the table has not that option');
        $this->assertSame('7', $config->get('count'));
    }

    // ---- Required options

    public function testARequiredOptionCanBeGivenByTheFile(): void
    {
        $file = $this->toml("[configurable]\ncount = 5\n");

        $command = $this->parsed(['--config', $file]);

        $this->assertSame('5', $command->config()->get('count'));
        $this->assertTrue($command->getOptionList()->getOptions()['count']->isSet());
    }

    public function testARequiredOptionCanBeGivenByACommonValue(): void
    {
        $file = $this->toml("count = 5\n");

        $this->assertSame('5', $this->parsed(['--config', $file])->config()->get('count'));
    }

    public function testARequiredOptionThatIsNotInTheFileIsStillRequired(): void
    {
        $file = $this->toml("[configurable]\npolicy = \"edf\"\n");

        try {
            $this->parsed(['--config', $file]);
            $this->fail('count is required');
        } catch (LoggedError $error) {
            $this->assertStringContainsString('Required options are missed', $error->getMessage());
            $this->assertTrue($this->logger->has('error', "Option 'count' is required"));
        }
    }

    public function testARequiredOptionIsStillRequiredWithoutTheFile(): void
    {
        $this->failsWith([], 'Required options are missed');
    }

    public function testTheConfigurationFileCanBeRequired(): void
    {
        $options = ['config' => ['description' => 'File', 'type' => 'config-file', 'required' => true]];

        $this->failsWith([], 'Required options are missed', $options);
    }

    // ---- Tables

    public function testTheTableOfAnotherCommandIsIgnored(): void
    {
        $file = $this->toml("[other]\ncount = 3\n\n[configurable]\ncount = 5\n");

        $this->assertSame('5', $this->parsed(['--config', $file])->config()->get('count'));
        $this->assertFalse($this->logger->has('warning', 'is not a command'));
    }

    public function testATableThatIsNotACommandIsAWarning(): void
    {
        $file = $this->toml("[typo]\ncount = 3\n\n[configurable]\ncount = 5\n");

        $this->parsed(['--config', $file]);

        $this->assertTrue($this->logger->has('warning', "the table [typo] is not a command"));
    }

    public function testCommonValuesOfOptionsThatTheCommandDoesNotHaveAreIgnored(): void
    {
        $file = $this->toml("frames = \"2:5\"\ncount = 5\n");

        $this->assertSame('5', $this->parsed(['--config', $file])->config()->get('count'));
    }

    public function testAnUnknownOptionInTheTableOfTheCommandIsAnError(): void
    {
        $file = $this->toml("[configurable]\ncount = 5\ncolour = \"red\"\n");

        $this->failsWith(['--config', $file], "Unknown option 'colour' in the configuration file '{$file}' (table [configurable])");
    }

    public function testTheShortNameIsNotAKey(): void
    {
        $file = $this->toml("[configurable]\nn = 5\n");

        $this->failsWith(['--config', $file], "Unknown option 'n'");
    }

    public function testTheConfigurationFileCannotBeGivenInTheFile(): void
    {
        $this->failsWith(['--config', $this->toml("[configurable]\nconfig = \"other.toml\"\ncount = 5\n")],
            "The option 'config' cannot be given in the configuration file");

        // A common value with that name is not for this command only: it is ignored
        $this->assertSame('5', $this->parsed(['--config', $this->toml("config = \"other.toml\"\ncount = 5\n", 'common.toml')])->config()->get('count'));
    }

    // ---- Types of the values

    /** @return array<string, array{string, string}> */
    public static function invalidValues(): array
    {
        return [
            'true for an option with a value' => ["count = true\n", "The option 'count' in the configuration file"],
            'text for a flag' => ["count = 1\ngrid = \"yes\"\n", "The option 'grid' in the configuration file"],
            'number for a flag' => ["count = 1\ngrid = 1\n", "requires true or false"],
            'list for an option that is not multiple' => ["count = 1\npolicy = [\"edf\"]\n", 'cannot have a list of values'],
            'list in a list' => ["count = 1\ninc = [[\"a\"]]\n", 'requires a text or a number'],
            'a table for an option' => ["count = 1\n\n[configurable.policy]\nz = 1\n", 'requires a value, not a table'],
            'a date with time' => ["count = 1\nratio = 1979-05-27T07:32:00Z\n", 'requires a text or a number'],
            'a value that is not valid' => ["count = 1\npolicy = \"zzz\"\n", "Invalid value for the option 'policy'"],
        ];
    }

    #[DataProvider('invalidValues')]
    public function testInvalidValues(string $toml, string $message): void
    {
        $this->failsWith(['--config', $this->toml("[configurable]\n" . $toml)], $message);
    }

    public function testTheValuesAreCheckedLikeTheOnesOfTheCommandLine(): void
    {
        $file = $this->toml("[configurable]\ncount = 1\npolicy = \"zzz\"\n");

        try {
            $this->parsed(['--config', $file]);
            $this->fail('An invalid value is an error');
        } catch (LoggedError) {
            $this->assertTrue($this->logger->has('error', "Invalid value 'zzz' for option 'policy'"));
            $this->assertTrue($this->logger->has('info', 'Valid values are: none, fixed, edf'));
        }
    }

    public function testEveryValueOfAListIsChecked(): void
    {
        $options = ['config' => ['description' => 'File', 'type' => 'config-file'],
            'inc' => ['description' => 'Include', 'multiple' => true, 'validValues' => ['a', 'b']]];

        $this->assertSame(['a', 'b'], $this->parsed(['--config', $this->toml("inc = [\"a\", \"b\"]\n")], $options, [])->config()->get('inc'));
        $this->failsWith(['--config', $this->toml("inc = [\"a\", \"z\"]\n", 'bad.toml')], "Invalid value for the option 'inc'", $options);
    }

    public function testAnEmptyListGivesNothing(): void
    {
        $file = $this->toml("[configurable]\ncount = 1\ninc = []\n");

        $this->assertSame(['src'], $this->parsed(['--config', $file])->config()->get('inc'));
    }

    // ---- Flags

    public function testFlagsFromTheFile(): void
    {
        $file = $this->toml("[configurable]\ncount = 1\ngrid = false\nquiet = true\n");

        $config = $this->parsed(['--config', $file])->config();

        $this->assertFalse($config->get('grid'));
        $this->assertTrue($config->get('quiet'));
    }

    #[DataProvider('orders')]
    public function testFlagsOfTheCommandLineHavePriority(bool $fileFirst): void
    {
        $file = $this->toml("[configurable]\ncount = 1\ngrid = true\nquiet = false\n");
        $flags = ['--no-grid', '--quiet'];
        $args = $fileFirst ? ['--config', $file, ...$flags] : [...$flags, '--config', $file];

        $config = $this->parsed($args)->config();

        $this->assertFalse($config->get('grid'));
        $this->assertTrue($config->get('quiet'));
    }

    public function testFlagsKnowIfTheyWereGiven(): void
    {
        $command = $this->parsed(['-n', '1', '--grid']);

        $this->assertTrue($command->getOptionList()->getOptions()['grid']->isSet());
        $this->assertFalse($command->getOptionList()->getOptions()['quiet']->isSet());
    }

    // ---- The file

    public function testAFileThatDoesNotExist(): void
    {
        try {
            $this->parsed(['--config', $this->base . '/config/missing.toml']);
            $this->fail('A file that does not exist is an error');
        } catch (LoggedError $error) {
            $this->assertStringContainsString("Error while parsing option 'config'", $error->getMessage());
            $this->assertTrue($this->logger->has('error', 'missing.toml\' cannot be read'));
        }
    }

    public function testADirectoryIsNotAFile(): void
    {
        $this->expectException(LoggedError::class);

        $this->parsed(['--config', $this->base . '/config']);
    }

    public function testAFormatThatIsNotSupported(): void
    {
        $file = $this->toml("count = 1\n", 'values.ini');

        try {
            $this->parsed(['--config', $file]);
            $this->fail('Only the formats that have a parser are supported');
        } catch (LoggedError) {
            $this->assertTrue($this->logger->has('error', 'has a format that is not supported (supported: .toml)'));
        }
    }

    public function testAnInvalidFileSaysWhereTheErrorIs(): void
    {
        $file = $this->toml("count = 1\npolicy = \n");

        $this->failsWith(['--config', $file], "Configuration file '{$file}': line 2");
    }

    public function testAnotherParserCanBeAdded(): void
    {
        $parser = new class implements \AppSupport\Config\FileParser {
            public function parse(string $contents): array
            {
                return json_decode($contents, true);
            }
        };
        $option = (new ConfigFile('config'))->addParser('json', $parser);
        $command = new ConfigurableCommand(['n|count' => ['description' => 'Count', 'required' => true]]);
        $command->boot();
        $command->getOptionList()->addOption($option);
        $args = ['--config', $this->toml('{"configurable": {"count": 8}}', 'values.json')];

        $command->parse($args, true);

        $this->assertSame('8', $command->config()->get('count'));
        $this->assertSame(['toml', 'json'], $option->getExtensions());
    }

    public function testAnOptionOfTheGlobalConfiguration(): void
    {
        // Without the internal configuration the values go to app.args.<command>
        $file = $this->toml("[configurable]\ncount = 5\npolicy = \"edf\"\n");
        $command = new ConfigurableCommand(self::OPTIONS, self::FLAGS);
        $command->boot();
        $args = ['--config', $file];

        $command->parse($args, false);

        $this->assertSame('5', Config::get('app.args.configurable.count'));
        $this->assertSame('edf', Config::get('app.args.configurable.policy'));
        $this->assertSame('5', $command->config()->get('count'));
    }

    public function testTheOptionHasADescriptionAndALabel(): void
    {
        $option = new ConfigFile('config');

        $this->assertSame('config', $option->getLongName());
        $this->assertSame('file', $option->getLabel());
        $this->assertNotSame('', $option->getDescription());
    }

    public function testTheNameOfTheOptionIsChosenByTheApplication(): void
    {
        $file = $this->toml("[configurable]\ncount = 5\n");
        $options = [
            's|settings' => ['description' => 'The settings', 'type' => 'config-file', 'label' => 'path'],
            'n|count' => ['description' => 'Count', 'required' => true],
        ];

        foreach ([['--settings', $file], ['-s', $file]] as $args) {
            $command = $this->parsed($args, $options, []);
            $this->assertSame('5', $command->config()->get('count'));
        }

        $option = $this->parsed(['-n', '1'], $options, [])->getOptionList()->getOptions()['settings'];
        $this->assertSame('settings', $option->getLongName());
        $this->assertSame('path', $option->getLabel(), 'The label of the definition replaces the one of the class');
        $this->assertSame('The settings', $option->getDescription());
        $this->assertArrayNotHasKey('config', $this->parsed(['-n', '1'], $options, [])->getOptionList()->getOptions());
    }

    // ---- Layers: the option can be multiple

    private const LAYERS = [
        'config' => ['description' => 'Configuration files', 'type' => 'config-file', 'multiple' => true],
        'n|count' => ['description' => 'Count', 'required' => true],
        'policy' => ['description' => 'Policy', 'default' => 'fixed', 'validValues' => ['none', 'fixed', 'edf']],
        'inc' => ['description' => 'Include', 'multiple' => true, 'default' => 'src'],
        'ratio' => ['description' => 'Ratio'],
    ];

    private function layers(array $args): ConfigurableCommand
    {
        return $this->parsed($args, self::LAYERS, self::FLAGS);
    }

    public function testTheLastFileHasPriority(): void
    {
        $base = $this->toml("[configurable]\ncount = 1\npolicy = \"edf\"\nratio = 0.5\n", 'base.toml');
        $project = $this->toml("[configurable]\ncount = 2\n", 'project.toml');

        $config = $this->layers(['--config', $base, '--config', $project])->config();

        $this->assertSame('2', $config->get('count'), 'The last file wins');
        $this->assertSame('edf', $config->get('policy'), 'What the last file does not have comes from the previous ones');
        $this->assertSame('0.5', $config->get('ratio'));
        $this->assertSame('1', $this->layers(['--config', $project, '--config', $base])->config()->get('count'), 'The order of the command line decides');
    }

    public function testEveryFileIsALayerBeforeBeingASpecificOne(): void
    {
        // The common value of the last file has priority over the table of the command of the first one
        $base = $this->toml("count = 1\n[configurable]\npolicy = \"edf\"\n", 'base.toml');
        $project = $this->toml("policy = \"none\"\n", 'project.toml');

        $config = $this->layers(['--config', $base, '--config', $project])->config();

        $this->assertSame('none', $config->get('policy'));
        $this->assertSame('1', $config->get('count'));
    }

    public function testTheCommandLineHasPriorityOverEveryFile(): void
    {
        $base = $this->toml("[configurable]\ncount = 1\npolicy = \"edf\"\n", 'base.toml');
        $project = $this->toml("[configurable]\ncount = 2\npolicy = \"none\"\n", 'project.toml');

        $config = $this->layers(['--config', $base, '-n', '9', '--config', $project])->config();

        $this->assertSame('9', $config->get('count'));
        $this->assertSame('none', $config->get('policy'));
    }

    public function testAListOfALayerReplacesTheOnesOfTheLayersBefore(): void
    {
        $base = $this->toml("[configurable]\ncount = 1\ninc = [\"a\", \"b\"]\n", 'base.toml');
        $project = $this->toml("[configurable]\ninc = [\"c\"]\n", 'project.toml');

        $this->assertSame(['c'], $this->layers(['--config', $base, '--config', $project])->config()->get('inc'));
        $this->assertSame(['a', 'b'], $this->layers(['--config', $project, '--config', $base])->config()->get('inc'));
    }

    public function testARequiredOptionCanBeInAnyLayer(): void
    {
        $base = $this->toml("[configurable]\ncount = 4\n", 'base.toml');
        $project = $this->toml("[configurable]\npolicy = \"none\"\n", 'project.toml');

        $this->assertSame('4', $this->layers(['--config', $base, '--config', $project])->config()->get('count'));
    }

    public function testEveryLayerIsCheckedAndTheErrorNamesItsFile(): void
    {
        $base = $this->toml("[configurable]\ncount = 1\n", 'base.toml');
        $project = $this->toml("[configurable]\ncolour = \"red\"\n", 'project.toml');

        $this->failsWith(['--config', $base, '--config', $project], "Unknown option 'colour' in the configuration file '{$project}'", self::LAYERS);
    }

    public function testTheSameFileTwice(): void
    {
        $file = $this->toml("[configurable]\ncount = 3\n");

        $this->assertSame('3', $this->layers(['--config', $file, '--config', $file])->config()->get('count'));
    }

    public function testAFileThatIsNotValidIsAnErrorInTheLayerThatHasIt(): void
    {
        $base = $this->toml("[configurable]\ncount = 1\n", 'base.toml');
        $bad = $this->toml("count = \n", 'bad.toml');

        $this->failsWith(['--config', $base, '--config', $bad], "Configuration file '{$bad}': line 1", self::LAYERS);
    }

    public function testTheFilesAreKnownInTheOrderOfTheCommandLine(): void
    {
        $base = $this->toml("[configurable]\ncount = 1\n", 'base.toml');
        $project = $this->toml("[configurable]\ncount = 2\n", 'project.toml');
        $command = $this->layers(['--config', $base, '--config', $project]);

        $this->assertSame([$base, $project], $command->getOptionList()->getOptions()['config']->getFiles());
    }

    public function testAnOptionThatIsNotMultipleCannotBeGivenTwice(): void
    {
        $base = $this->toml("[configurable]\ncount = 1\n", 'base.toml');
        $project = $this->toml("[configurable]\ncount = 2\n", 'project.toml');

        $this->failsWith(['--config', $base, '--config', $project], "Option 'config' does not support multiple values");
    }

    public function testSeveralConfigurationFilesWithDifferentNames(): void
    {
        $options = [
            'defaults' => ['description' => 'Defaults', 'type' => 'config-file'],
            'overrides' => ['description' => 'Overrides', 'type' => 'config-file'],
            'n|count' => ['description' => 'Count'],
            'policy' => ['description' => 'Policy'],
        ];
        $defaults = $this->toml("[configurable]\ncount = 1\npolicy = \"fixed\"\n", 'defaults.toml');
        $overrides = $this->toml("[configurable]\ncount = 2\n", 'overrides.toml');

        // The files are applied in the order of the options of the command: the first one that has a value wins
        $config = $this->parsed(['--defaults', $defaults, '--overrides', $overrides], $options, [])->config();

        $this->assertSame('1', $config->get('count'));
        $this->assertSame('fixed', $config->get('policy'));
    }

    // ---- Types of options in loadOptions

    public function testOptionTypes(): void
    {
        $command = new ConfigurableCommand([
            'plain' => ['description' => 'Plain'],
            'explicit' => ['description' => 'Explicit', 'type' => 'value'],
            'in' => ['description' => 'Input directory', 'type' => 'input-directory'],
            'o|out' => ['description' => 'Output directory', 'type' => 'output-directory'],
            'c|config' => ['description' => 'Config file', 'type' => 'config-file'],
        ]);
        $command->boot();
        $options = $command->getOptionList()->getOptions();

        $this->assertSame(Value::class, $options['plain']::class);
        $this->assertSame(Value::class, $options['explicit']::class);
        $this->assertSame(InputDirectory::class, $options['in']::class);
        $this->assertSame(OutputDirectory::class, $options['out']::class);
        $this->assertSame(ConfigFile::class, $options['config']::class);
        $this->assertSame('o', $options['out']->getShortName());
        $this->assertSame('Output directory', $options['out']->getDescription());
        $this->assertSame('Input directory', $options['in']->getDescription());
        $this->assertSame('Config file', $options['config']->getDescription(), 'The description of the definition is used');
    }

    public function testTheTypesOfDirectoriesCheckTheValues(): void
    {
        $options = ['in' => ['description' => 'Input', 'type' => 'input-directory'], 'out' => ['description' => 'Output', 'type' => 'output-directory']];

        $config = $this->parsed(['--in', $this->base, '--out', $this->base . '/new'], $options, [])->config();
        $this->assertSame($this->base, $config->get('in'));
        $this->assertSame($this->base . '/new', $config->get('out'));

        $this->failsWith(['--in', $this->base . '/missing'], 'Error while parsing option', $options);
    }

    public function testTheTypesOfDirectoriesCheckTheValuesOfTheFile(): void
    {
        $options = ['config' => ['description' => 'File', 'type' => 'config-file'],
            'in' => ['description' => 'Input', 'type' => 'input-directory']];

        $this->assertSame($this->base, $this->parsed(['--config', $this->toml("in = \"{$this->base}\"\n")], $options, [])->config()->get('in'));

        try {
            $this->parsed(['--config', $this->toml("in = \"{$this->base}/missing\"\n", 'bad.toml')], $options, []);
            $this->fail('The directory has to exist');
        } catch (LoggedError $error) {
            $this->assertStringContainsString("Invalid value for the option 'in'", $error->getMessage());
            $this->assertTrue($this->logger->has('error', 'does not exist or cannot be read'));
        }
    }

    /** @return array<string, array{array, string}> */
    public static function invalidTypes(): array
    {
        return [
            'unknown type' => [['description' => 'd', 'type' => 'file'], "Unknown type 'file' in option 'opt'. Expected: value, input-directory, output-directory, config-file"],
            'type is not a text' => [['description' => 'd', 'type' => 5], "Unknown type 'integer' in option 'opt'"],
            'configuration file with a default' => [['description' => 'd', 'type' => 'config-file', 'default' => 'a.toml'], "Option 'opt' of type 'config-file' cannot have a default value"],
        ];
    }

    #[DataProvider('invalidTypes')]
    public function testInvalidTypes(array $definition, string $message): void
    {
        $this->expectException(LoggedError::class);
        $this->expectExceptionMessage($message);

        (new ConfigurableCommand(['opt' => $definition]))->boot();
    }
}
