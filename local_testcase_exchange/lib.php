<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Plugin callbacks and configuration helpers.
 *
 * @package local_testcase_exchange
 * @copyright 2026 Nguyen Quoc Thinh
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
/**
 * Return the external testcase database configuration.
 *
 * @return array{host:string, port:int, user:string, pass:string, name:string}
 */
function local_testcase_exchange_get_db_config(): array {
    $port = (int) get_config('local_testcase_exchange', 'db_port');
    $password = get_config('local_testcase_exchange', 'db_pass');

    return [
        'host' => get_config('local_testcase_exchange', 'db_host') ?: 'mariadb',
        'port' => $port > 0 && $port <= 65535 ? $port : 3306,
        'user' => get_config('local_testcase_exchange', 'db_user') ?: 'moodle_app_writer',
        'pass' => $password === false ? '' : (string) $password,
        'name' => get_config('local_testcase_exchange', 'db_name') ?: 'testcase_store',
    ];
}

/**
 * Return configured Jobe servers with a ready-to-use runs endpoint.
 *
 * The setting accepts the same semicolon-separated host format as CodeRunner,
 * as well as one server per line. A full REST runs URL is also accepted.
 *
 * @return array<int, array{label:string, runsurl:string}>
 */
function local_testcase_exchange_get_jobe_servers(): array {
    $configured = trim((string) get_config('local_testcase_exchange', 'jobe_servers'));
    if ($configured === '') {
        $configured = trim((string) get_config('qtype_coderunner', 'jobe_host'));
    }
    if ($configured === '') {
        $configured = 'jobe1;jobe2';
    }

    $servers = [];
    foreach (preg_split('/[;\r\n]+/', $configured) as $server) {
        $server = trim($server);
        if ($server === '') {
            continue;
        }

        if (!preg_match('~^https?://~i', $server)) {
            $server = 'http://' . $server;
        }

        $baseurl = rtrim($server, '/');
        if (preg_match('~/jobe/index\.php/restapi/runs$~i', $baseurl)) {
            $runsurl = $baseurl;
        } else if (preg_match('~/jobe/index\.php/restapi$~i', $baseurl)) {
            $runsurl = $baseurl . '/runs';
        } else {
            $runsurl = $baseurl . '/jobe/index.php/restapi/runs';
        }

        $servers[] = [
            'label' => parse_url($baseurl, PHP_URL_HOST) ?: $baseurl,
            'runsurl' => $runsurl,
        ];
    }

    return $servers;
}

/**
 * Add the testcase exchange link to course navigation.
 *
 * @param navigation_node $parentnode Course navigation node.
 * @param stdClass $course Current course.
 * @param context_course $context Course context.
 */
function local_testcase_exchange_extend_navigation_course(navigation_node $parentnode, stdClass $course, context_course $context) {
    // Hide the link from guests and logged-out users.
    if (!isloggedin() || isguestuser()) {
        return;
    }

    // Respect the course-level view capability.
    if (!has_capability('local/testcase_exchange:view', $context)) {
        return;
    }

    $url = new moodle_url('/local/testcase_exchange/index.php', ['course' => $course->id]);
    $node = navigation_node::create(
        get_string('nav_testcase_bank', 'local_testcase_exchange'),
        $url,
        navigation_node::TYPE_CUSTOM,
        null,
        'testcase_exchange_node',
        new pix_icon('i/report', '')
    );

    $node->showinflatnavigation = true;
    $parentnode->add_node($node);
}

/**
 * Add contextual testcase links to Quiz navigation.
 *
 * @param settings_navigation $navigation Settings navigation.
 * @param context $context Current page context.
 */
function local_testcase_exchange_extend_settings_navigation(settings_navigation $navigation, context $context): void {
    if (!isloggedin() || isguestuser()) {
        return;
    }

    if ($context->contextlevel === CONTEXT_COURSE) {
        if (!has_capability('local/testcase_exchange:view', $context)) {
            return;
        }
        $courseadmin = $navigation->get('courseadmin');
        if ($courseadmin) {
            $bankurl = new moodle_url('/local/testcase_exchange/index.php', ['course' => $context->instanceid]);
            $courseadmin->add(
                get_string('nav_testcase_bank', 'local_testcase_exchange'),
                $bankurl,
                navigation_node::TYPE_SETTING,
                null,
                'testcase_exchange_course_bank',
                new pix_icon('i/report', '')
            );
            if (has_capability('local/testcase_exchange:review', $context)) {
                $reviewurl = new moodle_url('/local/testcase_exchange/review.php', ['course' => $context->instanceid]);
                $courseadmin->add(
                    get_string('reviewcontributions', 'local_testcase_exchange'),
                    $reviewurl,
                    navigation_node::TYPE_SETTING,
                    null,
                    'testcase_exchange_course_review',
                    new pix_icon('i/marked', '')
                );
            }
        }
        return;
    }

    if ($context->contextlevel !== CONTEXT_MODULE) {
        return;
    }

    $cm = get_coursemodule_from_id('', $context->instanceid, 0, false, IGNORE_MISSING);
    if (!$cm || $cm->modname !== 'quiz') {
        return;
    }
    $context = context_course::instance($cm->course);
    if (!has_capability('local/testcase_exchange:view', $context)) {
        return;
    }

    $url = new moodle_url('/local/testcase_exchange/index.php', ['course' => $cm->course]);
    $label = get_string('nav_testcase_bank', 'local_testcase_exchange');
    if (has_capability('local/testcase_exchange:manage', $context)) {
        $questions = \local_testcase_exchange\context_service::questions_for_course($cm->course);
        foreach ($questions as $question) {
            if ((int) $question->quizid === (int) $cm->instance) {
                $url = new moodle_url('/local/testcase_exchange/policy.php', [
                    'course' => $cm->course,
                    'question' => $question->quizid . ':' . $question->questionid,
                ]);
                break;
            }
        }
        $label = get_string('configuretestcasecontributions', 'local_testcase_exchange');
    }

    $navigation->add(
        $label,
        $url,
        navigation_node::TYPE_SETTING,
        null,
        'testcase_exchange_quiz',
        new pix_icon('i/report', '')
    );
}

/**
 * Add the testcase contribution toggle to Quiz settings.
 *
 * @param moodleform_mod $formwrapper Module form wrapper.
 * @param MoodleQuickForm $mform Module form.
 */
function local_testcase_exchange_coursemodule_standard_elements($formwrapper, MoodleQuickForm $mform): void {
    $current = $formwrapper->get_current();
    if (($current->modulename ?? '') !== 'quiz') {
        return;
    }

    $mform->addElement('header', 'testcaseexchangeheader', get_string('testcaseexchangeheading', 'local_testcase_exchange'));
    $mform->addElement(
        'advcheckbox',
        'testcaseexchangeenabled',
        get_string('enabletestcasecontributions', 'local_testcase_exchange')
    );
    $mform->addHelpButton('testcaseexchangeenabled', 'enabletestcasecontributions', 'local_testcase_exchange');

    $enabled = false;
    $quizid = (int) $formwrapper->get_instance();
    if ($quizid > 0) {
        try {
            $connection = \local_testcase_exchange\external_database::connect();
            $service = new \local_testcase_exchange\testcase_service($connection);
            $settings = $service->quiz_settings($quizid);
            $enabled = !empty($settings['enabled']);
            $connection->close();
        } catch (Throwable $e) {
            debugging($e->getMessage(), DEBUG_DEVELOPER);
        }
    }
    $mform->setDefault('testcaseexchangeenabled', $enabled ? 1 : 0);
}

/**
 * Save the testcase contribution toggle after a Quiz is created or updated.
 *
 * @param stdClass $data Submitted module data.
 * @param stdClass $course Course record.
 * @return stdClass Unmodified module data.
 */
function local_testcase_exchange_coursemodule_edit_post_actions($data, $course) {
    global $USER;

    if (($data->modulename ?? '') !== 'quiz' || empty($data->instance)) {
        return $data;
    }

    try {
        $connection = \local_testcase_exchange\external_database::connect();
        \local_testcase_exchange\schema_manager::migrate($connection);
        $service = new \local_testcase_exchange\testcase_service($connection);
        $settings = $service->quiz_settings((int) $data->instance);
        $service->save_quiz_settings((int) $data->instance, (int) $USER->id, [
            'enabled' => !empty($data->testcaseexchangeenabled),
            'review_mode' => $settings['review_mode'] ?? 'teacher',
            'reward_policy' => $settings['reward_policy'] ?? 'disabled',
            'show_oracle_output' => $settings['show_oracle_output'] ?? false,
            'leaderboard_enabled' => $settings['leaderboard_enabled'] ?? false,
            'max_runs_per_minute' => $settings['max_runs_per_minute'] ?? 10,
            'max_input_bytes' => $settings['max_input_bytes'] ?? 8192,
        ]);
        $connection->close();
    } catch (Throwable $e) {
        debugging($e->getMessage(), DEBUG_DEVELOPER);
    }

    return $data;
}

/**
 * Show contribution links after a student has completed an enabled Quiz.
 *
 * @return string HTML displayed before the page footer.
 */
function local_testcase_exchange_before_footer(): string {
    global $DB, $PAGE, $USER;

    if (!isloggedin() || isguestuser()) {
        return '';
    }
    try {
        $cm = $PAGE->cm;
    } catch (Throwable $e) {
        return '';
    }
    if (!$cm || $cm->modname !== 'quiz') {
        return '';
    }
    if (!in_array($PAGE->pagetype, [
        'mod-quiz-attempt',
        'mod-quiz-summary',
        'mod-quiz-view',
        'mod-quiz-review',
    ], true)) {
        return '';
    }

    $coursecontext = context_course::instance($cm->course);
    if (!has_capability('local/testcase_exchange:contribute', $coursecontext)) {
        return '';
    }

    $requestedattemptid = optional_param('attempt', 0, PARAM_INT);
    $attempt = $DB->get_record_sql(
        "SELECT *
           FROM {quiz_attempts}
          WHERE quiz = :quizid
                AND userid = :userid
                AND state <> :abandoned
                AND (:attemptid = 0 OR id = :attemptid2)
       ORDER BY attempt DESC",
        [
            'quizid' => $cm->instance,
            'userid' => $USER->id,
            'abandoned' => 'abandoned',
            'attemptid' => $requestedattemptid,
            'attemptid2' => $requestedattemptid,
        ],
        IGNORE_MULTIPLE
    );
    if (!$attempt) {
        return '';
    }

    try {
        $connection = \local_testcase_exchange\external_database::connect();
        $service = new \local_testcase_exchange\testcase_service($connection);
        $settings = $service->quiz_settings((int) $cm->instance);
        $connection->close();
        if (empty($settings['enabled'])) {
            return '';
        }
    } catch (Throwable $e) {
        debugging($e->getMessage(), DEBUG_DEVELOPER);
        return '';
    }

    $links = [];
    $questions = \local_testcase_exchange\context_service::questions_for_course($cm->course);
    foreach ($questions as $question) {
        if ((int) $question->quizid !== (int) $cm->instance) {
            continue;
        }
        $hasanswer = \local_testcase_exchange\context_service::has_valid_submission(
            (int) $attempt->uniqueid,
            (int) $question->questionid
        );
        if (!$hasanswer) {
            continue;
        }
        $url = new moodle_url('/local/testcase_exchange/index.php', [
            'course' => $cm->course,
            'question' => $question->quizid . ':' . $question->questionid,
            'attempt' => $attempt->id,
        ]);
        $links[] = html_writer::link(
            $url,
            get_string('contributetestcaseafterquiz', 'local_testcase_exchange') . ': ' . $question->questionname,
            ['class' => 'btn btn-primary mr-2 mb-2']
        );
    }
    if (!$links) {
        return '';
    }

    $content = html_writer::tag(
        'p',
        get_string('contributetestcaseafterquizintro', 'local_testcase_exchange'),
        ['class' => 'mb-2']
    );
    $content .= implode('', $links);
    return html_writer::div($content, 'card card-body mt-4 mb-4');
}
