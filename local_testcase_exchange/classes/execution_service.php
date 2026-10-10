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
        $proto = $question->get_prototype();
        $template = (string) ($policy['testcode_template'] ?? '');
        if ($template === '') {
            $template = (string) ($question->template ?: ($proto ? $proto->template : ''));
        }
        $grader = (string) ($question->grader ?: 'EqualityGrader');
        $qversion = isset($question->version) ? (int) $question->version : 1;
        $oraclehash = hash('sha256', (string) $question->answer);
        $testsuitehash = hash('sha256', json_encode($question->testcases ?? []));
        $limits = [
            'cputimelimitsecs' => $question->cputimelimitsecs,
            'memlimitmb' => $question->memlimitmb,
            'allornothing' => $question->allornothing ?? 1,
            'sandbox' => $question->sandbox,
        ];

        $testcase = self::make_testcase($question, $input, $policy);
        $student = self::run_source($question, $testcase, $studentsource, $quiz, $USER);
        $oracle = self::run_source($question, $testcase, (string) $question->answer, $quiz, $USER);
        return [
            'student' => $student,
            'oracle' => $oracle,
            'oracle_solution' => (string) $question->answer,
            'template' => $template,
            'coderunnertype' => (string) ($question->coderunnertype ?? ''),
            'question_version' => $qversion,
            'grader_type' => $grader,
            'oracle_version_hash' => $oraclehash,
            'test_suite_version_hash' => $testsuitehash,
            'execution_limits' => json_encode($limits, JSON_UNESCAPED_SLASHES),
        ];
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
            $output = rtrim((string) $result->got);
            if (self::is_execution_error($output)) {
                return ['output' => $output, 'outcome' => 'runtime_error', 'server' => $server, 'error' => $output];
            }
            return ['output' => $output, 'outcome' => 'success', 'server' => $server, 'error' => ''];
        } catch (\Throwable $e) {
            return ['output' => '', 'outcome' => 'infrastructure_error', 'server' => '', 'error' => $e->getMessage()];
        }
    }

    /**
     * Check if output string contains runtime error or sandbox execution failure markers.
     *
     * @param string $output Program output.
     * @return bool True if output indicates execution failure.
     */
    public static function is_execution_error(string $output): bool {
        return (bool) preg_match(
            '/\*\*\*(Run error|Time limit exceeded|Memory limit exceeded|Illegal system call|Internal error|Output limit exceeded|Abnormal termination|Server overload|No run).*?\*\*\*/i',
            $output
        ) ||
        str_contains($output, 'Traceback (most recent call last):') ||
        (bool) preg_match('/^Exception in thread\b/m', $output) ||
        (bool) preg_match('/\b(Segmentation fault|core dumped)\b/i', $output);
    }
}

