<?php

/**
 * main.php
 * 
 * This is the main include for the app
 * 
 * @since 8.4
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Library
 * 
 */

// try to manage the session as early as possible
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => isset($_SERVER['HTTPS']),
    'httponly' => true,
    'samesite' => 'Lax'
]);

// hold the app path
$appPath = dirname(__FILE__, 2) . '/';

// include our vendor autoloader
include_once $appPath . 'vendor/autoload.php';

// define the primary app path if not already defined
defined('KPTV_PATH') || define('KPTV_PATH', $appPath);

// create our fake alias if it doesn't already exist
if (! class_exists('KPTV')) {

    // redeclare this
    class KPTV extends KPTV_Static {}
}

// hold our full config to avoid repeated lookups
$_config = KPTV::get_full_config();

// setup the database config definitions
$_db = $_config->database ?? new stdClass();

// normalize database settings for sqlite-first operation
if (!isset($_db->driver) || $_db->driver === '') {
    $_db->driver = 'sqlite';
}
if ($_db->driver === 'sqlite') {
    if (!isset($_db->path) || $_db->path === '') {
        $_db->path = '/var/lib/data/kptv.sqlite';
    } elseif (is_dir((string) $_db->path) || str_ends_with((string) $_db->path, '/')) {
        $_db->path = rtrim((string) $_db->path, '/') . '/kptv.sqlite';
    }
}

// configure the cache
\KPT\Cache::configure([
    'path' => KPTV_PATH . '.cache/',
    'prefix' => KPTV::get_cache_prefix(),
    'allowed_backends' => ['array', 'opcache', 'file',], // also: redis, memcached, apcu, yac, mysql, sqlite, shmop, file
]);

// define the app URI
defined('KPTV_URI') || define('KPTV_URI', rtrim((string)($_config->mainuri ?? ''), '/') . '/');
defined('KPT_URI') || define('KPT_URI', rtrim((string)($_config->mainuri ?? ''), '/') . '/');
defined('KPTV_XC_URI') || define('KPTV_XC_URI', $_config->xcuri ?? '');

// define our app name
defined('APP_NAME') || define('APP_NAME', $_config->appname ?? 'KPTV');

// try to manage the session as early as possible
KPTV::manage_the_session();

// setup our environment
$_debug = (bool)($_config->debug_app ?? false);
defined('KPTV_DEBUG') || define('KPTV_DEBUG', $_debug);

// if we are debugging
if ($_debug) {

    // force PHP to render our errors
    @ini_set('display_errors', 1);
    @ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {

    // force php to NOT render our errors
    @ini_set('display_errors', 0);
    error_reporting(0);
}

// initialize the logger
new \KPT\Logger(KPTV_DEBUG);

// hold our constant definitions
defined('DB_DRIVER') || define('DB_DRIVER', (string)($_db->driver ?? 'sqlite'));
defined('DB_SERVER') || define('DB_SERVER', (string)($_db->server ?? ''));
defined('DB_SCHEMA') || define('DB_SCHEMA', (string)($_db->schema ?? ''));
defined('DB_PATH') || define('DB_PATH', (string)($_db->path ?? ''));
defined('DB_USER') || define('DB_USER', (string)($_db->username ?? ''));
defined('DB_PASS') || define('DB_PASS', (string)($_db->password ?? ''));
defined('TBL_PREFIX') || define('TBL_PREFIX', (string)($_db->tbl_prefix ?? ''));

// hold the global cli args
global $argv;

// make sure this only runs if called from a web browser
if (
    php_sapi_name() !== 'cli' &&
    (! isset($argv) ||
        ! is_array($argv) ||
        empty($argv) ||
        realpath($argv[0]) !==
        realpath(__FILE__))
) {

    // hold the routes path
    $routes_path = KPTV_PATH . 'views/routes.php';

    // make sure the routes file exists
    if (file_exists($routes_path)) {

        // Initialize the router with explicit base path
        $router = new \KPT\Router('');

        // enable the redis rate limiter
        $router->enableRateLimiter();

        // load the route definitions
        include_once $routes_path;

        // Dispatch the router
        try {
            if (KPTV_DEBUG) {
                // Debug - check if routes are registered
                error_log("Registered routes: " . print_r($router->getRoutes(), true));
            }
            $router->dispatch();

            // whoopsie...
        } catch (Throwable $e) {

            // log the error then throw a json response
            \KPT\Logger::error("Router error: " . $e->getMessage());
            header('Content-Type: application/json');
            http_response_code($e->getCode() >= 400 ? $e->getCode() : 500);
            echo json_encode([
                'status' => 'error',
                'message' => $e->getMessage(),
                'code' => $e->getCode()
            ]);
        }
    }
}
