<?php

declare(strict_types=1);

namespace WEM\Tests\Integration;

/**
 * Temporary, diagnostic-only observations. Never installs or mutates tables.
 */
final class MovementIsolationProbe
{
    public static function capture(string $phase, ?int $guestId = null, ?int $eventId = null): void
    {
        global $wpdb;

        $database = $wpdb->get_var('SELECT DATABASE()');
        $transaction = $wpdb->get_var('SELECT @@in_transaction');
        $table = \WEM_Movement_Schema::table_name();
        $exists = $wpdb->get_var($wpdb->prepare(
            'SHOW TABLES LIKE %s',
            $table
        ));
        $definition = null;
        $rows = null;
        if ($exists === $table) {
            $create = $wpdb->get_row("SHOW CREATE TABLE `{$table}`", ARRAY_N);
            $definition = is_array($create) ? ($create[1] ?? null) : null;
            if ($guestId !== null && $eventId !== null) {
                $rows = $wpdb->get_results($wpdb->prepare(
                    "SELECT sequence, movement_id, guest_id, event_term_id, movement_type
                     FROM {$table}
                     WHERE guest_id = %d AND event_term_id = %d
                     ORDER BY sequence",
                    $guestId,
                    $eventId
                ), ARRAY_A);
            }
        }

        fwrite(STDERR, "\nWEM-74 MOVEMENT ISOLATION " . wp_json_encode([
            'phase' => $phase,
            'database' => $database,
            'table' => $table,
            'exists' => $exists === $table,
            'transaction' => $transaction,
            'guest_id' => $guestId,
            'event_term_id' => $eventId,
            'rows' => $rows,
            'create_table' => $definition,
            'error' => $wpdb->last_error,
        ], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . "\n");
    }
}
