<?php
define('CLI_SCRIPT', true);
require_once(__DIR__ . '/config.php');

$q = $DB->get_record('question', ['id' => 119]);
$cropts = $DB->get_record('question_coderunner_options', ['questionid' => 119]);
$tests = $DB->get_records('question_coderunner_tests', ['questionid' => 119]);

echo "Question: " . $q->name . " (type: " . $cropts->coderunnertype . ")\n";
echo "Prototype: " . $cropts->prototypetype . "\n";
echo "Answer:\n" . $cropts->answer . "\n";
echo "Tests:\n";
foreach ($tests as $t) {
    echo " - Testcode: " . $t->testcode . " | Stdin: " . $t->stdin . " | Expected: " . $t->expected . "\n";
}
