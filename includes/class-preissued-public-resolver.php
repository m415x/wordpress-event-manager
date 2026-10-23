<?php

if (!defined('ABSPATH')) {
    exit;
}

/** Fail-closed P0 public resolution; never grants check-in or seating rights. */
final class WEM_Preissued_Public_Resolver
{
    public function resolve($token): ?array
    {
        global $wpdb;
        if (!is_string($token) || !preg_match('/^[A-Za-z0-9_-]{43}$/D', $token)) {
            return null;
        }
        $table = WEM_Preissued_Invitation_Schema::table_name();
        $digest = hash('sha256', $token);
        $record = $wpdb->get_row($wpdb->prepare(
            "SELECT event_term_id, state, assigned_guest_id FROM {$table} WHERE token_digest = %s",
            $digest
        ), ARRAY_A);
        if (!is_array($record) || $record['state'] !== 'assigned'
            || (int) $record['assigned_guest_id'] <= 0) {
            return null;
        }
        $guestId = (int) $record['assigned_guest_id'];
        if (get_post_type($guestId) !== 'invitado' || get_post_status($guestId) !== 'publish') {
            return null;
        }
        $terms = wp_get_object_terms($guestId, 'evento', ['fields' => 'ids']);
        if (is_wp_error($terms) || count($terms) !== 1
            || (int) $terms[0] !== (int) $record['event_term_id']) {
            return null;
        }
        return [
            'guest_id' => $guestId,
            'event_term_id' => (int) $record['event_term_id'],
        ];
    }
}
