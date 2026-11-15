<?php

// The autoloader of this package, or the one of the project that has it in its vendor directory
$autoloaders = [__DIR__ . '/../vendor/autoload.php', __DIR__ . '/../../../autoload.php'];
foreach ($autoloaders as $autoloader) {
    if (file_exists($autoloader)) {
        require $autoloader;
        // Used by the tests that run PHP in another process
        define('APP_SUPPORT_TESTS_AUTOLOAD', realpath($autoloader));
        break;
    }
}
if (!class_exists(\AppSupport\Console\Console::class)) {
    fwrite(STDERR, "Autoloader not found. Run 'composer install' first.\n");
    exit(1);
}

// The classes of the tests, which a project that includes this package does not autoload
spl_autoload_register(function (string $class): void {
    $prefix = 'AppSupport\\Tests\\';
    if (str_starts_with($class, $prefix)) {
        $file = __DIR__ . '/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        if (file_exists($file)) {
            require $file;
        }
    }
});
