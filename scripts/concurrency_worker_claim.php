<?php
/**
 * Concurrency Worker for Claiming Reward.
 * Runs in an isolated OS process with its own database connection.
 */

define('CLI_SCRIPT', true);
require('/var/www/html/config.php');
require_once($CFG->libdir . '/clilib.php');

$rewardid = (int) ($argv[1] ?? 0);
$userid = (int) ($argv[2] ?? 0);
$courseid = (int) ($argv[3] ?? 0);
$barrierfile = $argv[4] ?? '';
$logfile = $argv[5] ?? '';

if (!$rewardid || !$userid || !$courseid || !$barrierfile || !$logfile) {
    fwrite(STDERR, "Missing worker arguments\n");
    exit(1);
}

// 1. Wait for barrier release (spin wait with usleep)
$timeout = microtime(true) + 10.0;
while (!file_exists($barrierfile)) {
    usleep(1000); // 1ms
    if (microtime(true) > $timeout) {
        file_put_contents($logfile, json_encode(['status' => 'timeout']));
        exit(1);
    }
}

// 2. Execute claim_reward with independent DB connection
$gateway = new \local_testcase_exchange\db_gateway();
$service = new \local_testcase_exchange\testcase_service($gateway);

$starttime = microtime(true);
$res = $service->claim_reward($rewardid, $userid, $courseid);
$duration = microtime(true) - $starttime;

file_put_contents($logfile, json_encode([
    'pid' => getmypid(),
    'result' => $res,
    'duration_ms' => round($duration * 1000, 2),
    'timestamp' => microtime(true),
]));
exit(0);
