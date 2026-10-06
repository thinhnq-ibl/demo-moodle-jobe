<?php
defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_testcase_exchange';
$plugin->version   = 2026100700;
$plugin->requires  = 2023100900; // Moodle 4.3+
$plugin->maturity  = MATURITY_ALPHA;
$plugin->release   = 'v1.1.0';
$plugin->dependencies = [
    'qtype_coderunner' => 2023050100,
];
