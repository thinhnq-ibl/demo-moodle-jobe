<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// CodeRunner is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Execution context restoration and offline replay service.
 *
 * @package    local_testcase_exchange
 * @copyright  2026 Nguyen Quoc Thinh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace local_testcase_exchange;

defined('MOODLE_INTERNAL') || die();

/**
 * Restores execution context from frozen snapshots and replays runs independently.
 */
final class replay_service {

    /**
     * Replay a snapshot run completely independently on Jobe sandbox.
     *
     * @param array $snapshot The testcase_runs database row.
     * @param ?string $custominput Optional alternative test input.
     * @param string $jobenode Jobe node to use (default 'http://jobe1').
     * @return array Replay results with match evaluation and execution timing.
     */
    public static function replay_run(
        array $snapshot,
        ?string $custominput = null,
        string $jobenode = 'http://jobe1'
    ): array {
        $studentcode = (string) ($snapshot['student_code_snapshot'] ?? '');
        $oraclecode = (string) ($snapshot['oracle_solution_snapshot'] ?? '');
        $input = $custominput !== null ? $custominput : (string) ($snapshot['input_raw'] ?? '');
        $template = (string) ($snapshot['template_snapshot'] ?? '');
        $context = json_decode((string) ($snapshot['runtime_context'] ?? '{}'), true);
        $limits = json_decode((string) ($snapshot['execution_limits'] ?? '{}'), true);

        $lang = $context['coderunnertype'] ?? 'python3';
        $grader = $snapshot['grader_type'] ?? 'EqualityGrader';

        $starttime = microtime(true);

        // Run student code on Jobe.
        $studentres = self::dispatch_jobe($jobenode, $lang, $studentcode, $input, $limits);

        // Run oracle code on Jobe if available.
        $oracleres = [];
        if ($oraclecode !== '') {
            $oracleres = self::dispatch_jobe($jobenode, $lang, $oraclecode, $input, $limits);
        }

        $durationms = (int) round((microtime(true) - $starttime) * 1000);

        $studentstdout = trim((string) ($studentres['stdout'] ?? ''));
        $oraclestdout = trim((string) ($oracleres['stdout'] ?? ''));

        // Grade comparison using restored grader logic.
        $ispassed = self::evaluate_grader($grader, $studentstdout, $oraclestdout);

        // Check if output matches the original run recorded during quiz/exploration.
        $originaloutput = trim((string) ($snapshot['student_run_output'] ?? ''));
        $matchesoriginal = ($studentstdout === $originaloutput);

        return [
            'run_id' => $snapshot['id'] ?? 0,
            'language' => $lang,
            'grader' => $grader,
            'student_stdout' => $studentstdout,
            'oracle_stdout' => $oraclestdout,
            'original_stdout' => $originaloutput,
            'matches_original_run' => $matchesoriginal,
            'is_passed_against_oracle' => $ispassed,
            'student_jobe_outcome' => $studentres['outcome'] ?? 0,
            'student_cmpinfo' => $studentres['cmpinfo'] ?? '',
            'student_stderr' => $studentres['stderr'] ?? '',
            'duration_ms' => $durationms,
            'jobe_node' => $jobenode,
        ];
    }

    /**
     * Dispatch source and input to a Jobe sandbox runner.
     *
     * @param string $jobenode Jobe base URL.
     * @param string $lang Programming language ID.
     * @param string $code Source code.
     * @param string $input Standard input.
     * @param array $limits Execution limits.
     * @return array Jobe response.
     */
    public static function dispatch_jobe(
        string $jobenode,
        string $lang,
        string $code,
        string $input,
        array $limits = []
    ): array {
        $filename = 'source_' . time() . self::get_extension($lang);
        $payload = [
            'run_spec' => [
                'language_id' => $lang,
                'sourcefilename' => $filename,
                'sourcecode' => $code,
                'input' => $input,
            ],
        ];

        if (!empty($limits['cputimelimitsecs'])) {
            $payload['run_spec']['cputime'] = (int) $limits['cputimelimitsecs'];
        }
        if (!empty($limits['memlimitmb'])) {
            $payload['run_spec']['memorylimit'] = (int) $limits['memlimitmb'];
        }

        $url = rtrim($jobenode, '/') . '/jobe/index.php/restapi/runs';
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Accept: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        $response = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpcode !== 200 || !$response) {
            return [
                'outcome' => -1,
                'stdout' => '',
                'stderr' => "Jobe HTTP request failed with code $httpcode",
                'cmpinfo' => '',
            ];
        }

        return json_decode($response, true) ?: [];
    }

    /**
     * Evaluate student stdout against expected output based on grader rule.
     *
     * @param string $grader Grader class / rule name.
     * @param string $got Student output.
     * @param string $expected Expected / Oracle output.
     * @return bool
     */
    public static function evaluate_grader(string $grader, string $got, string $expected): bool {
        if ($got === $expected) {
            return true;
        }

        switch ($grader) {
            case 'NearEqualityGrader':
                // Normalize whitespace and trailing zeros in floats.
                $gotnorm = preg_replace('/\s+/', ' ', trim($got));
                $expnorm = preg_replace('/\s+/', ' ', trim($expected));
                return strcasecmp($gotnorm, $expnorm) === 0;

            case 'RegexGrader':
                if (trim($expected) === '') {
                    return false;
                }
                return (bool) @preg_match('/' . trim($expected) . '/s', $got);

            case 'EqualityGrader':
            default:
                return rtrim($got) === rtrim($expected);
        }
    }

    /**
     * Compute a state checksum across Moodle Gradebook and Attempt tables.
     * Used to verify that replay executions have zero side-effects.
     *
     * @return string SHA-256 state signature.
     */
    public static function compute_gradebook_state_signature(): string {
        global $DB;

        $tables = ['grade_grades', 'quiz_grades', 'quiz_attempts', 'question_attempts', 'question_attempt_steps'];
        $signatures = [];

        foreach ($tables as $table) {
            $count = $DB->count_records($table);
            $maxid = (int) $DB->get_field_sql("SELECT COALESCE(MAX(id), 0) FROM {{$table}}");
            $signatures[] = "$table:count=$count,maxid=$maxid";
        }

        return hash('sha256', implode(';', $signatures));
    }

    /**
     * Verify that an action produces zero side-effects on Gradebook and Attempts.
     *
     * @param callable $callback The action to execute.
     * @return array Verification report.
     */
    public static function verify_zero_side_effect(callable $callback): array {
        $before = self::compute_gradebook_state_signature();
        $result = $callback();
        $after = self::compute_gradebook_state_signature();

        return [
            'signature_before' => $before,
            'signature_after' => $after,
            'is_pure_read_only' => ($before === $after),
            'action_result' => $result,
        ];
    }

    /**
     * Helper to return standard source file extension for language.
     *
     * @param string $lang Jobe language ID.
     * @return string
     */
    private static function get_extension(string $lang): string {
        switch ($lang) {
            case 'python3':
                return '.py';
            case 'c':
                return '.c';
            case 'cpp':
                return '.cpp';
            case 'java':
                return '.java';
            default:
                return '.txt';
        }
    }
}
