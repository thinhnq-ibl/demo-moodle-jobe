<?php
/**
 * Concurrency Worker for Submitting Contribution.
 * Runs in an isolated OS process with its own database connection.
 *
 * @package    local_testcase_exchange
 * @copyright  2026 Nguyen Quoc Thinh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);
require('/var/www/html/config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->dirroot . '/local/testcase_exchange/lib.php');

$runid = (int) ($argv[1] ?? 0);
$userid = (int) ($argv[2] ?? 0);
$barrierfile = $argv[3] ?? '';
$logfile = $argv[4] ?? '';

if (!$runid || !$userid || !$barrierfile || !$logfile) {
    fwrite(STDERR, "Missing worker arguments for concurrency_worker_submit\n");
    exit(1);
}

// 1. Wait for barrier release (spin wait with 1ms interval)
$timeout = microtime(true) + 10.0;
while (!file_exists($barrierfile)) {
    usleep(1000);
    if (microtime(true) > $timeout) {
        file_put_contents($logfile, json_encode(['status' => 'timeout', 'pid' => getmypid()]));
        exit(1);
    }
}

// 2. Execute submit_contribution with independent DB connection
$service = new \local_testcase_exchange\testcase_service();

$starttime = microtime(true);
$error = null;
$result = null;

try {
    $result = $service->submit_contribution($runid, $userid);
} catch (\Throwable $e) {
    $error = $e->getMessage();
}
$duration = microtime(true) - $starttime;

file_put_contents($logfile, json_encode([
    'pid' => getmypid(),
    'userid' => $userid,
    'runid' => $runid,
    'result' => $result,
    'error' => $error,
    'duration_ms' => round($duration * 1000, 2),
    'timestamp' => microtime(true),
]));
exit(0);
