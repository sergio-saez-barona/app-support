# Helper Functions

The file `src/Support/helpers.php` defines global helper functions that are autoloaded via Composer. Each function is
wrapped in a `function_exists` check to allow overriding.

---

## app()

```php
function app(string $abstract = null): Console|object
```

Returns the global application container or resolves a registered service.

### Parameters

| Parameter   | Type           | Default | Description                           |
|-------------|----------------|---------|---------------------------------------|
| `$abstract` | `string\|null` | `null`  | The service name or class to resolve. |

### Behavior

- **Without arguments** — Returns the `Console` singleton (the global container instance).
- **With an argument** — Resolves and returns the service registered under that name.

### Examples

```php
// Get the application instance
$console = app();

// Resolve a registered service
$config = app('config');

// Resolve a named class
$repo = app('repository');
```

---

## env()

```php
function env(string $key, mixed $default = null): mixed
```

Reads an environment variable from `$_ENV` with type coercion for common string values.

### Parameters

| Parameter  | Type     | Default | Description                                |
|------------|----------|---------|--------------------------------------------|
| `$key`     | `string` | —       | The environment variable name.             |
| `$default` | `mixed`  | `null`  | Value returned if the variable is not set. |

### Type Coercion

The following string values are automatically converted:

| String value           | Returned as         |
|------------------------|---------------------|
| `"true"`, `"(true)"`   | `true`              |
| `"false"`, `"(false)"` | `false`             |
| `"empty"`, `"(empty)"` | `""` (empty string) |
| `"null"`, `"(null)"`   | `null`              |

Quoted strings (e.g. `"\"hello\""`) have their surrounding double quotes stripped.

All comparisons are case-insensitive.

### Examples

```php
$debug = env('APP_DEBUG', false);       // returns bool true if APP_DEBUG=true
$name  = env('APP_NAME', 'MyApp');      // returns the string value or default
$db    = env('DB_HOST');                 // returns null if not set
```

---

## load_config()

```php
function load_config(string $config): mixed
```

Loads and returns a PHP configuration file.

### Parameters

| Parameter | Type     | Description                                        |
|-----------|----------|----------------------------------------------------|
| `$config` | `string` | The configuration name (without `.php` extension). |

### Behavior

1. Reads the config path from `app.config.path` in the global config. Falls back to `<basePath>/config`.
2. Requires the file `<configPath>/<config>.php` and returns its value.
3. Throws `LogicException` if the file does not exist.

### Examples

```php
// Loads config/app.php and returns its array
$appConfig = load_config('app');

// Loads config/database.php
$dbConfig = load_config('database');
```

The configuration file is expected to return a value (typically an array):

```php
<?php
// config/app.php
return [
    'info' => [
        'name'    => 'My Application',
        'version' => '1.0.0',
    ],
];
```
