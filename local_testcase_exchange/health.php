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
 * Administrator service health page.
 *
 * @package    local_testcase_exchange
 * @copyright  2026 Nguyen Quoc Thinh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/filelib.php');

require_login();
require_capability('moodle/site:config', context_system::instance());

$PAGE->set_url(new moodle_url('/local/testcase_exchange/health.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_title(get_string('healthcheck', 'local_testcase_exchange'));
$PAGE->set_heading(get_string('healthcheck', 'local_testcase_exchange'));

$dbstatus = ['ok' => false, 'detail' => get_string('unavailable', 'local_testcase_exchange')];
try {
    global $DB;
    $dbman = $DB->get_manager();
    if ($dbman->table_exists('local_tce_runs') && $dbman->table_exists('local_tce_contributions')) {
        $pluginversion = (string) get_config('local_testcase_exchange', 'version');
        $dbstatus = ['ok' => true, 'detail' => get_string('schemaversion', 'local_testcase_exchange', $pluginversion)];
    }
} catch (Throwable $e) {
    debugging($e->getMessage(), DEBUG_DEVELOPER);
}

$nodes = [];
foreach (local_testcase_exchange_get_jobe_servers() as $server) {
    $languagesurl = preg_replace('~/runs$~', '/languages', $server['runsurl']);
    $curl = new curl();
    $options = ['CURLOPT_CONNECTTIMEOUT' => 3, 'CURLOPT_TIMEOUT' => 5];
    $apikey = (string) get_config('local_testcase_exchange', 'jobe_api_key');
    if ($apikey !== '') {
        $curl->setHeader(['X-API-KEY: ' . $apikey]);
    }
    $payload = $curl->get($languagesurl, [], $options);
    $nodes[] = [
        'baseurl' => $server['baseurl'],
        'runsurl' => $server['runsurl'],
        'ok' => ($curl->get_errno() === 0),
        'detail' => $curl->get_errno() === 0 ? $payload : $curl->error,
    ];
}

echo $OUTPUT->header();
echo $OUTPUT->box_start('generalbox');
echo html_writer::tag('h3', get_string('healthcheck', 'local_testcase_exchange'));
echo html_writer::tag('p', 'Database: ' . ($dbstatus['ok'] ? 'OK' : 'Error') . ' — ' . s($dbstatus['detail']));
echo html_writer::tag('h4', 'Jobe sandboxes');
echo html_writer::start_tag('ul');
foreach ($nodes as $node) {
    echo html_writer::tag(
        'li',
        s($node['baseurl']) . ': ' . ($node['ok'] ? 'OK' : 'Error') . ' — ' . s($node['detail'])
    );
}
echo html_writer::end_tag('ul');
echo $OUTPUT->box_end();
echo $OUTPUT->footer();
