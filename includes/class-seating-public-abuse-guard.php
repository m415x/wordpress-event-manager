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

        // The unique option_name row serializes increments. LAST_INSERT_ID(expr)
        // records THIS connection's awarded sequence, rather than reading a
        // subsequently changed shared value from another worker.
        $updated = $wpdb->query($wpdb->prepare(
            "INSERT INTO {$table} (option_name, option_value, autoload)
             VALUES (%s, %s, 'no')
             ON DUPLICATE KEY UPDATE option_value =
               CONCAT(
                 LAST_INSERT_ID(
                   IF(CAST(SUBSTRING_INDEX(option_value, ':', -1) AS UNSIGNED) <= %d,
                      1,
                      CAST(SUBSTRING_INDEX(option_value, ':', 1) AS UNSIGNED) + 1)
                 ),
                 ':',
                 IF(CAST(SUBSTRING_INDEX(option_value, ':', -1) AS UNSIGNED) <= %d,
                    %d,
                    CAST(SUBSTRING_INDEX(option_value, ':', -1) AS UNSIGNED))
               )",
            $option, '1:' . $expires, $now, $now, $expires
        ));
        if ($updated === false) {
            return false;
        }

        // A fresh INSERT (affected rows=1) owns sequence 1; an UPDATE uses
        // the connection-local sequence from its atomic row update.
        if ($updated === 1) {
            return true;
        }
        $sequence = $wpdb->get_var('SELECT LAST_INSERT_ID()');
        if ($sequence === null || $wpdb->last_error !== '') {
            return false;
        }
        return (int)$sequence > 0 && (int)$sequence <= $limit;
    }
}
