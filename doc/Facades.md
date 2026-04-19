# Facades

Facades provide a static interface to services registered in the application container. Under the hood, static method
calls are forwarded to the resolved singleton instance via `__callStatic`.

All facades extend `AppSupport\Support\Facades\Facade`.

---

## How Facades Work

1. Each facade defines a `getFacadeAccessor()` method that returns the container key (typically an interface FQCN or
   alias).
2. When a static method is called on the facade, `__callStatic` resolves the underlying instance from the container and
   delegates the call.
3. Resolved instances are cached in `Facade::$resolvedInstance` for subsequent calls.

### Base Facade Methods

| Method                               | Description                                            |
|--------------------------------------|--------------------------------------------------------|
| `Facade::swap(object $instance)`     | Replaces the resolved instance behind the facade.      |
| `Facade::getFacadeRoot()`            | Returns the resolved instance.                         |
| `Facade::clearInstance()`            | Clears the cached instance for this facade.            |
| `Facade::clearResolvedInstances()`   | Clears all cached facade instances.                    |
| `Facade::getFacadeApplication()`     | Returns the container instance.                        |
| `Facade::setFacadeApplication($app)` | Sets the container instance (called during bootstrap). |

---

## Cache

`AppSupport\Support\Facades\Cache`

Facade for the `AppSupport\Contracts\CacheInterface` singleton.

Provides a simple key-value cache.

### Available Methods

| Method                                     | Description                                                   |
|--------------------------------------------|---------------------------------------------------------------|
| `Cache::set(string $key, $value)`          | Stores a value in the cache.                                  |
| `Cache::get(string $key, $default = null)` | Retrieves a value from the cache, or `$default` if not found. |
| `Cache::isset(string $key): bool`          | Checks if a key exists in the cache.                          |
| `Cache::unset(string $key)`                | Removes a key from the cache.                                 |
| `Cache::flush()`                           | Clears all cached entries.                                    |

### Example

```php
use AppSupport\Support\Facades\Cache;

Cache::set('user.count', 42);
$count = Cache::get('user.count', 0);

if (Cache::isset('user.count')) {
    Cache::unset('user.count');
}

Cache::flush();
```

> **Note:** The `CacheInterface` singleton must be registered in the container before using this facade. It is not
> registered by default in `Console`; register it in your `app.php` config if needed.

---

## Config

`AppSupport\Support\Facades\Config`

Facade for the `AppSupport\Contracts\Config` singleton (backed by `AppSupport\Config\Repository`).

Provides dot-notation access to configuration values. Registered automatically by `Console` during bootstrap.

### Available Methods

| Method                                               | Description                                                                                   |
|------------------------------------------------------|-----------------------------------------------------------------------------------------------|
| `Config::set(string $key, $value)`                   | Sets a configuration value. Supports dot-notation keys (e.g. `"app.info.name"`).              |
| `Config::get(string $key, $default = null)`          | Retrieves a configuration value. Returns `$default` if not found.                             |
| `Config::isset(string $key): bool`                   | Checks if a configuration key exists.                                                         |
| `Config::unset(string $key)`                         | Removes a configuration key.                                                                  |
| `Config::load(string $config, string $alias = null)` | Loads a configuration file and stores it under the given alias (defaults to the config name). |

### Dot-Notation Keys

The `Repository` class uses `.` as a separator to navigate nested arrays:

```php
Config::set('database.host', 'localhost');
Config::set('database.port', 3306);

$host = Config::get('database.host');           // 'localhost'
$timeout = Config::get('database.timeout', 30); // 30 (default)
```

### Loading Configuration Files

```php
// Loads config/database.php and stores it under the key "database"
Config::load('database');

// Loads config/database.php and stores it under the key "db"
Config::load('database', 'db');
```

### Example

```php
use AppSupport\Support\Facades\Config;

// Set application info
Config::set('app.info.name', 'My CLI Tool');
Config::set('app.info.version', '2.0.0');

// Read it back
$name = Config::get('app.info.name');

// Load an entire config file
$dbConfig = Config::load('database');
```

---

## Log

`AppSupport\Support\Facades\Log`

Facade for the `Psr\Log\LoggerInterface` singleton (backed by `AppSupport\Support\Logger`).

Provides PSR-3 compatible logging to `STDERR` with colored level prefixes. Registered automatically by `Console` during
bootstrap.

### Available Methods

All standard PSR-3 log levels:

| Method                                            | Description                                                     |
|---------------------------------------------------|-----------------------------------------------------------------|
| `Log::emergency($message, array $context = [])`   | System is unusable.                                             |
| `Log::alert($message, array $context = [])`       | Action must be taken immediately.                               |
| `Log::critical($message, $context = [])`          | Critical conditions. **Terminates the process with `exit(1)`.** |
| `Log::error($message, array $context = [])`       | Runtime errors.                                                 |
| `Log::warning($message, array $context = [])`     | Warnings.                                                       |
| `Log::notice($message, array $context = [])`      | Normal but significant events.                                  |
| `Log::info($message, array $context = [])`        | Informational messages.                                         |
| `Log::debug($message, array $context = [])`       | Debug-level messages.                                           |
| `Log::log($level, $message, array $context = [])` | Logs at an arbitrary level.                                     |

Additional methods provided by the `Logger` implementation:

| Method                                      | Description                                                                |
|---------------------------------------------|----------------------------------------------------------------------------|
| `Log::incTab($level = LogLevel::EMERGENCY)` | Increases indentation for subsequent messages at or below the given level. |
| `Log::decTab($level = LogLevel::EMERGENCY)` | Decreases indentation.                                                     |
| `Log::backtrace($options, $limit)`          | Logs a debug backtrace at `ERROR` level.                                   |

### Log Level Behavior

- Messages at `CRITICAL` level or above reset indentation and **terminate the process** with `exit(1)`.
- Messages are only printed if their level is at or below the current log level (default: `DEBUG`, meaning all messages
  are shown).
- Output goes to `STDERR`.

### Level Prefixes

Each message is prefixed with a level indicator:

| Level     | Prefix |
|-----------|--------|
| EMERGENCY | `(!!)` |
| ALERT     | `(AA)` |
| CRITICAL  | `(CC)` |
| ERROR     | `(EE)` |
| WARNING   | `(WW)` |
| NOTICE    | `(NN)` |
| INFO      | `(II)` |
| DEBUG     | `(DD)` |

### Example

```php
use AppSupport\Support\Facades\Log;

Log::info("Processing started");
Log::incTab();
Log::debug("Reading file: input.txt");
Log::decTab();
Log::warning("File is empty");

// This will terminate the process:
Log::critical("Cannot continue without input");
```
