<?php
define('CLI_SCRIPT', true);
require_once('/var/www/html/config.php');
require_once('/var/www/html/lib/questionlib.php');
require_once('/var/www/html/mod/quiz/locallib.php');

global $DB;

// 1. Thêm bản ghi question_coderunner_tests cho câu hỏi 119 và 120
foreach ([119, 120] as $qid) {
    $existing = $DB->get_record('question_coderunner_tests', ['questionid' => $qid]);
    if (!$existing) {
        $t = new stdClass();
        $t->questionid = $qid;
        $t->testtype = null;
        $t->testcode = null;
        $t->stdin = null;
        $t->expected = null;
        $t->extra = null;
        $t->useasexample = 0;
        $t->display = 'SHOW';
        $t->hiderestiffail = 0;
        $t->mark = 1.0;
        $DB->insert_record('question_coderunner_tests', $t);
        echo "Đã thêm question_coderunner_tests cho câu hỏi {$qid}\n";
    }
}

// 2. Dọn các slot cũ của Quiz 2 nếu có
$quiz = $DB->get_record('quiz', ['id' => 2]);
$slots = $DB->get_records('quiz_slots', ['quizid' => 2]);
foreach ($slots as $s) {
    $DB->delete_records('question_references', ['component' => 'mod_quiz', 'questionarea' => 'slot', 'itemid' => $s->id]);
    $DB->delete_records('quiz_slots', ['id' => $s->id]);
}

// 3. Sử dụng hàm chuẩn quiz_add_quiz_question của Moodle để thêm bài 119 và 120
quiz_add_quiz_question(119, $quiz, 1, 1.0);
quiz_add_quiz_question(120, $quiz, 1, 1.0);
quiz_update_sumgrades($quiz);

// 4. Thêm feedback mặc định cho Quiz
if (!$DB->record_exists('quiz_feedback', ['quizid' => 2])) {
    $fb = new stdClass();
    $fb->quizid = 2;
    $fb->feedbacktext = '';
    $fb->feedbacktextformat = FORMAT_HTML;
    $fb->mingrade = 0;
    $fb->maxgrade = 11;
    $DB->insert_record('quiz_feedback', $fb);
}

echo "=== GẮN BÀI TẬP VÀO QUIZ THÀNH CÔNG BẰNG QUIZ_ADD_QUIZ_QUESTION ===\n";
