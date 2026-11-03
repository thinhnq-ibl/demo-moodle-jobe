<?php
define('CLI_SCRIPT', true);
require_once('/var/www/html/config.php');
require_once('/var/www/html/mod/quiz/lib.php');

global $DB;

$now = time();
$three_months_later = $now + (92 * 86400); // 92 days ~ 3 months

$quizzes = $DB->get_records_select('quiz', 'timeclose > 0');

echo "Found " . count($quizzes) . " quizzes with timeclose > 0.\n";
echo "Current server time: " . date('Y-m-d H:i:s', $now) . "\n";
echo "New closing time:    " . date('Y-m-d H:i:s', $three_months_later) . "\n\n";

$updated_count = 0;
foreach ($quizzes as $quiz) {
    $old_close = $quiz->timeclose;
    $quiz->timeclose = $three_months_later;
    $quiz->timemodified = $now;
    $DB->update_record('quiz', $quiz);
    quiz_update_events($quiz);
    $updated_count++;
}

echo "Successfully updated and refreshed events for {$updated_count} quizzes.\n";

echo "Purging Moodle caches...\n";
purge_all_caches();
echo "All caches purged successfully.\n";
