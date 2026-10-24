<?php
/**
 * Zentrale Initialisierung: Autoloader, .env, Fehlerbehandlung, Zeitzone, Helfer.
 * Erzeugt keinen Output und startet keine Session (siehe App\Core\Session::start()).
 */
declare(strict_types=1);

if (defined('APP_ROOT')) {
    return;
}

define('APP_ROOT', dirname(__DIR__));
define('APP_VERSION', '0.1.0-dev');

if (PHP_VERSION_ID < 80100) {
    http_response_code(500);
    exit('event-rms benötigt PHP 8.1 oder neuer (aktuell: ' . PHP_VERSION . ').');
}

spl_autoload_register(static function (string $class): void {
    if (strncmp($class, 'App\\', 4) !== 0) {
        return;
    }
    $file = APP_ROOT . '/src/' . str_replace('\\', '/', substr($class, 4)) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

require APP_ROOT . '/src/helpers.php';

App\Core\Env::load(APP_ROOT . '/.env');

$debug = App\Core\Env::bool('APP_DEBUG');
error_reporting(E_ALL);
ini_set('display_errors', $debug ? '1' : '0');
ini_set('log_errors', '1');

// Vorläufige Zeitzone; nach dem Laden der Settings wird die konfigurierte gesetzt.
date_default_timezone_set('Europe/Zurich');
