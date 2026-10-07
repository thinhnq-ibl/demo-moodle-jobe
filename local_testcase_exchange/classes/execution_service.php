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
 * CodeRunner execution service.
 *
 * @package    local_testcase_exchange
 * @copyright  2026 Nguyen Quoc Thinh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace local_testcase_exchange;

/**
 * Runs synthetic testcases through the actual CodeRunner question template.
 */
final class execution_service {
    /**
     * Run student and reference sources against one synthetic testcase.
     *
     * @param int $questionid CodeRunner question ID.
     * @param string $studentsource Student source code.
     * @param string $input Test input or expression.
     * @param array $policy Question policy.
     * @param \stdClass $quiz Quiz record.
     * @return array Student and oracle outcomes.
     */
    public static function run_pair(
        int $questionid,
        string $studentsource,
        string $input,
        array $policy,
        \stdClass $quiz
    ): array {
        global $CFG, $USER;

        require_once($CFG->libdir . '/questionlib.php');
        \question_bank::load_question_definition_classes('coderunner');
        $question = \question_bank::load_question($questionid);
        if (!$question instanceof \qtype_coderunner_question) {
            throw new \moodle_exception('notcoderunner', 'local_testcase_exchange');
        }
        if (trim((string) $question->answer) === '') {
            throw new \moodle_exception('missingoracle', 'local_testcase_exchange');
        }
        $question->get_prototype();

        $testcase = self::make_testcase($question, $input, $policy);
        $student = self::run_source($question, $testcase, $studentsource, $quiz, $USER);
        $oracle = self::run_source($question, $testcase, (string) $question->answer, $quiz, $USER);
        return ['student' => $student, 'oracle' => $oracle];
    }

    /**
     * Build a CodeRunner testcase object from input policy.
     *
     * @param \qtype_coderunner_question $question Question object.
     * @param string $input Test input.
     * @param array $policy Question policy.
     * @return \stdClass
     */
    private static function make_testcase($question, string $input, array $policy): \stdClass {
        $testcase = new \stdClass();
        $testcase->id = 0;
        $testcase->questionid = (int) $question->id;
        $testcase->testtype = 0;
        $testcase->stdin = '';
        $testcase->extra = '';
        $testcase->expected = '__TCE_CAPTURE_OUTPUT__';
        $testcase->useasexample = 0;
        $testcase->display = 'HIDE';
        $testcase->hiderestiffail = 0;
        $testcase->mark = 1.0;

        $mode = $policy['input_mode'] ?? 'testcode';
        if ($mode === 'stdin') {
            $prototype = !empty($question->testcases) ? reset($question->testcases) : null;
            $testcase->testcode = $prototype ? (string) $prototype->testcode : '';
            $testcase->stdin = $input;
        } else if ($mode === 'template') {
            $template = (string) ($policy['testcode_template'] ?? '');
            if ($template === '' || strpos($template, '{{INPUT}}') === false) {
                throw new \moodle_exception('invalidtestcodetemplate', 'local_testcase_exchange');
            }
            $testcase->testcode = str_replace('{{INPUT}}', $input, $template);
        } else {
            $testcase->testcode = $input;
        }
        return $testcase;
    }

    /**
     * Grade one source string through CodeRunner.
     *
     * @param \qtype_coderunner_question $question Question object.
     * @param \stdClass $testcase Synthetic testcase.
     * @param string $source Program source.
     * @param \stdClass $quiz Quiz record.
     * @param \stdClass $user User record.
     * @return array Normalised execution outcome.
     */
    private static function run_source($question, \stdClass $testcase, string $source, \stdClass $quiz, \stdClass $user): array {
        try {
            $runquestion = clone $question;
            $runquestion->testcases = [$testcase];
            $runquestion->student = $user;
            $runquestion->quiz = $quiz;
            $runquestion->contextid = \context_course::instance((int) $quiz->course)->id;
            [, , $cache] = $runquestion->grade_response(['answer' => $source], false, false, false);
            $outcome = unserialize($cache['_testoutcome'], ['allowed_classes' => true]);
            $server = '';
            if (!empty($outcome->sandboxinfo['jobeserver'])) {
                $server = (string) $outcome->sandboxinfo['jobeserver'];
            }
            if ($outcome->run_failed()) {
                return ['output' => '', 'outcome' => 'infrastructure_error', 'server' => $server,
                    'error' => (string) $outcome->errormessage];
            }
            if ($outcome->has_syntax_error()) {
                return ['output' => '', 'outcome' => 'compile_error', 'server' => $server,
                    'error' => (string) $outcome->errormessage];
            }
            if (empty($outcome->testresults)) {
                return ['output' => '', 'outcome' => 'runtime_error', 'server' => $server,
                    'error' => (string) $outcome->errormessage];
            }
            $result = reset($outcome->testresults);
            return ['output' => rtrim((string) $result->got), 'outcome' => 'success', 'server' => $server, 'error' => ''];
        } catch (\Throwable $e) {
            return ['output' => '', 'outcome' => 'infrastructure_error', 'server' => '', 'error' => $e->getMessage()];
        }
    }
}
