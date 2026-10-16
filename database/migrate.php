<?php
/**
 * CivicLens — Database Initializer
 */

require_once __DIR__ . '/../config.php';

$host = env('DB_HOST', 'localhost');
$port = env('DB_PORT', '3306');
$user = env('DB_USER', 'root');
$pass = env('DB_PASS', '');

try {
    echo "Connecting to MySQL server at {$host}:{$port}...\n";
    $pdo = new PDO("mysql:host={$host};port={$port}", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    echo "Connected successfully.\n";
    $pdo->exec("DROP DATABASE IF EXISTS `civiclens_db`");
    echo "Reset civiclens_db.\n";

    echo "Importing database/schema.sql...\n";
    $schema = file_get_contents(__DIR__ . '/schema.sql');
    $pdo->exec($schema);
    echo "Schema imported successfully.\n";

    echo "Importing database/seed.sql...\n";
    $seed = file_get_contents(__DIR__ . '/seed.sql');
    $pdo->exec($seed);
    echo "Seed data imported successfully.\n";

    echo "All tables created and initialized in database 'civiclens_db'!\n";
} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
