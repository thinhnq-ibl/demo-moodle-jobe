<?php
namespace local_testcase_exchange;

defined('MOODLE_INTERNAL') || die();

/** Runs a synthetic testcase through the actual CodeRunner question template. */
final class execution_service {
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
        } elseif ($mode === 'template') {
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
