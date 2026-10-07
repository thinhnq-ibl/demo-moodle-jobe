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
 * Moodle course and question context service.
 *
 * @package    local_testcase_exchange
 * @copyright  2026 Nguyen Quoc Thinh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace local_testcase_exchange;

/**
 * Resolves and validates Moodle course, Quiz and CodeRunner question context.
 */
final class context_service {
    /**
     * Return current CodeRunner question versions used by Quizzes in a course.
     *
     * @param int $courseid Course ID.
     * @return array Question context records.
     */
    public static function questions_for_course(int $courseid): array {
        global $DB;
        $sql = "
            SELECT CONCAT(qz.id, '-', q.id) AS rowid,
                   qz.id AS quizid, qz.name AS quizname, q.id AS questionid,
                   q.name AS questionname, qo.coderunnertype
              FROM {course_modules} cm
              JOIN {modules} m ON m.id = cm.module AND m.name = 'quiz'
              JOIN {quiz} qz ON qz.id = cm.instance
              JOIN {quiz_slots} qs ON qs.quizid = qz.id
              JOIN {question_references} qr ON qr.itemid = qs.id AND qr.component = 'mod_quiz'
              JOIN {question_bank_entries} qbe ON qbe.id = qr.questionbankentryid
              JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
               AND qv.version = CASE WHEN qr.version IS NULL THEN (
                   SELECT MAX(qv2.version)
                     FROM {question_versions} qv2
                    WHERE qv2.questionbankentryid = qbe.id AND qv2.status = 'ready'
               ) ELSE qr.version END
              JOIN {question} q ON q.id = qv.questionid AND q.qtype = 'coderunner'
              JOIN {question_coderunner_options} qo ON qo.questionid = q.id
             WHERE cm.course = ?
             ORDER BY qz.id, qs.slot";
        return array_values($DB->get_records_sql($sql, [$courseid]));
    }

    /**
     * Validate that a question belongs to a Quiz in a course.
     *
     * @param int $courseid Course ID.
     * @param int $quizid Quiz ID.
     * @param int $questionid Question ID.
     * @return \stdClass Validated question context.
     */
    public static function validate(int $courseid, int $quizid, int $questionid): \stdClass {
        foreach (self::questions_for_course($courseid) as $record) {
            if ((int) $record->quizid === $quizid && (int) $record->questionid === $questionid) {
                return $record;
            }
        }
        throw new \moodle_exception('invalidcontext', 'local_testcase_exchange');
    }
}
