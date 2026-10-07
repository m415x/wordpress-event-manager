<?php

if (!defined('ABSPATH')) {
    exit;
}

final class WEM_Movement_Service
{
    public function checkin($guest_id, $event_term_id, $actor_user_id, $source)
    {
        return $this->transition(
            $guest_id,
            $event_term_id,
            $actor_user_id,
            $source,
            'checkin'
        );
    }

    public function checkout($guest_id, $event_term_id, $actor_user_id, $source)
    {
        return $this->transition(
            $guest_id,
            $event_term_id,
            $actor_user_id,
            $source,
            'checkout'
        );
    }

    public function reentry($guest_id, $event_term_id, $actor_user_id, $source)
    {
        return $this->transition(
            $guest_id,
            $event_term_id,
            $actor_user_id,
            $source,
            'reentry'
        );
    }

    public function reassign_event($guest_id, $current_event_term_id, $new_event_term_id)
    {
        global $wpdb;

        $guest_id = (int) $guest_id;
        $current_event_term_id = (int) $current_event_term_id;
        $new_event_term_id = (int) $new_event_term_id;

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

            $canonical_event_id = WEM_Authorization::get_guest_event_term_id($guest_id);
            if ((int) $canonical_event_id !== $current_event_term_id) {
                throw new RuntimeException('Guest event scope changed before reassignment.');
            }

            $projection_checkin = (bool) get_post_meta($guest_id, 'wem_checkin', true);
            $projection_checkout = (bool) get_post_meta($guest_id, 'wem_checkout', true);

            $ledger = new WEM_Movement_Ledger();
            $history = $ledger->find_by_guest_event($guest_id, $current_event_term_id);

            if (!$this->projection_matches_ledger($history, $projection_checkin, $projection_checkout)) {
                throw new RuntimeException('Movement ledger and projection are inconsistent.');
            }

            if ($projection_checkin && !$projection_checkout) {
                throw new RuntimeException('Guest must be outside before event reassignment.');
            }

            $new_event = get_term($new_event_term_id, 'evento');
            if (!$new_event || is_wp_error($new_event)) {
                throw new RuntimeException('Target event is invalid.');
            }

            $assigned = wp_set_object_terms(
                $guest_id,
                array($new_event_term_id),
                'evento',
                false
            );

            if (is_wp_error($assigned)) {
                throw new RuntimeException('Unable to reassign guest event.');
            }

            delete_post_meta($guest_id, 'wem_checkin');
            delete_post_meta($guest_id, 'wem_checkin_at');
            delete_post_meta($guest_id, 'wem_checkin_by');
            delete_post_meta($guest_id, 'wem_checkout');
            delete_post_meta($guest_id, 'wem_checkout_at');
            delete_post_meta($guest_id, 'wem_checkout_by');

            $wpdb->query('COMMIT');

            return array(
                'ok' => true,
                'state' => 'outside',
                'event_term_id' => $new_event_term_id,
            );
        } catch (Throwable $exception) {
            $wpdb->query('ROLLBACK');
            clean_object_term_cache($guest_id, 'invitado');
            wp_cache_delete($guest_id, 'post_meta');

            throw $exception;
        }
    }

    private function transition($guest_id, $event_term_id, $actor_user_id, $source, $movement_type)
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

            $projection_checkin = (bool) get_post_meta($guest_id, 'wem_checkin', true);
            $projection_checkout = (bool) get_post_meta($guest_id, 'wem_checkout', true);

            $ledger = new WEM_Movement_Ledger();
            $history = $ledger->find_by_guest_event($guest_id, $event_term_id);

            if (!$this->projection_matches_ledger($history, $projection_checkin, $projection_checkout)) {
                throw new RuntimeException('Movement ledger and projection are inconsistent.');
            }

            $this->assert_transition_is_valid(
                $movement_type,
                $history,
                $projection_checkin,
                $projection_checkout
            );

            $occurred_at = current_time('Y-m-d H:i:s');

            $movement = $ledger->append(
                array(
                    'guest_id' => $guest_id,
                    'event_term_id' => $event_term_id,
                    'movement_type' => $movement_type,
                    'occurred_at' => $occurred_at,
                    'actor_user_id' => $actor_user_id,
                    'source' => (string) $source,
                    'decision' => 'accepted',
                )
            );

            if ($movement === false) {
                throw new RuntimeException('Unable to append movement.');
            }

            $operator = $this->operator_label($actor_user_id);

            if ($movement_type === 'checkout') {
                $this->project_checkout($guest_id, $occurred_at, $operator);
                $state = 'outside';
            } else {
                $this->project_inside($guest_id, $occurred_at, $operator);
                $state = 'inside';
            }

            $wpdb->query('COMMIT');

            return array(
                'ok' => true,
                'state' => $state,
                'movement' => $movement,
            );
        } catch (Throwable $exception) {
            $wpdb->query('ROLLBACK');
            wp_cache_delete($guest_id, 'post_meta');

            throw $exception;
        }
    }

    private function assert_transition_is_valid($movement_type, $history, $projection_checkin, $projection_checkout)
    {
        $inside = $projection_checkin && !$projection_checkout;
        $outside_after_checkout = $projection_checkin && $projection_checkout;

        if ($movement_type === 'checkin') {
            if ($inside) {
                throw new RuntimeException('Guest is already inside.');
            }

            if ($outside_after_checkout) {
                throw new RuntimeException('Guest must reenter after checkout.');
            }

            if (!empty($history)) {
                throw new RuntimeException('Guest cannot perform an initial checkin after movement history exists.');
            }

            return;
        }

        if ($movement_type === 'checkout') {
            if (!$inside) {
                throw new RuntimeException('Guest is not inside.');
            }

            return;
        }

        if ($movement_type === 'reentry') {
            if (!$outside_after_checkout) {
                throw new RuntimeException('Guest cannot reenter.');
            }

            $last = $history[count($history) - 1] ?? array();
            if (($last['movement_type'] ?? '') !== 'checkout') {
                throw new RuntimeException('Guest cannot reenter.');
            }

            return;
        }

        throw new RuntimeException('Unsupported movement transition.');
    }

    private function project_inside($guest_id, $occurred_at, $operator)
    {
        $this->write_projection_meta(
            $guest_id,
            'wem_checkin',
            1,
            'Unable to update checkin projection.'
        );

        $this->write_projection_meta(
            $guest_id,
            'wem_checkin_at',
            $occurred_at,
            'Unable to update checkin time projection.'
        );

        $this->write_projection_meta(
            $guest_id,
            'wem_checkin_by',
            $operator,
            'Unable to update checkin actor projection.'
        );

        delete_post_meta($guest_id, 'wem_checkout');
        delete_post_meta($guest_id, 'wem_checkout_at');
        delete_post_meta($guest_id, 'wem_checkout_by');
    }

    private function project_checkout($guest_id, $occurred_at, $operator)
    {
        $this->write_projection_meta(
            $guest_id,
            'wem_checkout',
            1,
            'Unable to update checkout projection.'
        );

        $this->write_projection_meta(
            $guest_id,
            'wem_checkout_at',
            $occurred_at,
            'Unable to update checkout time projection.'
        );

        $this->write_projection_meta(
            $guest_id,
            'wem_checkout_by',
            $operator,
            'Unable to update checkout actor projection.'
        );
    }

    private function write_projection_meta($guest_id, $meta_key, $value, $error_message)
    {
        $updated = update_post_meta($guest_id, $meta_key, $value);

        if ($updated !== false) {
            return;
        }

        $stored = get_post_meta($guest_id, $meta_key, true);

        if ((string) $stored === (string) $value) {
            return;
        }

        throw new RuntimeException($error_message);
    }

    private function operator_label($actor_user_id)
    {
        $actor = get_userdata($actor_user_id);

        return $actor && $actor->exists()
            ? ($actor->display_name ?: $actor->user_login)
            : (string) $actor_user_id;
    }

    private function projection_matches_ledger($history, $projection_checkin, $projection_checkout)
    {
        if (empty($history)) {
            return !$projection_checkin && !$projection_checkout;
        }

        $last = $history[count($history) - 1];
        $movement_type = isset($last['movement_type']) ? (string) $last['movement_type'] : '';

        if ($movement_type === 'checkout') {
            return $projection_checkin && $projection_checkout;
        }

        if ($movement_type === 'checkin' || $movement_type === 'reentry') {
            return $projection_checkin && !$projection_checkout;
        }

        return false;
    }
}
