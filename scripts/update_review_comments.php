<?php
define('CLI_SCRIPT', true);
require_once(__DIR__ . '/config.php');

$contribs = $DB->get_records('local_tce_contributions', null, 'id DESC', 'id, user_id, status, rating, review_comment');
foreach ($contribs as $c) {
    echo "ID: {$c->id} | Status: {$c->status} | Rating: {$c->rating} | Comment: {$c->review_comment}\n";
    if (strpos($c->review_comment, 'bao quát') !== false || empty($c->review_comment)) {
        $c->review_comment = 'Testcase hợp lệ; có thể bổ sung trường hợp số nguyên tố và giá trị biên.';
        $DB->update_record('local_tce_contributions', $c);
        echo " -> Updated ID {$c->id} comment.\n";
    }
}
