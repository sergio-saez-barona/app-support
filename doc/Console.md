# Console Classes

The `AppSupport\Console` namespace provides the building blocks for creating CLI applications. It handles command
registration, argument and option parsing, terminal output with colors, and application bootstrapping.

## Class Hierarchy

```
CLIItem (abstract)
├── Command (abstract)
├── Argument
│   └── InputFile
└── Option (abstract)
    ├── Flag
    └── Value
        ├── ConfigFile
        ├── InputDirectory
        └── OutputDirectory

Container
└── Console
```

---

## CLIItem

`AppSupport\Console\CLIItem` — Abstract base class for all CLI elements (commands, arguments, and options).

### Properties

| Property       | Type     | Description                                          |
|----------------|----------|------------------------------------------------------|
| `$name`        | `string` | The item name (protected, accessible by subclasses). |
| `$description` | `string` | A human-readable description of the item.            |

### Methods

| Method                                | Returns   | Description                   |
|---------------------------------------|-----------|-------------------------------|
| `getName()`                           | `string`  | Returns the item name.        |
| `setName(string $name)`               | `CLIItem` | Sets the item name. Fluent.   |
| `getDescription()`                    | `string`  | Returns the description.      |
| `setDescription(string $description)` | `static`  | Sets the description. Fluent. |

---

## Console

`AppSupport\Console\Console` — The main application class. Extends `AppSupport\Support\Container` and acts as the
service container, command registry, and entry point for the CLI application.

### Constructor

```php
new Console(string $basePath)
```

The constructor:

1. Registers itself as the global container instance and facade application.
2. Registers built-in services (`config`, `logger`, `repository`).
3. Loads the `console` configuration file to discover additional commands.
4. Loads the `app` configuration file to register singletons, classes, aliases, and service providers.

### Built-in Commands

Three commands are registered by default:

| Name    | Class                   | Description                    |
|---------|-------------------------|--------------------------------|
| `help`  | `Commands\HelpCommand`  | Displays help for a command. The descriptions of the command, its arguments and its options are word-wrapped to the terminal width (at most 100 columns), keeping the columns of the help. If the label of an option or argument is longer than its column (e.g. `-m, --mutation <unchanged:modified> [required]`), the description starts in the next line. |
| `list`  | `Commands\ListCommand`  | Lists all available commands. The descriptions of the sections and the commands are word-wrapped like the ones of `help`. |
| `about` | `Commands\AboutCommand` | Shows application information (authors and description; a long `app.info.brief` is word-wrapped to the terminal width, at most 100 columns). |

Additional commands are loaded from the `console` configuration file (`config/console.php`), which must return an array
with either a `commands` key (flat list) or a `sections` key (commands grouped by sections).

### Methods

| Method                                                      | Returns    | Description                                                                                                                                                            |
|-------------------------------------------------------------|------------|------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `getBasePath()`                                             | `string`   | Returns the application base path.                                                                                                                                     |
| `parseCommand(array $args, bool $useInternalConfig = true)` | `Command`  | Parses `$argv` into a command. Extracts the executable name, resolves the command name (or uses the default), finds the command, and parses its arguments and options. |
| `getDefaultCommand()`                                       | `string`   | Returns the default command name (initially `"list"`).                                                                                                                 |
| `setDefaultCommand(string $defaultCommand)`                 | `Console`  | Sets the default command. Throws `LogicException` if the command does not exist. Fluent.                                                                               |
| `showInfo()`                                                | `void`     | Prints the application logo, name, version, and date.                                                                                                                  |
| `showLogo()`                                                | `void`     | Prints a default ASCII art logo. Override this method to customize.                                                                                                    |
| `findCommand(string $name)`                                 | `?Command` | Finds and instantiates a command by name. Calls `boot()` on it. Logs a critical error if not found.                                                                    |
| `getCommands()`                                             | `array`    | Returns the full command registry (`name => classname`), always flat, also when `sections` is used.                                                                    |
| `getSections()`                                             | `array`    | Returns the normalized sections (`title`, `description`, `sorted`, `commands`) in configuration order. Empty when the flat `commands` array is used.                    |

### Configuration Files

The application expects two configuration files under the `config/` directory (or the path set in `app.config.path`):

**config/console.php** — Must return:

```php
return [
    'commands' => [
        'mycommand' => MyCommand::class,
    ],
];
```

Commands can be grouped in sections by using `sections` **instead of** `commands` (using both is a critical error):

```php
return [
    'sections' => [
        [
            'title'       => 'Analysis',                     // required
            'description' => 'Commands that analyze plans',  // optional, printed below the title
            'sorted'      => true,                           // optional, sort the commands by name (default false)
            'commands'    => [                               // required, same format as the flat array
                'check' => CheckCommand::class,
                'demand' => DemandCommand::class,
            ],
        ],
        [
            'title'    => 'Output',
            'commands' => ['diagram' => DiagramCommand::class],
        ],
    ],
];
```

| Field         | Type     | Required | Description                                                                    |
|---------------|----------|----------|--------------------------------------------------------------------------------|
| `title`       | `string` | **yes**  | Section title.                                                                 |
| `description` | `string` | no       | Text shown below the title by the `list` command.                              |
| `sorted`      | `bool`   | no       | Sort the section's commands alphabetically by name. By default, config order.  |
| `commands`    | `array`  | **yes**  | `name => classname`.                                                           |

Behavior:

- The hierarchy is flattened internally, so command resolution (`findCommand()`, `getCommands()`, `help`) works exactly
  as with the flat format.
- A command name cannot appear in more than one section (critical error). Unknown section fields are also errors.
- The `list` command prints the sections in order, each with its title, optional description and commands. Registered
  commands that belong to no section (e.g. the built-in `help`, `list` and `about`) are printed at the end in an
  *Other commands* section, sorted by name. To place them elsewhere, include them in a section
  (`'help' => \AppSupport\Console\Commands\HelpCommand::class`).

**config/app.php** — May return:

```php
return [
    'singletons' => [ /* alias => classname */ ],
    'classes'    => [ /* alias => classname */ ],
    'aliases'    => [ /* alias => abstract */ ],
    'providers'  => [ /* alias => classname */ ],
];
```

Providers are instantiated immediately and their `boot()` method is called if it exists. Singletons, classes, and
aliases are lazy-loaded.

---

## Command

`AppSupport\Console\Command` — Abstract base class for all commands. Extends `CLIItem`.

### Properties

| Property           | Type      | Description                                                    |
|--------------------|-----------|----------------------------------------------------------------|
| `$config`          | `?Config` | The configuration repository for this command's parsed values. |
| `$actualArguments` | `array`   | The raw argument strings that were successfully parsed.        |

### Methods

| Method                                                 | Returns           | Description                                                                                                               |
|--------------------------------------------------------|-------------------|---------------------------------------------------------------------------------------------------------------------------|
| `getOptionList()`                                      | `?OptionList`     | Returns the option list for this command.                                                                                 |
| `setOptionList(OptionList $optionList)`                | `static`          | Replaces the option list. Fluent.                                                                                         |
| `addArgument(Argument $argument)`                      | `void`            | Adds a positional argument. No arguments can be added after a multi-valued one.                                           |
| `getArguments()`                                       | `Argument[]`      | Returns all registered arguments.                                                                                         |
| `getActualArguments()`                                 | `array`           | Returns the raw parsed argument values.                                                                                   |
| `getHelp()` / `setHelp(string)`                        | `string` / `void` | Extended help text shown by the `help` command.                                                                           |
| `getUsage()` / `setUsage(string)`                      | `string` / `void` | Usage string (default: `"command [options] [arguments]"`).                                                                |
| `parse(array &$args, bool $useInternalConfig = false)` | `void`            | Parses the argument array. Options are parsed first, then positional arguments. Validates required arguments and options. |
| `loadOptions(array $definitions)`                      | `void`            | Bulk-loads options from a definition array (see below).                                                                   |
| `loadFlags(array $flags)`                              | `void`            | Bulk-loads flags from a definition array (see below).                                                                     |
| `boot()`                                               | —                 | **Abstract.** Called after instantiation to set up arguments, options, and flags.                                         |
| `execute()`                                            | —                 | **Abstract.** Called to run the command logic.                                                                            |

### loadOptions format

`loadOptions()` creates one `Value` option (`--name <value>`) per entry of the definitions array.

```php
$command->loadOptions([
    // key       => definition (associative format, recommended)
    'policy'     => ['description' => 'Scheduling policy', 'default' => 'fixed', 'validValues' => ['none', 'fixed', 'edf']],
    'o|output'   => ['description' => 'Output file'],                  // short alias: -o / --output
    'I|include'  => ['description' => 'Include path', 'multiple' => true],
    'source'     => ['description' => 'Source file', 'required' => true],
]);
```

The legacy positional format (`'length' => ['0', 'Simulation length']`) is still supported, see *Definition* below.

#### Key

| Form         | Meaning                                                                                                        |
|--------------|----------------------------------------------------------------------------------------------------------------|
| `long`       | Long option only: `--long <value>`.                                                                            |
| `short\|long` | Long option plus a short alias, split at the first `\|`: `-s <value>` and `--long <value>`. `short` is one char. |

The long name is also the config key under which the parsed value is stored (see *Parsing behavior*).

#### Definition

The definition can use one of two formats. The format is detected automatically: an array with at least one string
key is *associative*; anything else is *positional*.

##### Associative format (recommended)

```php
$command->loadOptions([
    's|policy' => [
        'description' => 'Scheduling policy',
        'default'     => 'fixed',
        'validValues' => ['none', 'fixed', 'edf'],
        'label'       => 'policy',
    ],
    'source' => ['description' => 'Source file', 'required' => true],
    'I|include' => ['description' => 'Include path', 'multiple' => true, 'default' => 'src'],
]);
```

| Field         | Type              | Required | Description                                                                                       |
|---------------|-------------------|----------|---------------------------------------------------------------------------------------------------|
| `description` | `string`          | **yes**  | Text shown by `help <command>`.                                                                   |
| `default`     | `string` / `null` | no       | Default value. Omitted or `null` = no default. `""` and `"0"` are real defaults.                  |
| `required`    | `bool`            | no       | The option must be given in the command line. Cannot be combined with `default`.                  |
| `multiple`    | `bool`            | no       | The option can be repeated; values are collected into an array.                                   |
| `validValues` | `string[]`        | no       | Whitelist of accepted values. Each single value is checked, also for `multiple` options. If a `default` is given it must be one of them. |
| `label`       | `string`          | no       | Placeholder shown in help (`--policy <label>`, default `value`).                                   |
| `type`        | `string`          | no       | The kind of option: `value` (default), `input-directory`, `output-directory` or `config-file` (see below). |

Errors (critical): missing or non-string `description`, unknown field, non-boolean `required`/`multiple`, non-array
`validValues`, `required` together with `default`, a `default` outside `validValues`, or an unknown `type`.

The `type` selects the class of the option (all of them are `Value`s, so the rest of the fields work in the same way):

| `type`             | Class             | Checks that the value...                                          |
|--------------------|-------------------|-------------------------------------------------------------------|
| `value`            | `Value`           | (no check)                                                        |
| `input-directory`  | `InputDirectory`  | is a directory that exists and can be read                        |
| `output-directory` | `OutputDirectory` | (see [OutputDirectory](#outputdirectory))                         |
| `config-file`      | `ConfigFile`      | is a file that can be read; its values are given to the other options (see [ConfigFile](#configfile)). It cannot have a `default`; it can be `multiple` (layers) |

The description of the definition replaces the one that the class has by default.

For `multiple` options the config always holds an array. A `default` is stored as a one-element array and is *replaced*
(not extended) by the values given in the command line.

##### Positional format (legacy)

```php
$command->loadOptions([
    'policy'   => 'fixed',                          // scalar: just the default
    'length'   => ['0', 'Simulation length'],       // [default, description]
    'o|output' => ['', 'Output file'],              // short alias: -o / --output
    'include'  => [null, 'Include path', true],     // [default, description, multiple]
    'plain'    => null,                             // no default value defined
]);
```

| Position | Name        | Type              | Required | Description                                                                                  |
|----------|-------------|-------------------|----------|----------------------------------------------------------------------------------------------|
| `[0]`    | default     | `string` / `null` | yes      | Default value. `null` = no default (nothing is written to the config).                       |
| `[1]`    | description | `string`          | no       | Text shown by `help <command>`.                                                              |
| `[2]`    | multiple    | `bool`            | no       | Repeatable option. Any other type is a critical error.                                       |

A scalar definition only sets the default and leaves the option without description. The positional format cannot
express `required`, `validValues` or `label`; use the associative format instead.

##### Notes

- Values are always strings; cast them in the command (`(int) $this->config->get('length')`).
- Options must appear before positional arguments on the command line (`cmd --length 10 file.tasks`).
- Options can also be built manually (`new Value(...)`, `setRequired()`, `setValidValues()`, ...) and added with
  `getOptionList()->addOption()`.

### loadFlags format

`loadFlags()` creates one `Flag` per entry. Each flag registers both `--name` and `--no-name`. The key accepts the same
`short|long` syntax as `loadOptions()` (a short alias `-s` always sets the flag to `true`).

```php
$command->loadFlags([
    // Associative format (recommended)
    'dry-run'   => ['description' => 'Run without making changes'],                  // default false
    'v|verbose' => ['description' => 'Verbose output', 'default' => true],

    // Positional format (legacy)
    'quiet'     => false,                                    // scalar: just the default
    'force'     => [false, 'Overwrite existing files'],      // [default, description]
]);
```

| Field         | Type     | Required | Description                                                    |
|---------------|----------|----------|----------------------------------------------------------------|
| `description` | `string` | **yes**  | Text shown by `help <command>`.                                |
| `default`     | `bool`   | no       | Default value (`false` when omitted). Non-booleans are errors. |

Errors (critical): missing or non-string `description`, unknown field, or a non-boolean default. In the positional
format, `[0]` is the default (`bool`, required) and `[1]` the description.

### Parsing behavior

The options are parsed first (so they have to come before the arguments), then the values of the configuration files
(options of type `config-file`) are given to the options that were not in the command line, then the arguments are
read, and finally the required arguments and options are checked.

When `$useInternalConfig` is `true`, parsed values are stored in a fresh local `Repository` with no key prefix. When
`false`, values are stored in the global config under the prefix `app.args.<commandName>.`, and `$this->config` is set
to a reference to that subtree.

---

## Argument

`AppSupport\Console\Argument` — Represents a positional command argument. Extends `CLIItem`.

### Constructor

```php
new Argument(string $name, string $description, bool $required = true, bool $multiple = false)
```

### Methods

| Method                       | Returns | Description                                                                                   |
|------------------------------|---------|-----------------------------------------------------------------------------------------------|
| `isRequired()`               | `bool`  | Whether the argument is required.                                                             |
| `setRequired(bool)`          | `void`  | Sets the required flag.                                                                       |
| `isMultiple()`               | `bool`  | Whether the argument accepts multiple values.                                                 |
| `setMultiple(bool)`          | `void`  | Sets the multiple flag.                                                                       |
| `isSet()`                    | `bool`  | Whether the argument has been parsed at least once.                                           |
| `parse(string $argumentStr)` | `bool`  | Validates and marks the argument as set. Returns `false` if already set and not multi-valued. |

---

## InputFile

`AppSupport\Console\InputFile` — An `Argument` subclass that validates the value is a readable file.

Overrides `parse()` to check that the file exists and is readable. Logs an error and returns `false` otherwise.

---

## Option

`AppSupport\Console\Option` — Abstract base class for command options (flags and values). Extends `CLIItem`.

### Constructor

```php
new Option(string $longName, string $shortName = "", string $path = "")
```

- `$longName` — Used with `--longName`.
- `$shortName` — Used with `-x` (single character).
- `$path` — The config key path. Defaults to `$longName`.

### Methods

| Method                                  | Returns  | Description                                             |
|-----------------------------------------|----------|---------------------------------------------------------|
| `getPath()`                             | `string` | Returns the config key path.                            |
| `getLongName()`                         | `string` | Returns the long option name.                           |
| `getShortName()`                        | `string` | Returns the short option name.                          |
| `isRequired()`                          | `bool`   | Whether the option is required.                         |
| `setRequired(bool)`                     | `static` | Sets the required flag. Fluent.                         |
| `isSet()`                               | `bool`   | Whether the option has been given, in the command line or in a configuration file. |
| `register(array &$long, array &$short)` | `void`   | Registers this option in the lookup arrays.             |
| `parse(...)`                            | `bool`   | **Abstract.** Parses the option from the argument list. |
| `configDefault(Config, string)`         | —        | **Abstract.** Writes the default value to the config.   |

---

## Flag

`AppSupport\Console\Flag` — A boolean option (no value argument). Extends `Option`.

### Behavior

- `--flag` sets the value to `true`.
- `--no-flag` sets the value to `false`.
- `-x` (short name) sets the value to `true`.

### Methods

| Method                          | Returns  | Description                                         |
|---------------------------------|----------|-----------------------------------------------------|
| `getDefault()`                  | `bool`   | Returns the default value.                          |
| `setDefault(bool)`              | `static` | Sets the default value. Fluent.                     |
| `register(...)`                 | `void`   | Registers both `--flag` and `--no-flag` long forms. |
| `configDefault(Config, string)` | `void`   | Writes the default boolean to the config.           |

---

## Value

`AppSupport\Console\Value` — An option that takes a value argument. Extends `Option`.

### Usage

```
--option value
-o value
```

### Methods

| Method                                       | Returns             | Description                                                                                            |
|----------------------------------------------|---------------------|--------------------------------------------------------------------------------------------------------|
| `isMultiple()` / `setMultiple(bool)`         | `bool` / `static`   | Whether the option can be specified multiple times. When multiple, values are collected into an array; a default is stored as a one-element array and replaced by the command-line values. |
| `getDefault()` / `setDefault(?string)`       | `?string` / `static`| The default value. `null` means no default is defined (`""` and `"0"` are valid defaults).           |
| `setRequired(bool)`                          | `static`            | When set to `true`, clears the default value.                                                          |
| `getValidValues()` / `setValidValues(array)` | `array` / `void`    | Restricts accepted values to a whitelist. Each single value is checked, also for multiple options.    |
| `isSet()`                                    | `bool`              | Whether the option has been parsed.                                                                    |
| `getLabel()` / `setLabel(string)`            | `string` / `void`   | The label shown in help output (default: `"value"`).                                                   |
| `configDefault(Config, string)`              | `void`              | Writes the default value to the config (only if it is not `null`).                                       |

---

## InputDirectory

`AppSupport\Console\InputDirectory` — A `Value` subclass that validates the value is a readable directory.

### Constructor

```php
new InputDirectory(string $longName, string $shortName = "")
```

Sets the description to `"Input directory"` automatically. After parsing, checks that each directory exists and is
readable.

---

## OutputDirectory

`AppSupport\Console\OutputDirectory` — A `Value` subclass for output directories.

### Constructor

```php
new OutputDirectory(string $longName, string $shortName = "", bool $errorIfExist = false)
```

### Methods

| Method                  | Returns | Description                                       |
|-------------------------|---------|---------------------------------------------------|
| `forceErrorIfExist()`   | `bool`  | Whether to error if the directory already exists. |
| `setErrorIfExist(bool)` | `void`  | Sets the error-if-exist flag.                     |

When `errorIfExist` is `true`, parsing fails if the directory already exists.

---

## ConfigFile

`AppSupport\Console\ConfigFile` — A `Value` option whose file gives values to the other options of
the command, so the options that are always the same do not have to be typed every time. It is declared with the type
`config-file`:

The name of the option is the one that the application gives it (`--config` in the examples, but it can be any name:
`--settings`, `--profile`...), and the `description`, `label`, `required`... are the ones of the definition.

```php
$this->loadOptions([
    'c|config' => ['description' => 'File with the values of the options', 'type' => 'config-file'],
    'n|count'  => ['description' => 'Number of tasks', 'required' => true],
    'policy'   => ['description' => 'Policy', 'default' => 'fixed'],
]);
```

The file is a [TOML](https://toml.io) file (`.toml`, read with the `php-collective/toml` library). The keys are the
**long names** of the options. A table with the name of a command has the values of that command only, and the keys
that are not in a table are common to every command that has an option with that name, so a file can serve several
commands:

```toml
granularity = 1000          # common: for the commands that have this option

[gen-periodic]              # only for gen-periodic
count = 20
utilization = 0.8
periods = "10:1000"

[gen-gmf]
frames = "2:5"
policy = ["a", "b"]         # a list, for an option that can be repeated
grid = false                # true or false, for a flag
```

**Priority.** For every option the value is, from the lowest to the highest priority: its default, a common value of
the file, the value in the table of the command, and the command line. The file is read *after* all the options of the
command line, so the result does not depend on the order of the arguments (`--config f.toml -n 3` and
`-n 3 --config f.toml` are the same). A list of the command line replaces the one of the file, like it replaces a default.

**Layers.** The option can be `multiple`: every file is a layer, and when the same option is in several files the one
of the **last** `--config` wins (`--config base.toml --config project.toml`). The layer has priority over the
specificity: a common key of a later file beats the table of the command of an earlier file. Lists replace each other,
they are not merged.

A command can also have several options of this type (e.g. `--defaults` and `--overrides`). They are applied in the
order in which they are declared, so the first one that gives a value to an option wins.

**Required options.** An option that is required is satisfied by the file: it only fails if it is in neither the
command line nor the file.

**Values.** Text and numbers are the text that would be typed in the command line (`count = 5` is `"5"`), and are
checked in the same way (`validValues`, directories that have to exist...). `true` and `false` are only for flags, and
a list only for options that can be repeated (an empty list gives nothing). Tables inside a table are not supported.

**Errors and warnings.**

- A key in the table of the command that is not an option of the command is an error (it is probably a typo), and so is
  the `config` option itself. The common keys that the command does not have are ignored.
- A table that is not the name of a command of the application is only a warning.
- A file that does not exist, cannot be read, has another extension, or is not valid TOML (the message says the line and
  the column) is an error.

Relative paths in the values are used as they are written, like in the command line (relative to the current directory).

### Constructor

```php
new ConfigFile(string $longName, string $shortName = "")
```

Its description is `"Configuration file with values for the options (the command line has priority)"` and its label
`file`, unless the definition gives others.

### Methods

| Method                                      | Returns    | Description                                                                                                  |
|---------------------------------------------|------------|--------------------------------------------------------------------------------------------------------------|
| `addParser(string $extension, FileParser $parser)` | `static` | Adds the parser of another format of file (by its extension), or replaces the one that there is. Fluent. |
| `getExtensions()`                           | `string[]` | The extensions of the files that can be read (`toml` by default).                                            |
| `getFiles()`                                | `string[]` | The files given in the command line, in order.                                                               |
| `valuesFor(string $command, array $commands)` | `array`  | The layers (one per file, highest priority first), each with `file`, `section` (its table), `common`, and `unknownSections`. |

A `FileParser` (`AppSupport\Config\FileParser`) has one method, `parse(string $contents): array`, that returns the keys
of the file with their values (and the tables as arrays with names) or throws a `ConfigFileError`.
`TomlFileParser` is the one of TOML.

---

## OptionList

`AppSupport\Console\OptionList` — Manages a collection of options for a command.

### Constants

| Constant        | Value         | Description                                         |
|-----------------|---------------|-----------------------------------------------------|
| `defaultPrefix` | `"app.args."` | Default config key prefix for parsed option values. |

### Methods

| Method                                          | Returns             | Description                                                                                         |
|-------------------------------------------------|---------------------|-----------------------------------------------------------------------------------------------------|
| `getConfigPrefix()` / `setConfigPrefix(string)` | `string` / `static` | The config key prefix.                                                                              |
| `addOption(Option $option)`                     | `Option`            | Registers an option.                                                                                |
| `getOptions()`                                  | `Option[]`          | Returns all registered options.                                                                     |
| `parseOption(array &$args, Config $config)`     | `void`              | Parses one option from the front of the args array. Supports `--long`, `--no-long`, and `-x` forms. |
| `isOption(array $args)`                         | `bool`              | Static. Returns `true` if the first element starts with `-`.                                        |
| `findOption(string $key, bool $useLongName)`    | `Option`            | Looks up an option by long or short name. Logs critical error if not found.                         |
| `configDefaults(Config $config)`                | `void`              | Writes all option defaults to the config.                                                           |
| `setFromValues(array $values, Config $config, bool $strict, string $origin)` | `void` | Gives values (long name => value) to the options that have not been given, checking them like the ones of the command line. With `$strict` an option that does not exist is an error, and if not it is ignored. `$origin` is used in the messages. |
| `checkRequired()`                               | `bool`              | Returns `true` if any required `Value` option has not been set. Logs errors for each missing one.   |

---

## Terminal

`AppSupport\Console\Terminal` — Static utility class for colored terminal output using ANSI escape codes.

### Color Constants

| Constant    | Code | | Constant       | Code |
|-------------|------|-|----------------|------|
| `Black`     | 30   | | `DarkGray`     | 90   |
| `Red`       | 31   | | `LightRed`     | 91   |
| `Green`     | 32   | | `LightGreen`   | 92   |
| `Yellow`    | 33   | | `LightYellow`  | 93   |
| `Blue`      | 34   | | `LightBlue`    | 94   |
| `Magenta`   | 35   | | `LightMagenta` | 95   |
| `Cyan`      | 36   | | `LightCyan`    | 96   |
| `LightGray` | 37   | | `White`        | 97   |
| `Default`   | 39   | |                |      |

### Style Constants

`Bold` (1), `Dim` (2), `Underline` (4), `Blink` (5), `Reverse` (7).

### Methods

| Method                                    | Returns  | Description                                  |
|-------------------------------------------|----------|----------------------------------------------|
| `foreground(int $color)`                  | `int`    | Returns the foreground code (identity).      |
| `background(int $color)`                  | `int`    | Returns the background code (`$color + 10`). |
| `getColor(int $fg, int $bg = Default)`    | `string` | Returns the ANSI escape sequence string.     |
| `getReset()`                              | `string` | Returns the reset escape sequence.           |
| `getDefault()`                            | `string` | Returns the default color escape sequence.   |
| `color(int $fg, int $bg = Default)`       | `void`   | Echoes the color escape sequence.            |
| `reset()`                                 | `void`   | Echoes the reset sequence.                   |
| `default()`                               | `void`   | Echoes the default color sequence.           |
| `print(string $text, int $fg, int $bg)`   | `void`   | Prints colored text, then resets.            |
| `println(string $text, int $fg, int $bg)` | `void`   | Like `print()` but appends a newline.        |
| `getWidth(int $default = 80)`             | `int`    | Terminal width in columns: `COLUMNS` variable, then `stty size` if the output is a terminal, else `$default`. |
| `wrap(string $text, int $width, string $indent = "")` | `string` | Multibyte-safe word wrap to `$width` columns (indentation included), prepending `$indent` to every line. Keeps existing line breaks and does not split long words. |
| `getTextWidth(int $max = 100)`            | `int`    | Width for the texts of the help: the terminal width, at most `$max` columns. |
| `wrapHanging(string $text, int $width, int $firstColumn, int $indent, int $hang = 2)` | `string` | Word wrap of a text that is printed after a label (e.g. the description of an option). The first line starts at column `$firstColumn` and its continuation lines go to that column; the other lines of the text (separated by `\n`) are indented `$indent` columns plus their own leading spaces, and their continuation lines `$hang` more. Multibyte-safe, long words are not split, and the result has no trailing newline. |
