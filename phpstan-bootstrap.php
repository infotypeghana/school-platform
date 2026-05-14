<?php

/**
 * PHPStan bootstrap: teach the analyser about IoC bindings that are always
 * present at runtime but invisible to static analysis.
 *
 * Executed once by PHPStan before analysis begins.
 * NOT loaded during normal HTTP requests or queue workers.
 */

// Ensure Laravel is bootstrapped so Larastan can resolve facades and models.
// Uses APP_ENV=testing so no production side-effects occur.
if (! defined('LARAVEL_START')) {
    define('LARAVEL_START', microtime(true));
}

// Only bootstrap if running inside PHPStan (avoids double-bootstrap in tests)
if (! isset($app)) {
    $app = require __DIR__ . '/bootstrap/app.php';
    $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
}
