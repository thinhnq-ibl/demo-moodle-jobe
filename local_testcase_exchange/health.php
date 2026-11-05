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

$checktime = date('H:i:s d/m/Y');

$dbstatus = [
    'ok' => false,
    'source' => 'Cơ sở dữ liệu Moodle (Bảng nội bộ local_tce_*)',
    'detail' => get_string('unavailable', 'local_testcase_exchange'),
];
try {
    global $DB;
    $dbman = $DB->get_manager();
    $requiredtables = ['local_tce_runs', 'local_tce_contributions', 'local_tce_canonical', 'local_tce_rewards', 'local_tce_reviews', 'local_tce_seeds', 'local_tce_policies'];
    $allok = true;
    foreach ($requiredtables as $tbl) {
        if (!$dbman->table_exists($tbl)) {
            $allok = false;
            break;
        }
    }
    if ($allok) {
        $pluginversion = (string) get_config('local_testcase_exchange', 'version');
        $dbstatus = [
            'ok' => true,
            'source' => 'Cơ sở dữ liệu Moodle (Bảng nội bộ local_tce_*)',
            'detail' => 'Tất cả 7 bảng dữ liệu nội bộ đã sẵn sàng (Schema version: ' . $pluginversion . '). Kết nối MariaDB chuẩn Moodle hoạt động ổn định.',
        ];
    }
} catch (Throwable $e) {
    debugging($e->getMessage(), DEBUG_DEVELOPER);
    $dbstatus = [
        'ok' => false,
        'source' => 'Cơ sở dữ liệu Moodle (Bảng nội bộ local_tce_*)',
        'detail' => $e->getMessage(),
    ];
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
    $startcheck = microtime(true);
    $payload = $curl->get($languagesurl, [], $options);
    $duration = round((microtime(true) - $startcheck) * 1000, 1);
    $errno = $curl->get_errno();

    $languagesformatted = [];
    $rawjson = '';
    $parsedok = false;
    if ($errno === 0 && !empty($payload)) {
        $rawjson = (string) $payload;
        $decoded = json_decode($payload, true);
        if (is_array($decoded)) {
            $parsedok = true;
            foreach ($decoded as $langitem) {
                if (is_array($langitem) && count($langitem) >= 2) {
                    $langname = ucfirst($langitem[0]);
                    $langver = $langitem[1];
                    $languagesformatted[] = "{$langname} {$langver}";
                }
            }
        }
    }

    $nodes[] = [
        'baseurl' => $server['baseurl'],
        'runsurl' => $server['runsurl'],
        'ok' => ($errno === 0 && $parsedok),
        'check_time' => $checktime,
        'duration_ms' => $duration,
        'languages_formatted' => !empty($languagesformatted) ? implode(', ', $languagesformatted) : '',
        'language_count' => count($languagesformatted),
        'raw_json' => $rawjson,
        'error' => $errno !== 0 ? ($curl->error ?: 'Connection failed') : (!$parsedok ? 'Invalid JSON response' : ''),
    ];
}

echo $OUTPUT->header();
?>
<div class="local-testcase-exchange-root py-2">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 pb-2 border-bottom">
        <div>
            <h4 class="mb-1 font-weight-bold text-dark">
                <i class="fa fa-heartbeat text-danger mr-2"></i>Chẩn đoán & Kiểm tra sức khỏe hệ thống
            </h4>
            <div class="text-muted small">
                Thời điểm kiểm tra: <strong><?php echo s($checktime); ?></strong>
            </div>
        </div>
        <div class="mt-2 mt-sm-0">
            <a href="<?php echo (new moodle_url('/local/testcase_exchange/index.php'))->out(false); ?>" class="btn btn-outline-secondary btn-sm font-weight-bold">
                &larr; <?php echo s(get_string('backtodashboard', 'local_testcase_exchange')); ?>
            </a>
        </div>
    </div>

    <!-- 1. Database Health Card -->
    <div class="card mb-4 border shadow-sm" style="border-radius: 8px;">
        <div class="card-header bg-white py-2 border-bottom font-weight-bold text-dark d-flex align-items-center">
            <i class="fa fa-database text-primary mr-2"></i>
            <span>Nguồn dữ liệu: <?php echo s($dbstatus['source']); ?></span>
        </div>
        <div class="card-body p-3">
            <div class="d-flex align-items-start">
                <span class="badge <?php echo $dbstatus['ok'] ? 'badge-success' : 'badge-danger'; ?> p-2 mr-3" style="font-size: 0.95rem;">
                    <?php echo $dbstatus['ok'] ? '<i class="fa fa-check mr-1"></i> Hoạt động tốt' : '<i class="fa fa-times mr-1"></i> Có lỗi'; ?>
                </span>
                <div class="text-dark" style="color: #334155; line-height: 1.5;">
                    <div><?php echo s($dbstatus['detail']); ?></div>
                    <small class="text-muted">Kiểm tra lúc: <?php echo s($checktime); ?></small>
                </div>
            </div>
        </div>
    </div>

    <!-- 2. Jobe Sandbox Cluster Card -->
    <div class="card border shadow-sm" style="border-radius: 8px;">
        <div class="card-header bg-white py-2 border-bottom font-weight-bold text-dark d-flex align-items-center justify-content-between">
            <div>
                <i class="fa fa-server text-info mr-2"></i>
                <span>Cụm Jobe Sandbox song song (<?php echo count($nodes); ?> nodes)</span>
            </div>
            <span class="badge badge-light border text-muted small">Thời gian kiểm tra: <?php echo s($checktime); ?></span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive mb-0">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light text-dark">
                        <tr>
                            <th style="min-width: 200px;">Máy chủ (Node)</th>
                            <th style="width: 140px;">Trạng thái</th>
                            <th style="min-width: 320px;">Ngôn ngữ hỗ trợ & Chi tiết kỹ thuật</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($nodes)): ?>
                            <tr>
                                <td colspan="3" class="text-center text-muted py-4">Chưa có máy chủ Jobe nào được cấu hình trong hệ thống.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($nodes as $node): ?>
                                <tr>
                                    <td>
                                        <div class="font-weight-bold text-dark"><?php echo s($node['baseurl']); ?></div>
                                        <div class="small text-muted font-monospace"><?php echo s($node['runsurl']); ?></div>
                                        <small class="text-muted d-block mt-1">Độ trễ: <strong><?php echo s($node['duration_ms']); ?> ms</strong></small>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo $node['ok'] ? 'badge-success' : 'badge-danger'; ?> p-2 d-inline-flex align-items-center">
                                            <?php echo $node['ok'] ? '<i class="fa fa-check mr-1"></i> Hoạt động' : '<i class="fa fa-times mr-1"></i> Ngoại tuyến'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($node['ok']): ?>
                                            <div class="text-success font-weight-bold small mb-1">
                                                <i class="fa fa-check-circle mr-1"></i>Kết nối thành công (Lấy được <?php echo $node['language_count']; ?> ngôn ngữ hỗ trợ — chưa gửi lệnh chạy code)
                                            </div>
                                            <div class="text-dark small mb-2 font-weight-bold" style="color: #1e293b;">
                                                <?php echo s($node['languages_formatted']); ?>
                                            </div>
                                            <details class="text-muted small">
                                                <summary class="cursor-pointer font-weight-bold text-primary">Chi tiết kỹ thuật (JSON thô)</summary>
                                                <pre class="bg-light p-2 border rounded font-monospace small mb-0 mt-1" style="max-height: 100px; overflow-y: auto;"><?php echo s($node['raw_json']); ?></pre>
                                            </details>
                                        <?php else: ?>
                                            <div class="text-danger small font-weight-bold">
                                                <i class="fa fa-exclamation-triangle mr-1"></i>Lỗi kết nối: <?php echo s($node['error']); ?>
                                            </div>
                                        <?php endif; ?>
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
