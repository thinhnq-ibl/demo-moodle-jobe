<?php
defined('MOODLE_INTERNAL') || die();

use mod_quiz\local\access_rule_base;
use mod_quiz\quiz_settings;

/**
 * Access rule to toggle student testcase contribution and exchange per quiz.
 *
 * @package    quizaccess_testcaseexchange
 */
class quizaccess_testcaseexchange extends access_rule_base {

    /**
     * Factory method: Returns rule instance if enabled for this quiz.
     */
    public static function make(quiz_settings $quizobj, $timenow, $canignoretimelimits) {
        $quiz = $quizobj->get_quiz();
        if (self::is_quiz_enabled($quiz->id)) {
            return new self($quizobj, $timenow);
        }
        return null;
    }

    /**
     * Check if testcase exchange is enabled for a given quiz ID.
     */
    public static function is_quiz_enabled($quizid) {
        $db_host = getenv('MOODLE_DB_HOST') ?: 'mariadb';
        $conn = @new mysqli($db_host, 'moodle_app_writer', 'JobeSecret123!', 'testcase_store', 3306);
        if ($conn->connect_error) {
            return false;
        }

        $enabled = false;
        $stmt = $conn->prepare("SELECT is_enabled FROM quiz_settings WHERE quiz_id = ?");
        if ($stmt) {
            $stmt->bind_param('i', $quizid);
            $stmt->execute();
            $stmt->bind_result($val);
            if ($stmt->fetch()) {
                $enabled = ((int)$val === 1);
            }
            $stmt->close();
        }
        $conn->close();
        return $enabled;
    }

    /**
     * Add settings elements to the quiz settings form (mod_form).
     */
    public static function add_settings_form_fields(mod_quiz_mod_form $quizform, MoodleQuickForm $mform) {
        $mform->addElement('header', 'testcaseexchangehdr', get_string('testcaseexchangehdr', 'quizaccess_testcaseexchange'));
        
        $mform->addElement('selectyesno', 'enable_testcase_exchange', get_string('enabletestcaseexchange', 'quizaccess_testcaseexchange'));
        $mform->addHelpButton('enable_testcase_exchange', 'enabletestcaseexchange', 'quizaccess_testcaseexchange');
        
        // Load current setting if editing existing quiz
        $default_val = 0;
        $current_quiz = $quizform->get_current();
        if (!empty($current_quiz->id)) {
            $default_val = self::is_quiz_enabled($current_quiz->id) ? 1 : 0;
        }
        $mform->setDefault('enable_testcase_exchange', $default_val);
    }

    /**
     * Save the settings when the quiz form is submitted.
     */
    public static function save_settings($quiz) {
        if (!isset($quiz->enable_testcase_exchange)) {
            return;
        }

        $quizid = (int)$quiz->id;
        $isenabled = !empty($quiz->enable_testcase_exchange) ? 1 : 0;

        $db_host = getenv('MOODLE_DB_HOST') ?: 'mariadb';
        $conn = @new mysqli($db_host, 'moodle_app_writer', 'JobeSecret123!', 'testcase_store', 3306);
        if ($conn->connect_error) {
            return;
        }

        $stmt = $conn->prepare("INSERT INTO quiz_settings (quiz_id, is_enabled) VALUES (?, ?) ON DUPLICATE KEY UPDATE is_enabled = VALUES(is_enabled)");
        if ($stmt) {
            $stmt->bind_param('ii', $quizid, $isenabled);
            $stmt->execute();
            $stmt->close();
        }
        $conn->close();
    }

    /**
     * Delete settings when the quiz is deleted.
     */
    public static function delete_settings($quiz) {
        $quizid = (int)$quiz->id;
        $db_host = getenv('MOODLE_DB_HOST') ?: 'mariadb';
        $conn = @new mysqli($db_host, 'moodle_app_writer', 'JobeSecret123!', 'testcase_store', 3306);
        if ($conn->connect_error) {
            return;
        }

        $stmt = $conn->prepare("DELETE FROM quiz_settings WHERE quiz_id = ?");
        if ($stmt) {
            $stmt->bind_param('i', $quizid);
            $stmt->execute();
            $stmt->close();
        }
        $conn->close();
    }

    /**
     * Display informative banner and action button on Quiz view page when rule is enabled.
     */
    public function description() {
        global $CFG;

        $quizid = $this->quiz->id;
        $courseid = $this->quiz->course;

        $dashboardurl = new moodle_url('/local/testcase_exchange/index.php', [
            'course' => $courseid,
            'quiz' => $quizid,
        ]);

        $btn = html_writer::link(
            $dashboardurl,
            get_string('gotodashboard', 'quizaccess_testcaseexchange'),
            ['class' => 'btn btn-outline-primary font-weight-bold text-nowrap']
        );

        $headertext = get_string('testcasebanner', 'quizaccess_testcaseexchange');
        $desctext = get_string('testcasebanner_desc', 'quizaccess_testcaseexchange');

        $content = html_writer::div(
            '<div class="d-flex align-items-center justify-content-between flex-wrap">' .
                '<div class="mr-3 mb-2 mb-md-0">' .
                    '<div class="font-weight-bold text-dark mb-1">' . $headertext . '</div>' .
                    '<div class="text-muted small">' . $desctext . '</div>' .
                '</div>' .
                '<div class="mt-1 mt-md-0">' . $btn . '</div>' .
            '</div>',
            'card bg-light border p-3 my-4'
        );

        return $content;
    }
}
