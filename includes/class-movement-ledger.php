<?php

if (!defined('ABSPATH')) {
    exit;
}

final class WEM_Movement_Ledger
{
    public function append($movement)
    {
        $this->assert_canonical_movement($movement);

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

    private function assert_canonical_movement($movement)
    {
        if (!is_array($movement)) {
            throw new InvalidArgumentException('Movement must be an array.');
        }

        foreach (array('guest_id', 'event_term_id', 'movement_type', 'occurred_at', 'actor_user_id', 'source', 'decision') as $required) {
            if (!array_key_exists($required, $movement)) {
                throw new InvalidArgumentException('Missing movement field: ' . $required);
            }
        }

        if ((int) $movement['guest_id'] <= 0) {
            throw new InvalidArgumentException('guest_id must be a positive integer.');
        }

        if ((int) $movement['event_term_id'] <= 0) {
            throw new InvalidArgumentException('event_term_id must be a positive integer.');
        }

        if ((int) $movement['actor_user_id'] <= 0) {
            throw new InvalidArgumentException('actor_user_id must be a positive integer.');
        }

        if (!in_array((string) $movement['movement_type'], array('checkin', 'checkout', 'reentry'), true)) {
            throw new InvalidArgumentException('Unsupported movement_type.');
        }

        if ((string) $movement['source'] !== 'staff_web') {
            throw new InvalidArgumentException('Unsupported movement source.');
        }

        if ((string) $movement['decision'] !== 'accepted') {
            throw new InvalidArgumentException('Movement ledger stores accepted decisions only.');
        }
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
