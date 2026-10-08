<?php

if (!defined('ABSPATH')) {
    exit;
}

final class WEM_Public_Invitation_Resolver
{
    public function resolve($token)
    {
        if (!is_string($token) || !preg_match('/^[A-Za-z0-9_-]{43}$/', $token)) {
            return null;
        }

        global $wpdb;

        $table_name = WEM_Invitation_Credential_Schema::table_name();
        $digest = hash('sha256', $token);

        $credential = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT guest_id, status, active_slot
                FROM {$table_name}
                WHERE token_digest = %s
                LIMIT 1",
                $digest
            ),
            ARRAY_A
        );

        if (!is_array($credential)) {
            return null;
        }

        if (
            (string) $credential['status'] !== 'active'
            || (int) $credential['active_slot'] !== 1
        ) {
            return null;
        }

        $guest_id = (int) $credential['guest_id'];

        if ($guest_id <= 0 || get_post_type($guest_id) !== 'invitado') {
            return null;
        }

        $terms = get_the_terms($guest_id, 'evento');

        if (!is_array($terms) || count($terms) !== 1) {
            return null;
        }

        $event = reset($terms);

        if (!$event || is_wp_error($event) || !isset($event->term_id)) {
            return null;
        }

        return array(
            'guest_id' => $guest_id,
            'event_term_id' => (int) $event->term_id,
        );
    }
}
