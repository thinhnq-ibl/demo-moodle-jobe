<?php
/**
 * Concurrency Worker for Reviewing & Granting Reward.
 * Runs in an isolated OS process with its own database connection.
 */

define('CLI_SCRIPT', true);
require('/var/www/html/config.php');
require_once($CFG->libdir . '/clilib.php');

$contribid = (int) ($argv[1] ?? 0);
$reviewerid = (int) ($argv[2] ?? 0);
$courseid = (int) ($argv[3] ?? 0);
$barrierfile = $argv[4] ?? '';
$logfile = $argv[5] ?? '';

if (!$contribid || !$reviewerid || !$courseid || !$barrierfile || !$logfile) {
    fwrite(STDERR, "Missing worker arguments\n");
    exit(1);
}

// Wait for barrier release
$timeout = microtime(true) + 10.0;
while (!file_exists($barrierfile)) {
    usleep(1000);
    if (microtime(true) > $timeout) {
        file_put_contents($logfile, json_encode(['status' => 'timeout']));
        exit(1);
    }
}

$gateway = new \local_testcase_exchange\db_gateway();
$service = new \local_testcase_exchange\testcase_service($gateway);

$starttime = microtime(true);
$error = null;
$success = false;
try {
    $service->review($contribid, $courseid, $reviewerid, 'approved', 'Concurrent approval', 5);
    $success = true;
} catch (\Throwable $e) {
    $error = $e->getMessage();
}
$duration = microtime(true) - $starttime;

file_put_contents($logfile, json_encode([
    'pid' => getmypid(),
    'success' => $success,
    'error' => $error,
    'duration_ms' => round($duration * 1000, 2),
    'timestamp' => microtime(true),
]));
exit(0);
