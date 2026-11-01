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

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/quiz/locallib.php');

use mod_quiz\local\access_rule_base;
use mod_quiz\quiz_settings;

/**
 * Access rule to toggle student testcase contribution and exchange per quiz.
 *
 * @package    quizaccess_testcaseexchange
 * @copyright  2026 Nguyen Quoc Thinh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class quizaccess_testcaseexchange extends access_rule_base {

    /**
     * Factory method: Returns rule instance if enabled for this quiz.
     *
     * @param quiz_settings $quizobj Quiz settings object.
     * @param int $timenow Current timestamp.
     * @param bool $canignoretimelimits Whether time limits can be ignored.
     * @return quizaccess_testcaseexchange|null
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
     *
     * @param int $quizid Quiz ID.
     * @return bool
     */
    public static function is_quiz_enabled(int $quizid): bool {
        global $DB;

        try {
            if (!$DB->get_manager()->table_exists('local_tce_quiz_settings')) {
                return false;
            }
            $record = $DB->get_record('local_tce_quiz_settings', ['quiz_id' => $quizid], 'is_enabled, enabled');
            if ($record) {
                return (!empty($record->is_enabled) || !empty($record->enabled));
            }
        } catch (\Throwable $e) {
            return false;
        }
        return false;
    }

    /**
     * Add settings elements to the quiz settings form (mod_form).
     *
     * @param mod_quiz_mod_form $quizform Quiz form object.
     * @param MoodleQuickForm $mform QuickForm form.
     */
    public static function add_settings_form_fields(mod_quiz_mod_form $quizform, MoodleQuickForm $mform) {
        $mform->addElement('header', 'testcaseexchangehdr', get_string('testcaseexchangehdr', 'quizaccess_testcaseexchange'));

        $mform->addElement('selectyesno', 'enable_testcase_exchange', get_string('enabletestcaseexchange', 'quizaccess_testcaseexchange'));
        $mform->addHelpButton('enable_testcase_exchange', 'enabletestcaseexchange', 'quizaccess_testcaseexchange');

        // Load current setting if editing existing quiz.
        $defaultval = 0;
        $currentquiz = $quizform->get_current();
        if (!empty($currentquiz->id)) {
            $defaultval = self::is_quiz_enabled((int)$currentquiz->id) ? 1 : 0;
        }
        $mform->setDefault('enable_testcase_exchange', $defaultval);
    }

    /**
     * Save the settings when the quiz form is submitted.
     *
     * @param stdClass $quiz Quiz object.
     */
    public static function save_settings($quiz) {
        global $DB, $USER;

        if (!isset($quiz->enable_testcase_exchange)) {
            return;
        }

        $quizid = (int)$quiz->id;
        $isenabled = !empty($quiz->enable_testcase_exchange) ? 1 : 0;
        $timenow = time();

        try {
            if (!$DB->get_manager()->table_exists('local_tce_quiz_settings')) {
                return;
            }

            $existing = $DB->get_record('local_tce_quiz_settings', ['quiz_id' => $quizid]);
            if ($existing) {
                $existing->is_enabled = $isenabled;
                $existing->enabled = $isenabled;
                $existing->updated_by = $USER->id ?? null;
                $existing->timemodified = $timenow;
                $DB->update_record('local_tce_quiz_settings', $existing);
            } else {
                $record = new \stdClass();
                $record->quiz_id = $quizid;
                $record->is_enabled = $isenabled;
                $record->enabled = $isenabled;
                $record->review_mode = 'teacher';
                $record->reward_policy = 'one_for_one';
                $record->show_oracle_output = 1;
                $record->leaderboard_enabled = 1;
                $record->max_runs_per_minute = 10;
                $record->max_input_bytes = 8192;
                $record->updated_by = $USER->id ?? null;
                $record->timecreated = $timenow;
                $record->timemodified = $timenow;
                $DB->insert_record('local_tce_quiz_settings', $record);
            }
        } catch (\Throwable $e) {
            debugging('Error saving testcase exchange settings: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    /**
     * Delete settings when the quiz is deleted.
     *
     * @param stdClass $quiz Quiz object.
     */
    public static function delete_settings($quiz) {
        global $DB;

        $quizid = (int)$quiz->id;
        try {
            if ($DB->get_manager()->table_exists('local_tce_quiz_settings')) {
                $DB->delete_records('local_tce_quiz_settings', ['quiz_id' => $quizid]);
            }
        } catch (\Throwable $e) {
            debugging('Error deleting testcase exchange settings: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    /**
     * Display informative banner and action button on Quiz view page when rule is enabled.
     *
     * @return string
     */
    public function description() {
        $quizid = $this->quiz->id;
        $courseid = $this->quiz->course;

        $dashboardurl = new \moodle_url('/local/testcase_exchange/index.php', [
            'course' => $courseid,
            'quiz' => $quizid,
        ]);

        $btn = \html_writer::link(
            $dashboardurl,
            get_string('gotodashboard', 'quizaccess_testcaseexchange'),
            ['class' => 'btn btn-outline-primary font-weight-bold text-nowrap']
        );

        $headertext = get_string('testcasebanner', 'quizaccess_testcaseexchange');
        $desctext = get_string('testcasebanner_desc', 'quizaccess_testcaseexchange');

        $content = \html_writer::div(
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
