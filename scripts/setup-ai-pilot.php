<?php
// Development fixture for the local Docker Moodle site. Not distributed with the plugin.

define('CLI_SCRIPT', true);

require_once('/var/www/html/config.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/lib/questionlib.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');

/** Create or return the AI pilot course. */
function local_tce_pilot_course(): stdClass {
    global $DB;

    $course = $DB->get_record('course', ['shortname' => 'AI-PY-PILOT']);
    if ($course) {
        return $course;
    }
    $data = (object) [
        'fullname' => 'Python cơ bản cho AI — Pilot đóng góp',
        'shortname' => 'AI-PY-PILOT',
        'category' => 1,
        'format' => 'topics',
        'numsections' => 4,
        'summary' => '<p>Ba Quiz Python cơ bản và một hoạt động đặt câu hỏi cho bài đọc về lịch sử AI.</p>',
        'summaryformat' => FORMAT_HTML,
        'visible' => 1,
    ];
    return create_course($data);
}

/** Enrol the two fixture students. */
function local_tce_pilot_enrol_students(stdClass $course): void {
    global $DB;

    $plugin = enrol_get_plugin('manual');
    $instances = enrol_get_instances($course->id, true);
    $manual = null;
    foreach ($instances as $instance) {
        if ($instance->enrol === 'manual') {
            $manual = $instance;
            break;
        }
    }
    if (!$manual) {
        throw new coding_exception('Manual enrolment is not available in the pilot course.');
    }
    $studentrole = $DB->get_record('role', ['shortname' => 'student'], '*', MUST_EXIST);
    foreach (['student1', 'student2'] as $username) {
        $user = $DB->get_record('user', ['username' => $username], '*', MUST_EXIST);
        $plugin->enrol_user($manual, $user->id, $studentrole->id);
    }
}

/** Return the pilot question category. */
function local_tce_pilot_category(context_course $context): stdClass {
    global $DB;

    $category = $DB->get_record('question_categories', [
        'contextid' => $context->id,
        'name' => 'AI Python pilot',
    ]);
    if ($category) {
        return $category;
    }
    $id = $DB->insert_record('question_categories', (object) [
        'name' => 'AI Python pilot',
        'contextid' => $context->id,
        'info' => 'CodeRunner questions for the AI Python pilot.',
        'infoformat' => FORMAT_HTML,
        'stamp' => make_unique_id_code(),
        'parent' => 0,
        'sortorder' => 1,
        'idnumber' => null,
    ]);
    return $DB->get_record('question_categories', ['id' => $id], '*', MUST_EXIST);
}

/** Create a CodeRunner question by copying safe Python defaults from the built-in prototype. */
function local_tce_pilot_question(stdClass $category, array $definition): int {
    global $DB;

    $existing = $DB->get_record_sql(
        'SELECT q.*
           FROM {question} q
           JOIN {question_versions} qv ON qv.questionid = q.id
           JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
          WHERE qbe.idnumber = :idnumber
       ORDER BY qv.version DESC',
        ['idnumber' => $definition['idnumber']],
        IGNORE_MULTIPLE
    );
    if ($existing) {
        return (int) $existing->id;
    }
    $adminid = 2;
    $now = time();
    $questionid = $DB->insert_record('question', (object) [
        'parent' => 0,
        'name' => $definition['name'],
        'questiontext' => $definition['text'],
        'questiontextformat' => FORMAT_HTML,
        'generalfeedback' => $definition['feedback'],
        'generalfeedbackformat' => FORMAT_HTML,
        'defaultmark' => 1.0,
        'penalty' => 0.0,
        'qtype' => 'coderunner',
        'length' => 1,
        'stamp' => make_unique_id_code(),
        'timecreated' => $now,
        'timemodified' => $now,
        'createdby' => $adminid,
        'modifiedby' => $adminid,
    ]);
    $entryid = $DB->insert_record('question_bank_entries', (object) [
        'questioncategoryid' => $category->id,
        'idnumber' => $definition['idnumber'],
        'ownerid' => $adminid,
    ]);
    $DB->insert_record('question_versions', (object) [
        'questionbankentryid' => $entryid,
        'version' => 1,
        'questionid' => $questionid,
        'status' => 'ready',
    ]);

    $prototype = $DB->get_record('question_coderunner_options', ['questionid' => 16], '*', MUST_EXIST);
    unset($prototype->id);
    $prototype->questionid = $questionid;
    $prototype->coderunnertype = 'python3';
    $prototype->prototypetype = 0;
    $prototype->answer = $definition['answer'];
    $prototype->answerpreload = $definition['preload'];
    $prototype->validateonsave = 0;
    $prototype->answerboxlines = 12;
    $prototype->displayfeedback = 1;
    $DB->insert_record('question_coderunner_options', $prototype);

    foreach ($definition['tests'] as $index => $test) {
        $DB->insert_record('question_coderunner_tests', (object) [
            'questionid' => $questionid,
            'testtype' => 0,
            'testcode' => '',
            'stdin' => $test[0],
            'expected' => $test[1],
            'extra' => '',
            'useasexample' => $index < 2 ? 1 : 0,
            'display' => $index < 2 ? 'SHOW' : 'HIDE',
            'hiderestiffail' => 0,
            'mark' => 1.0,
        ]);
    }
    return (int) $questionid;
}

/** Create a one-question Quiz and attach the question bank entry. */
function local_tce_pilot_quiz(stdClass $course, int $section, array $definition): stdClass {
    global $DB;

    $quiz = $DB->get_record('quiz', ['course' => $course->id, 'name' => $definition['quizname']]);
    if (!$quiz) {
        $quizid = $DB->insert_record('quiz', (object) [
            'course' => $course->id,
            'name' => $definition['quizname'],
            'intro' => $definition['intro'],
            'introformat' => FORMAT_HTML,
            'timeopen' => 0,
            'timeclose' => 0,
            'timelimit' => 0,
            'overduehandling' => 'autosubmit',
            'graceperiod' => 0,
            'preferredbehaviour' => 'adaptive',
            'attempts' => 0,
            'grademethod' => QUIZ_GRADEHIGHEST,
            'decimalpoints' => 2,
            'questiondecimalpoints' => -1,
            'reviewattempt' => 0x10010,
            'reviewcorrectness' => 0x10010,
            'reviewmarks' => 0x10010,
            'reviewspecificfeedback' => 0,
            'reviewgeneralfeedback' => 0x10000,
            'reviewrightanswer' => 0,
            'reviewoverallfeedback' => 0,
            'questionsperpage' => 1,
            'navmethod' => 'free',
            'shuffleanswers' => 0,
            'sumgrades' => 1.0,
            'grade' => 10.0,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
        $DB->insert_record('quiz_sections', (object) [
            'quizid' => $quizid,
            'firstslot' => 1,
            'heading' => '',
            'shufflequestions' => 0,
        ]);
        $module = $DB->get_record('modules', ['name' => 'quiz'], '*', MUST_EXIST);
        $cmid = add_course_module((object) [
            'course' => $course->id,
            'module' => $module->id,
            'instance' => $quizid,
            'section' => $section,
            'added' => time(),
            'visible' => 1,
        ]);
        course_add_cm_to_section($course, $cmid, $section);
        $quiz = $DB->get_record('quiz', ['id' => $quizid], '*', MUST_EXIST);
    }
    $cm = get_coursemodule_from_instance('quiz', $quiz->id, $course->id, false, MUST_EXIST);
    $quizcontext = context_module::instance($cm->id);
    $slot = $DB->get_record('quiz_slots', ['quizid' => $quiz->id, 'slot' => 1]);
    if (!$slot) {
        $slotid = $DB->insert_record('quiz_slots', (object) [
            'slot' => 1,
            'quizid' => $quiz->id,
            'page' => 1,
            'displaynumber' => '1',
            'requireprevious' => 0,
            'maxmark' => 1.0,
        ]);
        $slot = $DB->get_record('quiz_slots', ['id' => $slotid], '*', MUST_EXIST);
    }
    $version = $DB->get_record('question_versions', ['questionid' => $definition['questionid']], '*', MUST_EXIST);
    $reference = $DB->get_record('question_references', [
        'component' => 'mod_quiz',
        'questionarea' => 'slot',
        'itemid' => $slot->id,
    ]);
    if (!$reference) {
        $DB->insert_record('question_references', (object) [
            'usingcontextid' => $quizcontext->id,
            'component' => 'mod_quiz',
            'questionarea' => 'slot',
            'itemid' => $slot->id,
            'questionbankentryid' => $version->questionbankentryid,
            'version' => null,
        ]);
    } else if ((int) $reference->usingcontextid !== (int) $quizcontext->id
            || (int) $reference->questionbankentryid !== (int) $version->questionbankentryid) {
        $reference->usingcontextid = $quizcontext->id;
        $reference->questionbankentryid = $version->questionbankentryid;
        $DB->update_record('question_references', $reference);
    }
    return $quiz;
}

/** Create a Q&A forum and teacher discussion for the reading activity. */
function local_tce_pilot_forum(stdClass $course, int $section): stdClass {
    global $DB;

    $name = 'Bài đọc: Alan Turing và những nền móng của AI';
    $forum = $DB->get_record('forum', ['course' => $course->id, 'name' => $name]);
    if ($forum) {
        $cm = get_coursemodule_from_instance('forum', $forum->id, $course->id, false, MUST_EXIST);
        context_module::instance($cm->id);
        return $forum;
    }
    $forumid = $DB->insert_record('forum', (object) [
        'course' => $course->id,
        'type' => 'qanda',
        'name' => $name,
        'intro' => '<p>Đọc đoạn văn trong discussion. Mỗi sinh viên đăng một câu hỏi mở trước khi đọc câu hỏi của bạn khác.</p>',
        'introformat' => FORMAT_HTML,
        'assessed' => 0,
        'scale' => 0,
        'forcesubscribe' => 0,
        'trackingtype' => 1,
        'rsstype' => 0,
        'rssarticles' => 0,
        'timemodified' => time(),
        'warnafter' => 0,
        'blockafter' => 0,
        'blockperiod' => 0,
        'completiondiscussions' => 0,
        'completionreplies' => 1,
        'completionposts' => 1,
    ]);
    $module = $DB->get_record('modules', ['name' => 'forum'], '*', MUST_EXIST);
    $cmid = add_course_module((object) [
        'course' => $course->id,
        'module' => $module->id,
        'instance' => $forumid,
        'section' => $section,
        'added' => time(),
        'visible' => 1,
        'completion' => COMPLETION_TRACKING_AUTOMATIC,
    ]);
    course_add_cm_to_section($course, $cmid, $section);
    context_module::instance($cmid);
    $discussionid = $DB->insert_record('forum_discussions', (object) [
        'course' => $course->id,
        'forum' => $forumid,
        'name' => 'Đọc và đặt một câu hỏi có chiều sâu',
        'firstpost' => 0,
        'userid' => 2,
        'groupid' => -1,
        'assessed' => 0,
        'timemodified' => time(),
        'usermodified' => 2,
        'timestart' => 0,
        'timeend' => 0,
        'pinned' => 1,
    ]);
    $message = '<p><strong>Alan Turing (1912–1954)</strong> là nhà toán học người Anh có ảnh hưởng lớn đến khoa học máy tính và AI. Ông mô tả mô hình máy Turing, góp phần đặt nền móng cho khái niệm thuật toán và máy tính đa dụng. Trong Thế chiến II, công việc giải mã của ông tại Bletchley Park hỗ trợ phe Đồng Minh. Năm 1950, bài báo “Computing Machinery and Intelligence” đặt câu hỏi liệu máy móc có thể suy nghĩ và đề xuất phép thử về sau được gọi là Turing Test. Di sản của ông cũng nhắc chúng ta rằng tiến bộ công nghệ luôn gắn với trách nhiệm xã hội và cách xã hội đối xử với con người.</p><p><strong>Nhiệm vụ:</strong> Đăng một câu hỏi mở, không thể trả lời chỉ bằng “có/không”. Câu hỏi phải liên hệ ít nhất hai ý trong đoạn văn. Bạn chỉ xem câu hỏi của bạn học sau khi đã đăng câu hỏi của mình.</p>';
    $postid = $DB->insert_record('forum_posts', (object) [
        'discussion' => $discussionid,
        'parent' => 0,
        'userid' => 2,
        'created' => time(),
        'modified' => time(),
        'mailed' => 0,
        'subject' => 'Alan Turing: kỹ thuật, trí tuệ và trách nhiệm xã hội',
        'message' => $message,
        'messageformat' => FORMAT_HTML,
        'messagetrust' => 0,
        'attachment' => 0,
        'totalscore' => 0,
        'mailnow' => 0,
        'deleted' => 0,
        'privatereplyto' => 0,
        'wordcount' => count_words(strip_tags($message)),
        'charcount' => core_text::strlen(strip_tags($message)),
    ]);
    $DB->set_field('forum_discussions', 'firstpost', $postid, ['id' => $discussionid]);
    return $DB->get_record('forum', ['id' => $forumid], '*', MUST_EXIST);
}

$definitions = [
    [
        'idnumber' => 'ai-py-confidence',
        'quizname' => 'Quiz 1 — Chuẩn hóa confidence score',
        'name' => 'Python AI 1: Giới hạn confidence trong khoảng 0 đến 1',
        'intro' => '<p>Thực hành điều kiện và số thực trên một confidence score của mô hình AI.</p>',
        'text' => '<p>Đọc một số thực. Nếu nhỏ hơn 0, dùng 0; nếu lớn hơn 1, dùng 1. In kết quả với đúng hai chữ số thập phân.</p>',
        'feedback' => '<p>Hãy kiểm tra giá trị biên, ngoài miền và số thập phân thông thường.</p>',
        'answer' => "score = float(input())\nscore = max(0.0, min(1.0, score))\nprint(f'{score:.2f}')",
        'preload' => "score = float(input())\n# TODO: giới hạn score trong [0, 1] rồi in hai chữ số thập phân.",
        'tests' => [['0.75', '0.75'], ['-0.2', '0.00'], ['1.4', '1.00'], ['0', '0.00'], ['1', '1.00']],
        'pilot_inputs' => ['0.999', '-10'],
    ],
    [
        'idnumber' => 'ai-py-keyword',
        'quizname' => 'Quiz 2 — Đếm từ khóa AI',
        'name' => 'Python AI 2: Đếm từ khóa AI trong câu',
        'intro' => '<p>Thực hành chuỗi, chuẩn hóa chữ thường và tách từ.</p>',
        'text' => '<p>Đọc một dòng văn bản. Đếm số từ bằng <code>ai</code> không phân biệt hoa thường. Các từ được phân cách bằng khoảng trắng. In số đếm.</p>',
        'feedback' => '<p>Hãy nghĩ đến chuỗi rỗng, nhiều khoảng trắng và cách viết hoa.</p>',
        'answer' => "words = input().lower().split()\nprint(sum(1 for word in words if word == 'ai'))",
        'preload' => "words = input().lower().split()\n# TODO: đếm các từ bằng 'ai'.",
        'tests' => [['AI helps AI', '2'], ['Python for ai', '1'], ['machine learning', '0'], ['', '0'], ['ai AI Ai aI', '4']],
        'pilot_inputs' => ['  AI   ai  ', 'AI-powered AI'],
    ],
    [
        'idnumber' => 'ai-py-accuracy',
        'quizname' => 'Quiz 3 — Tính accuracy của mô hình',
        'name' => 'Python AI 3: Tính phần trăm accuracy',
        'intro' => '<p>Thực hành input nhiều giá trị, phép chia và trường hợp đặc biệt.</p>',
        'text' => '<p>Đọc hai số nguyên <code>correct total</code>. Nếu <code>total = 0</code>, in <code>0.00</code>; ngược lại in phần trăm accuracy với hai chữ số thập phân.</p>',
        'feedback' => '<p>Trường hợp total bằng 0 là testcase biên bắt buộc.</p>',
        'answer' => "correct, total = map(int, input().split())\naccuracy = 0.0 if total == 0 else correct * 100.0 / total\nprint(f'{accuracy:.2f}')",
        'preload' => "correct, total = map(int, input().split())\n# TODO: tính accuracy %, xử lý total = 0.",
        'tests' => [['8 10', '80.00'], ['0 0', '0.00'], ['1 3', '33.33'], ['10 10', '100.00'], ['0 5', '0.00']],
        'pilot_inputs' => ['2 7', '999 1000'],
    ],
];

$course = local_tce_pilot_course();
local_tce_pilot_enrol_students($course);
$context = context_course::instance($course->id);
$category = local_tce_pilot_category($context);
$created = [];
foreach ($definitions as $index => $definition) {
    $questionid = local_tce_pilot_question($category, $definition);
    $definition['questionid'] = $questionid;
    $quiz = local_tce_pilot_quiz($course, $index + 1, $definition);
    $created[] = ['quiz' => $quiz, 'questionid' => $questionid, 'definition' => $definition];
}
$forum = local_tce_pilot_forum($course, 4);

$connection = \local_testcase_exchange\external_database::connect();
\local_testcase_exchange\schema_manager::migrate($connection);
$service = new \local_testcase_exchange\testcase_service($connection);
foreach ($created as $item) {
    $service->save_quiz_settings((int) $item['quiz']->id, 2, [
        'enabled' => 1,
        'review_mode' => 'teacher',
        'reward_policy' => 'disabled',
        'show_oracle_output' => 0,
        'leaderboard_enabled' => 0,
        'max_runs_per_minute' => 10,
        'max_input_bytes' => 4096,
    ]);
    $service->save_question_policy((int) $item['quiz']->id, $item['questionid'], 2, [
        'input_mode' => 'stdin',
        'testcode_template' => '',
        'normalization_mode' => 'trim',
        'categories' => 'normal,boundary,empty,invalid,large,branch,other',
    ]);
}
$connection->close();
rebuild_course_cache($course->id, true);

echo json_encode([
    'courseid' => (int) $course->id,
    'shortname' => $course->shortname,
    'quizzes' => array_map(static function(array $item): array {
        return ['id' => (int) $item['quiz']->id, 'questionid' => $item['questionid']];
    }, $created),
    'forumid' => (int) $forum->id,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
