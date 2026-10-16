<?php
/**
 * Database Configuration
 */

return [
    'host'     => env('DB_HOST', 'localhost'),
    'port'     => env('DB_PORT', 3306),
    'database' => env('DB_NAME', 'civiclens_db'),
    'username' => env('DB_USER', 'root'),
    'password' => env('DB_PASS', ''),
    'charset'  => 'utf8mb4',
    'options'  => [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ],
];
