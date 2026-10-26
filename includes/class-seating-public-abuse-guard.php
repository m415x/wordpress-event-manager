<?php
if (!defined('ABSPATH')) { exit; }

/**
 * WEM-93/T6: bounded fixed-window counter with one atomic MySQL write.
 * Keys are HMAC'd with server-only material; no bearer or PII is stored.
 * No public HTTP operation is enabled by this class.
 */
final class WEM_Seating_Public_Abuse_Guard
{
    public function permits_request()
    {
        return false;
    }

    public function record_attempt($bucket, $limit, $windowSeconds)
    {
        if (!is_string($bucket) || $bucket === ''
            || !is_int($limit) || $limit < 1 || $limit > 1000
            || !is_int($windowSeconds) || $windowSeconds < 1 || $windowSeconds > 86400
        ) {
            return false;
        }

        global $wpdb;
        $option = 'wem_i3_rate_' . hash_hmac('sha256', $bucket, wp_salt('auth'));
        $now = time();
        $expires = $now + $windowSeconds;
        $table = $wpdb->options;

        // Unique option_name serializes increments across PHP workers. A
        // stale bucket resets in the same atomic SQL statement.
        $updated = $wpdb->query($wpdb->prepare(
            "INSERT INTO {$table} (option_name, option_value, autoload)
             VALUES (%s, %s, 'no')
             ON DUPLICATE KEY UPDATE option_value =
                IF(CAST(SUBSTRING_INDEX(option_value, ':', -1) AS UNSIGNED) <= %d,
                   VALUES(option_value),
                   CONCAT(CAST(SUBSTRING_INDEX(option_value, ':', 1) AS UNSIGNED) + 1,
                          ':', SUBSTRING_INDEX(option_value, ':', -1)))",
            $option, '1:' . $expires, $now
        ));
        if ($updated === false) {
            return false;
        }

        $value = $wpdb->get_var($wpdb->prepare(
            "SELECT option_value FROM {$table} WHERE option_name=%s",
            $option
        ));
        if (!is_string($value) || !preg_match('/^([0-9]+):([0-9]+)$/', $value, $matches)) {
            return false;
        }

        return (int)$matches[2] > $now && (int)$matches[1] <= $limit;
    }
}
