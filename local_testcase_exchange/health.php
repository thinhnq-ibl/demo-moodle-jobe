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
require_once(__DIR__ . '/lib.php');

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
    $dbstatus = ['ok' => false, 'detail' => $e->getMessage()];
}

$nodes = [];
$jobeservers = local_testcase_exchange_get_jobe_servers();
foreach ($jobeservers as $server) {
    $languagesurl = preg_replace('~/runs$~', '/languages', $server['runsurl']);
    $curl = new curl();
    $options = ['CURLOPT_CONNECTTIMEOUT' => 3, 'CURLOPT_TIMEOUT' => 5];
    $apikey = (string) get_config('local_testcase_exchange', 'jobe_api_key');
    if ($apikey !== '') {
        $curl->setHeader(['X-API-KEY: ' . $apikey]);
    }
    $payload = $curl->get($languagesurl, [], $options);
    $errno = $curl->get_errno();
    $nodes[] = [
        'baseurl' => $server['baseurl'],
        'runsurl' => $server['runsurl'],
        'ok' => ($errno === 0),
        'detail' => $errno === 0 ? $payload : ($curl->error ?: 'Connection failed'),
    ];
}

echo $OUTPUT->header();
?>
<div class="local-testcase-exchange-root py-2">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="mb-0 font-weight-bold text-dark">
            <i class="fa fa-heartbeat text-danger mr-2"></i><?php echo s(get_string('healthcheck', 'local_testcase_exchange')); ?>
        </h3>
        <a href="<?php echo (new moodle_url('/local/testcase_exchange/index.php'))->out(false); ?>" class="btn btn-outline-secondary btn-sm font-weight-bold">
            &larr; <?php echo s(get_string('backtodashboard', 'local_testcase_exchange')); ?>
        </a>
    </div>

    <div class="card mb-4 border-0 shadow-sm" style="border-radius: 8px; border: 1px solid #e2e8f0;">
        <div class="card-header bg-white py-3 border-bottom font-weight-bold text-dark d-flex align-items-center">
            <i class="fa fa-database text-primary mr-2"></i>
            <span><?php echo s(get_string('databasehealth', 'local_testcase_exchange')); ?></span>
        </div>
        <div class="card-body">
            <div class="d-flex align-items-center">
                <span class="badge <?php echo $dbstatus['ok'] ? 'badge-success' : 'badge-danger'; ?> p-2 mr-3" style="font-size: 0.95rem;">
                    <?php echo $dbstatus['ok'] ? '<i class="fa fa-check mr-1"></i> OK' : '<i class="fa fa-times mr-1"></i> Error'; ?>
                </span>
                <span class="text-secondary"><?php echo s($dbstatus['detail']); ?></span>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm" style="border-radius: 8px; border: 1px solid #e2e8f0;">
        <div class="card-header bg-white py-3 border-bottom font-weight-bold text-dark d-flex align-items-center">
            <i class="fa fa-server text-info mr-2"></i>
            <span><?php echo s(get_string('jobehealth', 'local_testcase_exchange')); ?> (<?php echo count($nodes); ?> nodes)</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive mb-0">
                <table class="table table-hover mb-0">
                    <thead class="bg-light text-muted">
                        <tr>
                            <th style="width: 280px;"><?php echo s(get_string('server', 'local_testcase_exchange')); ?></th>
                            <th style="width: 130px;"><?php echo s(get_string('status', 'core')); ?></th>
                            <th><?php echo s(get_string('languages', 'local_testcase_exchange')); ?> / Chi tiết</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($nodes)): ?>
                            <tr>
                                <td colspan="3" class="text-center text-muted py-4">Chưa có máy chủ Jobe nào được cấu hình.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($nodes as $node): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo s($node['baseurl']); ?></strong>
                                        <div class="small text-muted font-monospace"><?php echo s($node['runsurl']); ?></div>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo $node['ok'] ? 'badge-success' : 'badge-danger'; ?> p-2">
                                            <?php echo $node['ok'] ? '<i class="fa fa-check mr-1"></i> Hoạt động' : '<i class="fa fa-times mr-1"></i> Ngoại tuyến'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="font-monospace small bg-light p-2 rounded border" style="max-height: 120px; overflow-y: auto;">
                                            <?php echo s($node['detail']); ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php
echo $OUTPUT->footer();
