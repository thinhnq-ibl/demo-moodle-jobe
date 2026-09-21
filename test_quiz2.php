<?php
define('CLI_SCRIPT', true);
require('/var/www/html/config.php');
require_once('/var/www/html/mod/quiz/locallib.php');

$quiz = $DB->get_record('quiz', ['id' => 2]);
$course = $DB->get_record('course', ['id' => 3]);
$cm = get_coursemodule_from_instance('quiz', 2, 3);
$quizobj = new quiz_settings($quiz, $cm, $course);
$structure = $quizobj->get_structure();

echo "Slots count: " . count($structure->get_slots()) . "\n";
foreach ($structure->get_slots() as $slot) {
    echo "Slot: {$slot->slot}, Question ID: {$slot->questionid}, Name: {$slot->name}\n";
}

// Test start attempt
$user = $DB->get_record('user', ['username' => 'student1']);
try {
    $quba = question_engine::make_questions_usage_by_activity('mod_quiz', $quizobj->get_context());
    $quba->set_preferred_behaviour($quiz->preferredbehaviour);
    echo "OK: Quiz structure and question engine loadable!\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
