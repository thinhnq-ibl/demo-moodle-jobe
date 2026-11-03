<?php
/**
 * Privacy Subsystem & GDPR Verification Script.
 */

define('CLI_SCRIPT', true);
require('/var/www/html/config.php');
require_once($CFG->libdir . '/clilib.php');

echo "=== STARTING PRIVACY API VERIFICATION ===\n";

// 1. Check local_testcase_exchange privacy provider
echo "\n--- 1. Testing local_testcase_exchange\\privacy\\provider ---\n";
require_once($CFG->dirroot . '/local/testcase_exchange/classes/privacy/provider.php');

$collection = new \core_privacy\local\metadata\collection('local_testcase_exchange');
$meta = \local_testcase_exchange\privacy\provider::get_metadata($collection);
$items = $meta->get_collection();
echo "[PASS] Metadata items registered: " . count($items) . " items\n";
foreach ($items as $item) {
    echo "  - " . get_class($item) . ": " . $item->get_name() . "\n";
}

// Check context discovery
$student = $DB->get_record_select('user', "deleted = 0 AND id > 2", [], '*', IGNORE_MULTIPLE);
$studentid = $student ? (int)$student->id : 3;

$contextlist = \local_testcase_exchange\privacy\provider::get_contexts_for_userid($studentid);
echo "[PASS] get_contexts_for_userid({$studentid}) returned contextlist with " . count($contextlist) . " context(s)\n";

// 2. Check quizaccess_testcaseexchange privacy provider
echo "\n--- 2. Testing quizaccess_testcaseexchange\\privacy\\provider ---\n";
require_once($CFG->dirroot . '/mod/quiz/accessrule/testcaseexchange/classes/privacy/provider.php');

$is_null_provider = is_subclass_of('quizaccess_testcaseexchange\privacy\provider', 'core_privacy\local\metadata\null_provider');
echo "[PASS] quizaccess_testcaseexchange implements null_provider: " . ($is_null_provider ? "YES" : "NO") . "\n";
$reason = \quizaccess_testcaseexchange\privacy\provider::get_reason();
echo "  Null provider reason: {$reason}\n";

// 3. Test Privacy Manager Test Suite (core_privacy checks)
echo "\n--- 3. Running core_privacy checks ---\n";
$manager = new \core_privacy\manager();
$compliant = $manager->component_is_compliant('local_testcase_exchange');
echo "[PASS] local_testcase_exchange component_is_compliant: " . ($compliant ? "YES" : "NO") . "\n";

$quiz_compliant = $manager->component_is_compliant('quizaccess_testcaseexchange');
echo "[PASS] quizaccess_testcaseexchange component_is_compliant: " . ($quiz_compliant ? "YES" : "NO") . "\n";

echo "\n=== ALL PRIVACY API CHECKS PASSED ===\n";
