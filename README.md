# AppSupport

A lightweight PHP library for building command-line applications. It provides command routing, argument and option parsing, a service container, configuration management, facades, and colored terminal output.

## Requirements

- PHP >= 8.2
- `ext-mbstring`

## Installation

```bash
composer require ssaezba/appsupport
```

## Quick Start

Create an entry point script (e.g. `bin/myapp`):

```php
#!/usr/bin/env php
<?php

require __DIR__ . '/../vendor/autoload.php';

use AppSupport\Console\Console;

$app = new Console(dirname(__DIR__));
$command = $app->parseCommand($argv);
$command->execute();
```

Create a configuration file at `config/console.php`:

```php
<?php

return [
    'commands' => [
        'greet' => App\Commands\GreetCommand::class,
    ],
];
```

And `config/app.php`:

```php
<?php

return [
    'info' => [
        'name'    => 'My CLI Tool',
        'version' => '1.0.0',
        'date'    => '2026',
        'authors' => ['Your Name'],
        'brief'   => 'A short description of your tool.',
    ],
    'singletons' => [],
    'classes'    => [],
    'aliases'    => [],
    'providers'  => [],
];
```

Then define your command:

```php
<?php

namespace App\Commands;

use AppSupport\Console\Argument;
use AppSupport\Console\Command;
use AppSupport\Console\Terminal;

class GreetCommand extends Command
{
    public function __construct()
    {
        parent::__construct();
        $this->setName('greet');
        $this->setDescription('Greets someone');
        $this->setUsage('greet <name>');
    }

    public function boot()
    {
        $this->addArgument(new Argument('name', 'The person to greet'));
    }

    public function execute()
    {
        $name = $this->actualArguments[0];
        Terminal::println("Hello, {$name}!", Terminal::Green);
    }
}
```

Run it:

```bash
php bin/myapp greet World
# Hello, World!
```

## Features

### Command Routing

The `Console` class discovers commands from `config/console.php` and provides four built-in commands: `list`, `help`, `about` and `dump-config` (a template of a configuration file with the options of a command). The default command (when none is specified) is `list`.
Commands can optionally be grouped in titled sections (`sections` instead of `commands` in `config/console.php`), which the `list` command displays grouped; see [Console Classes](doc/Console.md#configuration-files).

### Argument and Option Parsing

Commands support positional arguments, value options (`--output dir`), and boolean flags (`--verbose` / `--no-verbose`). Specialized types like `InputFile`, `InputDirectory`, and `OutputDirectory` add filesystem validation automatically.
Options and flags are declared in bulk with `loadOptions()` and `loadFlags()` using associative arrays:
```php
$this->loadOptions([
    's|policy' => ['description' => 'Policy', 'default' => 'fixed', 'validValues' => ['none', 'fixed', 'edf']],
    'source'   => ['description' => 'Source file', 'required' => true],
]);
$this->loadFlags([
    'v|verbose' => ['description' => 'Verbose output', 'default' => false],
]);
```
See [Console Classes](doc/Console.md#loadoptions-format) for all fields, and the `type` of an option (`input-directory`,
`output-directory`, `config-file`).

#### Configuration files
An option of type `config-file` (`--config values.toml`) reads the values of the other options from a TOML file, so the
options that are always the same do not have to be written every time. A table with the name of a command has its values
(so one file can serve several commands), the command line has priority over the file, and an option that is required can
be given by the file:

```toml
granularity = 1000       # common to the commands that have this option

[gen-periodic]
count = 20
utilization = 0.8
```
See [ConfigFile](doc/Console.md#configfile).

### Service Container

`Console` extends a PSR-style service container that supports singletons, class registration, aliases, and service providers with `boot()` methods. Access services with the global `app()` helper.

### Configuration

The `Config` facade provides dot-notation access to a nested configuration repository. Load configuration files with `Config::load('database')` and access values with `Config::get('database.host')`.

### Facades

Static proxies to container services:

- `Config` — configuration repository
- `Log` — PSR-3 logger (outputs to STDERR)
- `Cache` — simple key-value cache

### Terminal Output

The `Terminal` class provides static methods for colored output using ANSI escape codes.

### Helper Functions

- `app($abstract)` — resolve a service or get the container
- `env($key, $default)` — read environment variables with type coercion
- `load_config($name)` — load a PHP configuration file

## Utilities
### php-bump-version
Composer installs the program `php-bump-version` in the `vendor/bin` directory of the projects that use this package. It
bumps the semantic version of a project (an application or a library built on this package), from its root directory:

```bash
vendor/bin/php-bump-version                # prints the current version
vendor/bin/php-bump-version --patch        # 1.4.2 -> 1.4.3
vendor/bin/php-bump-version --minor        # 1.4.2 -> 1.5.0
vendor/bin/php-bump-version --major        # 1.4.2 -> 2.0.0
```

The current version is the `"version"` of `composer.json` (it has to be `MAJOR.MINOR.PATCH`). A bump writes the new one
there and, if `config/app.php` exists, updates the `'version'` and the `'date'` of its `'info'` array (the date of
today, as `YYYY-MM-DD`). Only those values change: the rest of the files, with their format and comments, stay as they are.

| Option              | Description                                                                                     |
|---------------------|-------------------------------------------------------------------------------------------------|
| `--major`, `--minor`, `--patch` | The bump level. Only one of them is allowed                                         |
| `--sync`            | Without a bump level: copies the version of `composer.json` to `config/app.php` (the date changes only if the version there was another one) |
| `--date YYYY-MM-DD` | The date to write, instead of today (the date of the time zone of PHP, see `date.timezone`)    |
| `--dry-run`         | Shows what would change, without writing any file                                               |
| `-h`, `--help`      | Shows the help                                                                                  |

The files are not written if there is an error. `config/app.php` is read as PHP code (its tokens), so a `'version'` or
a `'date'` that is not a plain string literal is reported and left as it is.

## Documentation

Detailed documentation is available in the `doc/` directory:

- [Console Classes](doc/Console.md) — all classes in the Console namespace
- [Helper Functions](doc/Helpers.md) — `app()`, `env()`, `load_config()`
- [Facades](doc/Facades.md) — Cache, Config, and Log facades

## License

See [composer.json](composer.json) for package metadata.

## Tests
The tests use [PHPUnit](https://phpunit.de) (a development dependency, PHP 8.4 or later):
```bash
composer install
composer test            # or: vendor/bin/phpunit
```
When the package is installed in another project, they can be run with the PHPUnit of that project:
`vendor/bin/phpunit -c vendor/ssaezba/appsupport/phpunit.xml.dist`.

They cover the definition of options and flags (`Command::loadOptions()` and `loadFlags()`, positional and
associative formats), the parsing of options and arguments, the command sections, the output of the `help`,
`list` and `about` commands (columns and word wrap), `Terminal` and the configuration `Repository`.
The fatal errors of the library end the process, so the tests replace the logger with one that throws an exception.
