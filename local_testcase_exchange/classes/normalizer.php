<?php
namespace local_testcase_exchange;

defined('MOODLE_INTERNAL') || die();

/** Input normalisation and fingerprinting. */
final class normalizer {
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

    public static function fingerprint(string $normalized): string {
        return hash('sha256', $normalized);
    }

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
