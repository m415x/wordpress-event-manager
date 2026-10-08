<?php

if (!defined('ABSPATH')) {
    exit;
}

final class WEM_Public_Invitation_Projection
{
    public function build($guest_id, $event_term_id)
    {
        $guest_id = (int) $guest_id;
        $event_term_id = (int) $event_term_id;

        if ($guest_id <= 0 || get_post_type($guest_id) !== 'invitado') {
            return null;
        }

        if ($event_term_id <= 0) {
            return null;
        }

        $terms = get_the_terms($guest_id, 'evento');

        if (!is_array($terms) || count($terms) !== 1) {
            return null;
        }

        $event = reset($terms);

        if (
            !$event
            || is_wp_error($event)
            || !isset($event->term_id, $event->name)
            || (int) $event->term_id !== $event_term_id
        ) {
            return null;
        }

        return array(
            'guest_name' => (string) get_post_meta($guest_id, 'wem_nombre', true),
            'event_name' => (string) $event->name,
        );
    }
}
