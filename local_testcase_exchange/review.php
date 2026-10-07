<?php
require_once(__DIR__ . '/../../config.php');

use local_testcase_exchange\external_database;
use local_testcase_exchange\schema_manager;
use local_testcase_exchange\testcase_service;

require_login();
$courseid = required_param('course', PARAM_INT);
$course = get_course($courseid);
$context = context_course::instance($courseid);
require_capability('local/testcase_exchange:review', $context);

$PAGE->set_url(new moodle_url('/local/testcase_exchange/review.php', ['course' => $courseid]));
$PAGE->set_context($context);
$PAGE->set_title(get_string('reviewcontributions', 'local_testcase_exchange'));
$PAGE->set_heading($course->fullname . ' — ' . get_string('reviewcontributions', 'local_testcase_exchange'));

$connection = external_database::connect();
schema_manager::migrate($connection);
$service = new testcase_service($connection);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_sesskey();
    try {
        $service->review(
            required_param('contribution_id', PARAM_INT),
            $courseid,
            (int) $USER->id,
            required_param('decision', PARAM_ALPHAEXT),
            optional_param('review_note', '', PARAM_TEXT)
        );
        redirect($PAGE->url, get_string('reviewsaved', 'local_testcase_exchange'), null,
            \core\output\notification::NOTIFY_SUCCESS);
    } catch (Throwable $e) {
        redirect($PAGE->url, $e->getMessage(), null, \core\output\notification::NOTIFY_ERROR);
    }
}

$items = $service->pending_contributions($courseid);
echo $OUTPUT->header();
echo html_writer::link(
    new moodle_url('/local/testcase_exchange/index.php', ['course' => $courseid]),
    get_string('backtodashboard', 'local_testcase_exchange'),
    ['class' => 'btn btn-secondary mb-3']
);
?>
<div class="table-responsive">
    <table class="table table-striped">
        <thead><tr><th>ID</th><th>User ID</th><th>Quiz/Question</th><th>Input</th><th>Oracle</th><th>Mục đích</th><th>Nhóm</th><th>Review</th></tr></thead>
        <tbody>
        <?php if (empty($items)): ?>
            <tr><td colspan="8" class="text-center text-muted"><?= s(get_string('nocontributionspending', 'local_testcase_exchange')) ?></td></tr>
        <?php endif; ?>
        <?php foreach ($items as $item): ?>
            <tr>
                <td><?= (int) $item['id'] ?></td>
                <td><?= (int) $item['user_id'] ?></td>
                <td><?= (int) $item['quiz_id'] ?>/<?= (int) $item['question_id'] ?></td>
                <td><pre><?= s($item['input_normalized']) ?></pre></td>
                <td><pre><?= s($item['oracle_output'] ?? '') ?></pre></td>
                <td><?= s($item['purpose'] ?? '') ?></td>
                <td><?= s($item['category'] ?? '') ?></td>
                <td>
                    <form method="post">
                        <input type="hidden" name="sesskey" value="<?= sesskey() ?>">
                        <input type="hidden" name="contribution_id" value="<?= (int) $item['id'] ?>">
                        <input class="form-control form-control-sm mb-2" name="review_note" placeholder="<?= s(get_string('reviewnote', 'local_testcase_exchange')) ?>">
                        <button class="btn btn-sm btn-success" name="decision" value="approved"><?= s(get_string('approvecontribution', 'local_testcase_exchange')) ?></button>
                        <button class="btn btn-sm btn-warning" name="decision" value="needs_explanation"><?= s(get_string('needsexplanation', 'local_testcase_exchange')) ?></button>
                        <button class="btn btn-sm btn-danger" name="decision" value="rejected"><?= s(get_string('rejectcontribution', 'local_testcase_exchange')) ?></button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php
$connection->close();
echo $OUTPUT->footer();
