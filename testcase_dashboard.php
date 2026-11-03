<?php
// Backward compatibility redirect for legacy testcase_dashboard.php
require_once(__DIR__ . '/config.php');

$courseid = optional_param('course', 0, PARAM_INT);
$params = [];
if ($courseid > 0) {
    $params['course'] = $courseid;
}
$target = new moodle_url('/local/testcase_exchange/index.php', $params);
redirect($target);
