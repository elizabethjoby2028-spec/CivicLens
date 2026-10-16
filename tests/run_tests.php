<?php
/**
 * CivicLens — Test Runner CLI Entrypoint
 * Usage: php tests/run_tests.php
 */

require_once __DIR__ . '/CivicLensTest.php';

$suite = new CivicLensTest();
$suite->runAll();
