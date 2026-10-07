<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Tests for testcase input normalization.
 *
 * @package    local_testcase_exchange
 * @copyright  2026 Nguyen Quoc Thinh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_testcase_exchange;

/**
 * Test normalization and stable fingerprint generation.
 *
 * @covers \local_testcase_exchange\normalizer
 */
final class normalizer_test extends \advanced_testcase {

    /** Test raw mode only normalizes line endings. */
    public function test_raw_normalization(): void {
        $this->assertSame(" a\nb ", normalizer::normalize(" a\r\nb ", 'raw'));
    }

    /** Test trim mode removes outer whitespace and trailing whitespace per line. */
    public function test_trim_normalization(): void {
        $this->assertSame("a\n b", normalizer::normalize("  a  \r\n b \n", 'trim'));
    }

    /** Test equivalent JSON objects produce the same canonical representation. */
    public function test_json_normalization_is_stable(): void {
        $first = normalizer::normalize('{"b":2,"a":{"d":4,"c":3}}', 'json');
        $second = normalizer::normalize('{"a":{"c":3,"d":4},"b":2}', 'json');
        $this->assertSame($first, $second);
        $this->assertSame(normalizer::fingerprint($first), normalizer::fingerprint($second));
    }

    /** Test invalid JSON is rejected. */
    public function test_invalid_json_throws_exception(): void {
        $this->expectException(\JsonException::class);
        normalizer::normalize('{invalid', 'json');
    }
}
