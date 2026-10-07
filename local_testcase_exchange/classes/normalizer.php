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
 * Input normalization utilities.
 *
 * @package    local_testcase_exchange
 * @copyright  2026 Nguyen Quoc Thinh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace local_testcase_exchange;

/**
 * Input normalisation and fingerprinting.
 */
final class normalizer {
    /**
     * Normalize input according to the question policy.
     *
     * @param string $input Raw input.
     * @param string $mode Normalization mode.
     * @return string Normalized input.
     */
    public static function normalize(string $input, string $mode): string {
        $input = str_replace(["\r\n", "\r"], "\n", $input);
        if ($mode === 'trim') {
            $lines = array_map('rtrim', explode("\n", trim($input)));
            return implode("\n", $lines);
        }
        if ($mode === 'json') {
            $decoded = json_decode($input, true, 512, JSON_THROW_ON_ERROR);
            self::sort_recursive($decoded);
            return json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        return $input;
    }

    /**
     * Create a stable SHA-256 fingerprint.
     *
     * @param string $normalized Normalized input.
     * @return string Hexadecimal fingerprint.
     */
    public static function fingerprint(string $normalized): string {
        return hash('sha256', $normalized);
    }

    /**
     * Sort associative JSON structures recursively.
     *
     * @param mixed $value Value to sort in place.
     */
    private static function sort_recursive(&$value): void {
        if (!is_array($value)) {
            return;
        }
        foreach ($value as &$item) {
            self::sort_recursive($item);
        }
        if (!array_is_list($value)) {
            ksort($value);
        }
    }
}
