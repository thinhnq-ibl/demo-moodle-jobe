<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace local_testcase_exchange;

use core\hook\output\before_footer_html_generation;

/**
 * Output hook callbacks.
 *
 * @package    local_testcase_exchange
 * @copyright  2026 Nguyen Quoc Thinh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hook_callbacks {
    /**
     * Add the post-Quiz contribution call to action.
     *
     * @param before_footer_html_generation $hook Footer generation hook.
     */
    public static function before_footer_html_generation(before_footer_html_generation $hook): void {
        $hook->add_html(local_testcase_exchange_before_footer());
    }
}
