<?php

if (!defined('ABSPATH')) {
    exit;
}

final class WEM_Movement_Ledger
{
    public function append($movement)
    {
        global $wpdb;

        $movement_id = wp_generate_uuid4();
        $table_name = WEM_Movement_Schema::table_name();

        $inserted = $wpdb->insert(
            $table_name,
            array(
                'movement_id' => $movement_id,
                'guest_id' => (int) $movement['guest_id'],
                'event_term_id' => (int) $movement['event_term_id'],
                'movement_type' => (string) $movement['movement_type'],
                'occurred_at' => (string) $movement['occurred_at'],
                'actor_user_id' => (int) $movement['actor_user_id'],
                'source' => (string) $movement['source'],
                'decision' => (string) $movement['decision'],
            ),
            array('%s', '%d', '%d', '%s', '%s', '%d', '%s', '%s')
        );

        if ($inserted === false) {
            return false;
        }

        return array(
            'sequence' => (int) $wpdb->insert_id,
            'movement_id' => $movement_id,
        );
    }

    public function find_by_guest_event($guest_id, $event_term_id)
    {
        global $wpdb;

        $table_name = WEM_Movement_Schema::table_name();

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT sequence, movement_id, guest_id, event_term_id, movement_type, occurred_at, actor_user_id, source, decision
                FROM {$table_name}
                WHERE guest_id = %d AND event_term_id = %d
                ORDER BY sequence ASC",
                (int) $guest_id,
                (int) $event_term_id
            ),
            ARRAY_A
        );

        return is_array($rows) ? $rows : array();
    }
}
