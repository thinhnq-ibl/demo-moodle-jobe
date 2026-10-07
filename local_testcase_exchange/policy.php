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
require_capability('local/testcase_exchange:manage', $context);

$PAGE->set_url(new moodle_url('/local/testcase_exchange/policy.php', ['course' => $courseid]));
$PAGE->set_context($context);
$PAGE->set_title(get_string('managepolicies', 'local_testcase_exchange'));
$PAGE->set_heading($course->fullname . ' — ' . get_string('managepolicies', 'local_testcase_exchange'));

$connection = external_database::connect();
schema_manager::migrate($connection);
$service = new testcase_service($connection);
$questions = context_service::questions_for_course($courseid);
$questionmap = [];
foreach ($questions as $question) {
    $questionmap[$question->quizid . ':' . $question->questionid] = $question;
}

$selection = optional_param('question', array_key_first($questionmap) ?? '', PARAM_RAW_TRIMMED);
$selected = $questionmap[$selection] ?? null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_sesskey();
    if (!$selected) {
        throw new moodle_exception('invalidcontext', 'local_testcase_exchange');
    }
    try {
        $service->save_quiz_settings((int) $selected->quizid, (int) $USER->id, [
            'enabled' => optional_param('enabled', 0, PARAM_BOOL),
            'review_mode' => required_param('review_mode', PARAM_ALPHA),
            'reward_policy' => required_param('reward_policy', PARAM_ALPHAEXT),
            'show_oracle_output' => optional_param('show_oracle_output', 0, PARAM_BOOL),
            'leaderboard_enabled' => optional_param('leaderboard_enabled', 0, PARAM_BOOL),
            'max_runs_per_minute' => required_param('max_runs_per_minute', PARAM_INT),
            'max_input_bytes' => required_param('max_input_bytes', PARAM_INT),
        ]);
        $service->save_question_policy((int) $selected->quizid, (int) $selected->questionid, (int) $USER->id, [
            'input_mode' => required_param('input_mode', PARAM_ALPHA),
            'testcode_template' => optional_param('testcode_template', '', PARAM_RAW),
            'normalization_mode' => required_param('normalization_mode', PARAM_ALPHA),
            'categories' => optional_param('categories', '', PARAM_TEXT),
        ]);
        redirect(new moodle_url($PAGE->url, ['question' => $selection]),
            get_string('policysaved', 'local_testcase_exchange'), null,
            \core\output\notification::NOTIFY_SUCCESS);
    } catch (Throwable $e) {
        redirect(new moodle_url($PAGE->url, ['question' => $selection]), $e->getMessage(), null,
            \core\output\notification::NOTIFY_ERROR);
    }
}

$quizsettings = $selected ? $service->quiz_settings((int) $selected->quizid) : [];
$policy = $selected ? $service->question_policy(
    (int) $selected->quizid,
    (int) $selected->questionid,
    (string) $selected->coderunnertype
) : [];

echo $OUTPUT->header();
echo html_writer::link(new moodle_url('/local/testcase_exchange/index.php', ['course' => $courseid]),
    get_string('backtodashboard', 'local_testcase_exchange'), ['class' => 'btn btn-secondary mb-3']);
?>
<?php if (empty($questions)): ?>
    <div class="alert alert-warning"><?= s(get_string('nocoderunnerquestions', 'local_testcase_exchange')) ?></div>
<?php else: ?>
    <form method="get" class="mb-4">
        <input type="hidden" name="course" value="<?= $courseid ?>">
        <label for="question"><strong><?= s(get_string('selectquestion', 'local_testcase_exchange')) ?></strong></label>
        <select id="question" name="question" class="form-control" onchange="this.form.submit()">
            <?php foreach ($questions as $question): $value = $question->quizid . ':' . $question->questionid; ?>
                <option value="<?= s($value) ?>" <?= $value === $selection ? 'selected' : '' ?>><?= s($question->quizname . ' — ' . $question->questionname) ?></option>
            <?php endforeach; ?>
        </select>
    </form>
    <form method="post">
        <input type="hidden" name="sesskey" value="<?= sesskey() ?>">
        <input type="hidden" name="question" value="<?= s($selection) ?>">
        <div class="card mb-3"><div class="card-header"><strong>Quiz policy</strong></div><div class="card-body">
            <div class="form-check"><input type="checkbox" class="form-check-input" id="enabled" name="enabled" value="1" <?= !empty($quizsettings['enabled']) ? 'checked' : '' ?>><label class="form-check-label" for="enabled"><?= s(get_string('enablefeature', 'local_testcase_exchange')) ?></label></div>
            <div class="form-check"><input type="checkbox" class="form-check-input" id="show_oracle_output" name="show_oracle_output" value="1" <?= !empty($quizsettings['show_oracle_output']) ? 'checked' : '' ?>><label class="form-check-label" for="show_oracle_output"><?= s(get_string('showoracle', 'local_testcase_exchange')) ?></label></div>
            <div class="form-check mb-3"><input type="checkbox" class="form-check-input" id="leaderboard_enabled" name="leaderboard_enabled" value="1" <?= !empty($quizsettings['leaderboard_enabled']) ? 'checked' : '' ?>><label class="form-check-label" for="leaderboard_enabled"><?= s(get_string('enableleaderboard', 'local_testcase_exchange')) ?></label></div>
            <label>Review mode</label><select class="form-control mb-2" name="review_mode"><option value="teacher" <?= $quizsettings['review_mode'] === 'teacher' ? 'selected' : '' ?>>Teacher</option><option value="auto" <?= $quizsettings['review_mode'] === 'auto' ? 'selected' : '' ?>>Auto</option></select>
            <label>Reward policy</label><select class="form-control mb-2" name="reward_policy"><option value="one_for_one" <?= $quizsettings['reward_policy'] === 'one_for_one' ? 'selected' : '' ?>>One for one</option><option value="disabled" <?= $quizsettings['reward_policy'] === 'disabled' ? 'selected' : '' ?>>Disabled</option></select>
            <label>Runs/minute</label><input class="form-control mb-2" type="number" min="1" max="120" name="max_runs_per_minute" value="<?= (int) $quizsettings['max_runs_per_minute'] ?>">
            <label>Max input bytes</label><input class="form-control" type="number" min="64" max="1048576" name="max_input_bytes" value="<?= (int) $quizsettings['max_input_bytes'] ?>">
        </div></div>
        <div class="card mb-3"><div class="card-header"><strong>Question policy</strong></div><div class="card-body">
            <label>Input mode</label><select class="form-control mb-2" name="input_mode"><option value="stdin" <?= $policy['input_mode'] === 'stdin' ? 'selected' : '' ?>>stdin</option><option value="testcode" <?= $policy['input_mode'] === 'testcode' ? 'selected' : '' ?>>testcode</option><option value="template" <?= $policy['input_mode'] === 'template' ? 'selected' : '' ?>>template</option></select>
            <label>Test code template (`{{INPUT}}`)</label><textarea class="form-control mb-2" name="testcode_template" rows="4"><?= s($policy['testcode_template'] ?? '') ?></textarea>
            <label>Normalization</label><select class="form-control mb-2" name="normalization_mode"><option value="raw" <?= $policy['normalization_mode'] === 'raw' ? 'selected' : '' ?>>raw</option><option value="trim" <?= $policy['normalization_mode'] === 'trim' ? 'selected' : '' ?>>trim</option><option value="json" <?= $policy['normalization_mode'] === 'json' ? 'selected' : '' ?>>json</option></select>
            <label>Categories</label><input class="form-control" name="categories" value="<?= s($policy['categories'] ?? '') ?>">
        </div></div>
        <button class="btn btn-primary" type="submit"><?= s(get_string('savechanges')) ?></button>
    </form>
<?php endif; ?>
<?php
$connection->close();
echo $OUTPUT->footer();
