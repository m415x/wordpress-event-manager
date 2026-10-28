<?php

if (!defined('ABSPATH')) {
    exit;
}

/** WEM-90/T3: sole consent authority is the existing guest/event preference row. */
final class WEM_Seating_Bearer_Consent
{
    public function get_for_bearer($bearer)
    {
        $identity = (new WEM_Seating_Bearer_Authorization())->resolve($bearer);
        if ($identity === null) {
            return null;
        }

        global $wpdb;
        $table = WEM_Seating_Visibility_Consent_Schema::table_name();
        $value = $wpdb->get_var($wpdb->prepare(
            "SELECT visibility_opt_in FROM {$table} WHERE guest_id=%d AND event_term_id=%d",
            $identity['guest_id'], $identity['event_term_id']
        ));
        if ($wpdb->last_error !== '') {
            throw new RuntimeException('Visibility preference unavailable.');
        }
        return $value !== null && (int) $value === 1;
    }

    public function set_for_bearer($bearer, $visible)
    {
        if (!is_bool($visible)) {
            return false;
        }
        $identity = (new WEM_Seating_Bearer_Authorization())->resolve($bearer);
        if ($identity === null) {
            return false;
        }

        global $wpdb;
        $table = WEM_Seating_Visibility_Consent_Schema::table_name();
        $result = $wpdb->query($wpdb->prepare(
            "INSERT INTO {$table} (guest_id, event_term_id, visibility_opt_in, updated_at)
             VALUES (%d, %d, %d, %s)
             ON DUPLICATE KEY UPDATE visibility_opt_in=VALUES(visibility_opt_in), updated_at=VALUES(updated_at)",
            $identity['guest_id'], $identity['event_term_id'], $visible ? 1 : 0,
            gmdate('Y-m-d H:i:s')
        ));
        if ($result === false) {
            throw new RuntimeException('Visibility preference could not be saved.');
        }
        return true;
    }
}
