<?php
/**
 * CivicLens — Core Configuration & Bootstrap
 */

// Define root path
if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', __DIR__);
}

// Simple .env Loader
if (!function_exists('load_env')) {
    function load_env($file_path) {
        if (!file_exists($file_path)) {
            return;
        }
        $lines = file($file_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || strpos($line, '#') === 0) {
                continue;
            }
            if (strpos($line, '=') !== false) {
                list($name, $value) = explode('=', $line, 2);
                $name = trim($name);
                $value = trim($value);
                // Remove surrounding quotes
                if (preg_match('/^(["\'])(.*)\1$/', $value, $matches)) {
                    $value = $matches[2];
                }
                if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
                    putenv("{$name}={$value}");
                    $_ENV[$name] = $value;
                    $_SERVER[$name] = $value;
                }
            }
        }
    }
}

// Helper to get env variable with fallback
if (!function_exists('env')) {
    function env($key, $default = null) {
        $value = getenv($key);
        if ($value === false) {
            $value = isset($_ENV[$key]) ? $_ENV[$key] : (isset($_SERVER[$key]) ? $_SERVER[$key] : $default);
        }
        if ($value === 'true' || $value === '(true)') return true;
        if ($value === 'false' || $value === '(false)') return false;
        if ($value === 'null' || $value === '(null)') return null;
        if ($value === 'empty' || $value === '(empty)') return '';
        return $value;
    }
}

// Load environment variables
load_env(ROOT_PATH . '/.env');

// Set Application Constants
define('APP_NAME', env('APP_NAME', 'CivicLens'));
define('APP_TAGLINE', env('APP_TAGLINE', 'Report. Track. Resolve.'));
define('APP_ENV', env('APP_ENV', 'development'));
define('APP_DEBUG', (bool)env('APP_DEBUG', true));
$configuredUrl = env('APP_URL', 'http://localhost/CivicLens');
if (isset($_SERVER['HTTP_HOST']) && strpos($configuredUrl, 'localhost') !== false && $_SERVER['HTTP_HOST'] !== 'localhost' && $_SERVER['HTTP_HOST'] !== '127.0.0.1') {
    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https://' : 'http://';
    $configuredUrl = $protocol . $_SERVER['HTTP_HOST'] . '/CivicLens';
}
define('APP_URL', rtrim($configuredUrl, '/'));
define('ANONYMOUS_COMPLAINT_FEE', (float)env('ANONYMOUS_COMPLAINT_FEE', 9.00));
define('AUTHORITY_SUBSCRIPTION_PRICE', (float)env('AUTHORITY_SUBSCRIPTION_PRICE', 5000.00));

// Set Timezone
date_default_timezone_set(env('APP_TIMEZONE', 'Asia/Kolkata'));

// Error handling based on environment
if (APP_DEBUG) {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(0);
}

// Secure Session Initialization
// Secure Session Initialization
if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    // Configure secure session parameters
    ini_set('session.cookie_httponly', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_samesite', 'Lax');
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
        ini_set('session.cookie_secure', '1');
    }
    session_start();
}
