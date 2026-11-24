<?php
/** Shared start-up for the web app and the CLI scripts. */
declare(strict_types=1);

spl_autoload_register(function (string $class): void {
    foreach (['config', 'models', 'services', 'repositories', 'controllers'] as $dir) {
        $file = dirname(__DIR__) . "/$dir/$class.php";
        if (is_file($file)) {
            require $file;
            return;
        }
    }
});

require __DIR__ . '/helpers.php';
Env::load(dirname(__DIR__) . '/.env');
date_default_timezone_set(Env::get('APP_TIMEZONE', 'UTC'));

// Never show PHP errors to users; log them server-side instead.
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);
if (!is_dir(Config::logsDir())) {
    @mkdir(Config::logsDir(), 0700, true);
}
ini_set('error_log', Config::logsDir() . '/php_errors.log');
