# AppSupport

A lightweight PHP library for building command-line applications. It provides command routing, argument and option parsing, a service container, configuration management, facades, and colored terminal output.

## Requirements

- PHP >= 8.0
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

The `Console` class discovers commands from `config/console.php` and provides three built-in commands: `list`, `help`, and `about`. The default command (when none is specified) is `list`.

### Argument and Option Parsing

Commands support positional arguments, value options (`--output dir`), and boolean flags (`--verbose` / `--no-verbose`). Specialized types like `InputFile`, `InputDirectory`, and `OutputDirectory` add filesystem validation automatically.

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

## Documentation

Detailed documentation is available in the `doc/` directory:

- [Console Classes](doc/Console.md) — all classes in the Console namespace
- [Helper Functions](doc/Helpers.md) — `app()`, `env()`, `load_config()`
- [Facades](doc/Facades.md) — Cache, Config, and Log facades

## License

See [composer.json](composer.json) for package metadata.
