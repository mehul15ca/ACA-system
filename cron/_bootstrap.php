<?php
declare(strict_types=1);

/**
 * Cron bootstrap (CLI only)
 * Usage (first line in every cron script):
 *   require_once __DIR__ . '/_bootstrap.php';
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit("403 Forbidden\n");
}

// Ensure we run from project root context safely
$projectRoot = realpath(__DIR__ . '/..');
if ($projectRoot === false) {
    fwrite(STDERR, "Project root not found.\n");
    exit(1);
}

require_once $projectRoot . '/config.php';

// Optional: enforce timezone
date_default_timezone_set('America/Toronto');

// Hard stop if DB not available
if (!isset($conn) || !($conn instanceof mysqli)) {
    fwrite(STDERR, "Database connection not available.\n");
    exit(1);
}

// Basic hardening for cron runtime
mysqli_report(MYSQLI_REPORT_OFF);
