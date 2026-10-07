<?php
require_once(__DIR__ . '/../../config.php');

use local_testcase_exchange\context_service;
use local_testcase_exchange\external_database;
use local_testcase_exchange\schema_manager;
use local_testcase_exchange\testcase_service;

require_login();

$courseid = required_param('course', PARAM_INT);
$course = get_course($courseid);
$context = context_course::instance($courseid);
require_capability('local/testcase_exchange:view', $context);

$PAGE->set_url(new moodle_url('/local/testcase_exchange/index.php', ['course' => $courseid]));
$PAGE->set_context($context);
$PAGE->set_title(get_string('nav_testcase_bank', 'local_testcase_exchange'));
$PAGE->set_heading($course->fullname . ' — ' . get_string('nav_testcase_bank', 'local_testcase_exchange'));

$questions = context_service::questions_for_course($courseid);
$questionmap = [];
foreach ($questions as $question) {
    $questionmap[$question->quizid . ':' . $question->questionid] = $question;
}

$connection = null;
$service = null;
$databaseerror = '';
try {
    $connection = external_database::connect();
    schema_manager::migrate($connection);
    $service = new testcase_service($connection);
} catch (Throwable $e) {
    $databaseerror = get_string('databaseunavailable', 'local_testcase_exchange');
    debugging($e->getMessage(), DEBUG_DEVELOPER);
}

if ($service && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_sesskey();
    $action = required_param('action', PARAM_ALPHAEXT);
    try {
        if ($action === 'run') {
            require_capability('local/testcase_exchange:run', $context);
            $selection = required_param('question', PARAM_RAW_TRIMMED);
            if (!isset($questionmap[$selection])) {
                throw new moodle_exception('invalidcontext', 'local_testcase_exchange');
            }
            $questioncontext = $questionmap[$selection];
            $quiz = $DB->get_record('quiz', ['id' => $questioncontext->quizid, 'course' => $courseid], '*', MUST_EXIST);
            $run = $service->create_run(
                $courseid,
                $quiz,
                $questioncontext,
                (int) $USER->id,
                required_param('student_code', PARAM_RAW),
                required_param('test_input', PARAM_RAW),
                optional_param('predicted_output', '', PARAM_RAW),
                optional_param('purpose', '', PARAM_TEXT),
                optional_param('category', 'other', PARAM_ALPHANUMEXT),
                optional_param('reflection', '', PARAM_TEXT)
            );
            $message = $run['prediction_matches_oracle'] ?
                get_string('runcreatedmatch', 'local_testcase_exchange') :
                get_string('runcreatedmismatch', 'local_testcase_exchange');
            redirect($PAGE->url, $message, null, \core\output\notification::NOTIFY_SUCCESS);
        } elseif ($action === 'contribute') {
            require_capability('local/testcase_exchange:contribute', $context);
            $result = $service->submit_contribution(required_param('run_id', PARAM_INT), (int) $USER->id);
            redirect(
                $PAGE->url,
                get_string('contributionstatus', 'local_testcase_exchange', $result['status']),
                null,
                $result['status'] === 'duplicate' ?
                    \core\output\notification::NOTIFY_WARNING : \core\output\notification::NOTIFY_SUCCESS
            );
        } elseif ($action === 'explain') {
            require_capability('local/testcase_exchange:contribute', $context);
            $service->resubmit_explanation(
                required_param('contribution_id', PARAM_INT),
                (int) $USER->id,
                required_param('explanation', PARAM_TEXT)
            );
            redirect($PAGE->url, get_string('explanationsaved', 'local_testcase_exchange'), null,
                \core\output\notification::NOTIFY_SUCCESS);
        }
    } catch (Throwable $e) {
        redirect($PAGE->url, $e->getMessage(), null, \core\output\notification::NOTIFY_ERROR);
    }
}

$runs = $service ? $service->runs_for_user($courseid, (int) $USER->id) : [];
$contributions = $service ? $service->contributions_for_user($courseid, (int) $USER->id) : [];
$rewards = $service ? $service->rewards_for_user($courseid, (int) $USER->id) : [];
$contributionbyrun = [];
foreach ($contributions as $contribution) {
    $contributionbyrun[(int) $contribution['run_id']] = $contribution;
}

echo $OUTPUT->header();
if ($databaseerror) {
    echo $OUTPUT->notification($databaseerror, 'notifyproblem');
    echo $OUTPUT->footer();
    exit;
}

if (has_capability('local/testcase_exchange:manage', $context)) {
    echo html_writer::div(
        html_writer::link(
            new moodle_url('/local/testcase_exchange/policy.php', ['course' => $courseid]),
            get_string('managepolicies', 'local_testcase_exchange'),
            ['class' => 'btn btn-secondary mr-2']
        ) . (has_capability('local/testcase_exchange:review', $context) ? ' ' . html_writer::link(
            new moodle_url('/local/testcase_exchange/review.php', ['course' => $courseid]),
            get_string('reviewcontributions', 'local_testcase_exchange'),
            ['class' => 'btn btn-primary']
        ) : ''),
        'mb-3'
    );
}
?>
<?php if (has_capability('local/testcase_exchange:run', $context)): ?>
<div class="card mb-4">
    <div class="card-header"><strong><?= s(get_string('privateexploration', 'local_testcase_exchange')) ?></strong></div>
    <div class="card-body">
        <?php if (empty($questions)): ?>
            <div class="alert alert-warning"><?= s(get_string('nocoderunnerquestions', 'local_testcase_exchange')) ?></div>
        <?php else: ?>
            <form method="post">
                <input type="hidden" name="sesskey" value="<?= sesskey() ?>">
                <input type="hidden" name="action" value="run">
                <div class="form-group">
                    <label for="question"><strong><?= s(get_string('selectquestion', 'local_testcase_exchange')) ?></strong></label>
                    <select class="form-control" id="question" name="question" required>
                        <?php foreach ($questions as $question):
                            $quizsettings = $service->quiz_settings((int) $question->quizid);
                            $label = $question->quizname . ' — ' . $question->questionname;
                            if (empty($quizsettings['enabled'])) {
                                $label .= ' (' . get_string('disabled', 'core') . ')';
                            }
                        ?>
                            <option value="<?= (int) $question->quizid ?>:<?= (int) $question->questionid ?>" <?= empty($quizsettings['enabled']) ? 'disabled' : '' ?>><?= s($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="student_code"><strong><?= s(get_string('studentsource', 'local_testcase_exchange')) ?></strong></label>
                    <textarea class="form-control" id="student_code" name="student_code" rows="10" required></textarea>
                </div>
                <div class="row">
                    <div class="col-md-6 form-group">
                        <label for="test_input"><strong><?= s(get_string('testinput', 'local_testcase_exchange')) ?></strong></label>
                        <textarea class="form-control" id="test_input" name="test_input" rows="4" required></textarea>
                    </div>
                    <div class="col-md-6 form-group">
                        <label for="predicted_output"><strong><?= s(get_string('predictedoutput', 'local_testcase_exchange')) ?></strong></label>
                        <textarea class="form-control" id="predicted_output" name="predicted_output" rows="4"></textarea>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-4 form-group">
                        <label for="category"><?= s(get_string('category', 'local_testcase_exchange')) ?></label>
                        <select class="form-control" id="category" name="category">
                            <?php foreach (['normal', 'boundary', 'empty', 'invalid', 'large', 'branch', 'other'] as $category): ?>
                                <option value="<?= $category ?>"><?= s(get_string('category_' . $category, 'local_testcase_exchange')) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-8 form-group">
                        <label for="purpose"><?= s(get_string('purpose', 'local_testcase_exchange')) ?></label>
                        <input class="form-control" id="purpose" name="purpose" maxlength="1000">
                    </div>
                </div>
                <div class="form-group">
                    <label for="reflection"><?= s(get_string('reflection', 'local_testcase_exchange')) ?></label>
                    <input class="form-control" id="reflection" name="reflection" maxlength="1000">
                </div>
                <button class="btn btn-primary" type="submit"><?= s(get_string('runprivate', 'local_testcase_exchange')) ?></button>
            </form>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<ul class="nav nav-tabs" role="tablist">
    <li class="nav-item"><a class="nav-link active" data-toggle="tab" href="#runs"><?= s(get_string('myruns', 'local_testcase_exchange')) ?> (<?= count($runs) ?>)</a></li>
    <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#contributions"><?= s(get_string('mycontributions', 'local_testcase_exchange')) ?> (<?= count($contributions) ?>)</a></li>
    <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#rewards"><?= s(get_string('myrewards', 'local_testcase_exchange')) ?> (<?= count($rewards) ?>)</a></li>
</ul>
<div class="tab-content border border-top-0 p-3 mb-4">
    <div class="tab-pane active" id="runs">
        <div class="table-responsive"><table class="table table-sm table-striped">
            <thead><tr><th>ID</th><th>Input</th><th><?= s(get_string('predictedoutput', 'local_testcase_exchange')) ?></th><th><?= s(get_string('studentoutput', 'local_testcase_exchange')) ?></th><th><?= s(get_string('oracleoutput', 'local_testcase_exchange')) ?></th><th><?= s(get_string('status')) ?></th><th></th></tr></thead>
            <tbody>
            <?php foreach ($runs as $run):
                $runsettings = $service->quiz_settings((int) $run['quiz_id']);
                $existing = $contributionbyrun[(int) $run['id']] ?? null;
            ?>
                <tr>
                    <td><?= (int) $run['id'] ?></td>
                    <td><pre class="mb-0"><?= s($run['input_raw']) ?></pre></td>
                    <td><pre class="mb-0"><?= s($run['predicted_output'] ?? '') ?></pre></td>
                    <td><pre class="mb-0"><?= s($run['student_run_output'] ?? '') ?></pre><small><?= s($run['student_outcome']) ?></small></td>
                    <td><?php if (!empty($runsettings['show_oracle_output'])): ?><pre class="mb-0"><?= s($run['oracle_output'] ?? '') ?></pre><?php else: ?>—<?php endif; ?><small><?= s($run['oracle_outcome']) ?></small></td>
                    <td><?= s($existing['status'] ?? 'private') ?></td>
                    <td>
                        <?php if (has_capability('local/testcase_exchange:contribute', $context) &&
                                !$existing && $run['oracle_outcome'] === 'success'): ?>
                            <form method="post">
                                <input type="hidden" name="sesskey" value="<?= sesskey() ?>">
                                <input type="hidden" name="action" value="contribute">
                                <input type="hidden" name="run_id" value="<?= (int) $run['id'] ?>">
                                <button class="btn btn-sm btn-success" type="submit"><?= s(get_string('proposesharing', 'local_testcase_exchange')) ?></button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
    </div>
    <div class="tab-pane" id="contributions">
        <div class="table-responsive"><table class="table table-sm table-striped">
            <thead><tr><th>ID</th><th>Input</th><th><?= s(get_string('category', 'local_testcase_exchange')) ?></th><th><?= s(get_string('status')) ?></th><th><?= s(get_string('time')) ?></th></tr></thead>
            <tbody><?php foreach ($contributions as $item): ?><tr><td><?= (int) $item['id'] ?></td><td><pre class="mb-0"><?= s($item['input_normalized']) ?></pre></td><td><?= s($item['category'] ?? '') ?></td><td><?= s($item['status']) ?><?php if (!empty($item['latest_review_note'])): ?><br><small><?= s($item['latest_review_note']) ?></small><?php endif; ?><?php if ($item['status'] === 'needs_explanation' && has_capability('local/testcase_exchange:contribute', $context)): ?><form method="post" class="mt-2"><input type="hidden" name="sesskey" value="<?= sesskey() ?>"><input type="hidden" name="action" value="explain"><input type="hidden" name="contribution_id" value="<?= (int) $item['id'] ?>"><input class="form-control form-control-sm mb-1" name="explanation" required><button class="btn btn-sm btn-primary"><?= s(get_string('resubmitexplanation', 'local_testcase_exchange')) ?></button></form><?php endif; ?></td><td><?= s($item['submitted_at']) ?></td></tr><?php endforeach; ?></tbody>
        </table></div>
    </div>
    <div class="tab-pane" id="rewards">
        <div class="table-responsive"><table class="table table-sm table-striped">
            <thead><tr><th>ID</th><th>Input</th><th><?= s(get_string('oracleoutput', 'local_testcase_exchange')) ?></th><th><?= s(get_string('category', 'local_testcase_exchange')) ?></th><th><?= s(get_string('time')) ?></th></tr></thead>
            <tbody><?php foreach ($rewards as $item): ?><tr><td><?= (int) $item['id'] ?></td><td><pre class="mb-0"><?= s($item['input_normalized']) ?></pre></td><td><pre class="mb-0"><?= s($item['oracle_output'] ?? '') ?></pre></td><td><?= s($item['category'] ?? '') ?></td><td><?= s($item['created_at']) ?></td></tr><?php endforeach; ?></tbody>
        </table></div>
    </div>
</div>
<?php
if ($connection) {
    $connection->close();
}
echo $OUTPUT->footer();
