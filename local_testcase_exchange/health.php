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
use local_testcase_exchange\external_database;
use local_testcase_exchange\schema_manager;

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
    $connection = external_database::connect();
    schema_manager::migrate($connection);
    $version = $connection->query(
        'SELECT version FROM schema_migrations ORDER BY applied_at DESC, version DESC LIMIT 1'
    )->fetch_assoc()['version'] ?? 'unknown';
    $dbstatus = ['ok' => true, 'detail' => get_string('schemaversion', 'local_testcase_exchange', $version)];
    $connection->close();
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
    $info = $curl->get_info();
    $languages = json_decode((string) $payload, true);
    $languageids = [];
    if (is_array($languages)) {
        foreach ($languages as $key => $language) {
            $languageids[] = is_array($language) ? (string) ($language['language_id'] ?? $key) : (string) $language;
        }
    }
    $nodes[] = [
        'label' => $server['label'],
        'ok' => (int) ($info['http_code'] ?? 0) === 200 && is_array($languages),
        'languages' => implode(', ', array_slice($languageids, 0, 30)),
    ];
}

echo $OUTPUT->header();
echo html_writer::tag('h3', get_string('databasehealth', 'local_testcase_exchange'));
echo $OUTPUT->notification($dbstatus['detail'], $dbstatus['ok'] ? 'notifysuccess' : 'notifyproblem');
echo html_writer::tag('h3', get_string('jobehealth', 'local_testcase_exchange'));
$table = new html_table();
$table->head = [get_string('server', 'local_testcase_exchange'), get_string('status'),
    get_string('languages', 'local_testcase_exchange')];
foreach ($nodes as $node) {
    $table->data[] = [s($node['label']), $node['ok'] ? get_string('available', 'local_testcase_exchange') :
        get_string('unavailable', 'local_testcase_exchange'), s($node['languages'])];
}
echo html_writer::table($table);
echo $OUTPUT->footer();
