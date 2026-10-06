<?php

if (!defined('ABSPATH')) {
    exit;
}

final class WEM_Movement_Service
{
    public function checkin($guest_id, $event_term_id, $actor_user_id, $source)
    {
        global $wpdb;

        $guest_id = (int) $guest_id;
        $event_term_id = (int) $event_term_id;
        $actor_user_id = (int) $actor_user_id;

        $wpdb->query('START TRANSACTION');

        try {
            $locked_guest_id = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT ID FROM {$wpdb->posts} WHERE ID = %d FOR UPDATE",
                    $guest_id
                )
            );

            if ((int) $locked_guest_id !== $guest_id) {
                throw new RuntimeException('Guest could not be serialized.');
            }

            $already_inside = get_post_meta($guest_id, 'wem_checkin', true)
                && !get_post_meta($guest_id, 'wem_checkout', true);

            if ($already_inside) {
                throw new RuntimeException('Guest is already inside.');
            }

            $occurred_at = current_time('Y-m-d H:i:s');

            $ledger = new WEM_Movement_Ledger();
            $movement = $ledger->append(
                array(
                    'guest_id' => $guest_id,
                    'event_term_id' => $event_term_id,
                    'movement_type' => 'checkin',
                    'occurred_at' => $occurred_at,
                    'actor_user_id' => $actor_user_id,
                    'source' => (string) $source,
                    'decision' => 'accepted',
                )
            );

            if ($movement === false) {
                throw new RuntimeException('Unable to append movement.');
            }

            if (update_post_meta($guest_id, 'wem_checkin', 1) === false) {
                throw new RuntimeException('Unable to update checkin projection.');
            }

            if (update_post_meta($guest_id, 'wem_checkin_at', $occurred_at) === false) {
                throw new RuntimeException('Unable to update checkin time projection.');
            }

            $actor = get_userdata($actor_user_id);
            $operator = $actor && $actor->exists()
                ? ($actor->display_name ?: $actor->user_login)
                : (string) $actor_user_id;

            if (update_post_meta($guest_id, 'wem_checkin_by', $operator) === false) {
                throw new RuntimeException('Unable to update checkin actor projection.');
            }

            delete_post_meta($guest_id, 'wem_checkout');
            delete_post_meta($guest_id, 'wem_checkout_at');
            delete_post_meta($guest_id, 'wem_checkout_by');

            $wpdb->query('COMMIT');

            return array(
                'ok' => true,
                'state' => 'inside',
                'movement' => $movement,
            );
        } catch (Throwable $exception) {
            $wpdb->query('ROLLBACK');
            throw $exception;
        }
    }
}
