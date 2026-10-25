<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * WEM-81 transaction boundary for seating writes on one wpdb connection.
 *
 * Callers are responsible for the WEM-75 identity/lock/authorization protocol.
 * This boundary owns only start/commit/rollback and never swallows exceptions.
 */
final class WEM_Seating_Transaction
{
    public static function run(callable $work)
    {
        global $wpdb;

        // Never silently commit or roll back a transaction owned by another caller.
        $alreadyActive = $wpdb->get_var('SELECT @@in_transaction');
        if ($alreadyActive === null || (int) $alreadyActive !== 0) {
            throw new RuntimeException('Seating transaction requires an idle database connection.');
        }

        if ($wpdb->query('START TRANSACTION') === false) {
            throw new RuntimeException('Unable to begin seating transaction.');
        }

        try {
            $value = $work();

            if ($wpdb->query('COMMIT') === false) {
                throw new RuntimeException('Seating transaction commit failed; outcome is unknown.');
            }

            return $value;
        } catch (Throwable $exception) {
            $rolledBack = $wpdb->query('ROLLBACK');
            if ($rolledBack === false) {
                throw new RuntimeException('Seating transaction rollback failed; connection unavailable.', 0, $exception);
            }

            throw $exception;
        }
    }
}
