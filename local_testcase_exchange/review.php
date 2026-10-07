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
 * Contribution review page.
 *
 * @package    local_testcase_exchange
 * @copyright  2026 Nguyen Quoc Thinh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
use local_testcase_exchange\external_database;
use local_testcase_exchange\schema_manager;
use local_testcase_exchange\testcase_service;

require_once(__DIR__ . '/../../config.php');

require_login();
$courseid = required_param('course', PARAM_INT);
$course = get_course($courseid);
$context = context_course::instance($courseid);
require_capability('local/testcase_exchange:review', $context);

$PAGE->set_url(new moodle_url('/local/testcase_exchange/review.php', ['course' => $courseid]));
$PAGE->set_context($context);
$PAGE->set_title(get_string('reviewcontributions', 'local_testcase_exchange'));
$PAGE->set_heading($course->fullname . ' — ' . get_string('reviewcontributions', 'local_testcase_exchange'));

$connection = null;
try {
    $connection = external_database::connect();
    schema_manager::migrate($connection);
} catch (Throwable $e) {
    debugging($e->getMessage(), DEBUG_DEVELOPER);
    throw new moodle_exception('databaseunavailable', 'local_testcase_exchange');
}
$service = new testcase_service($connection);

if (data_submitted()) {
    require_sesskey();
    try {
        $service->review(
            required_param('contribution_id', PARAM_INT),
            $courseid,
            (int) $USER->id,
            required_param('decision', PARAM_ALPHAEXT),
            optional_param('review_note', '', PARAM_TEXT)
        );
        redirect(
            $PAGE->url,
            get_string('reviewsaved', 'local_testcase_exchange'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    } catch (Throwable $e) {
        $message = $e instanceof moodle_exception ? $e->getMessage() :
            get_string('unexpectederror', 'local_testcase_exchange');
        if (!($e instanceof moodle_exception)) {
            debugging($e->getMessage(), DEBUG_DEVELOPER);
        }
        redirect($PAGE->url, $message, null, \core\output\notification::NOTIFY_ERROR);
    }
}

$items = $service->pending_contributions($courseid);
echo $OUTPUT->header();
echo html_writer::link(
    new moodle_url('/local/testcase_exchange/index.php', ['course' => $courseid]),
    get_string('backtodashboard', 'local_testcase_exchange'),
    ['class' => 'btn btn-secondary mb-3']
);
foreach ($items as &$item) {
    $item['quiz_question'] = $item['quiz_id'] . '/' . $item['question_id'];
}
unset($item);
echo $OUTPUT->render_from_template('local_testcase_exchange/review', [
    'sesskey' => sesskey(),
    'items' => $items,
    'has_items' => !empty($items),
]);
$connection->close();
echo $OUTPUT->footer();
