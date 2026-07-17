<?php

declare(strict_types=1);

/**
 * Minimal PSR-4 autoloader for environments without Composer.
 * Prefer `require 'vendor/autoload.php'` when installed via Composer.
 */
require __DIR__ . '/src/polyfills.php';

spl_autoload_register(function ($class) {
    $prefix = 'WebFunction\\';

    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $path = __DIR__ . '/src/' . str_replace('\\', '/', $relative) . '.php';

    if (is_file($path)) {
        require $path;
    }
});
