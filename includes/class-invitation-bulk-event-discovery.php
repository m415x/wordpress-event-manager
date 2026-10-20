<?php

/**
 * WEM-73: discover published guests that belong to exactly one event.
 * Read-only; no credential creation, revocation, or export.
 */
final class WEM_Invitation_Bulk_Event_Discovery
{
    public function guests(int $event_id): array
    {
        if ($event_id <= 0 || !term_exists($event_id, 'evento')) {
            throw new RuntimeException('Invalid invitation event.');
        }

        $ids = get_posts(array(
            'post_type' => 'invitado',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'orderby' => 'ID',
            'order' => 'ASC',
            'suppress_filters' => false,
            'tax_query' => array(
                array(
                    'taxonomy' => 'evento',
                    'field' => 'term_id',
                    'terms' => array($event_id),
                    'include_children' => false,
                ),
            ),
        ));

        $eligible = array();
        foreach ($ids as $id) {
            $guest_id = (int) $id;
            $terms = wp_get_object_terms($guest_id, 'evento', array('fields' => 'ids'));
            if (is_wp_error($terms) || count($terms) !== 1
                || (int) $terms[0] !== $event_id) {
                continue;
            }
            $eligible[$guest_id] = $guest_id;
        }

        sort($eligible, SORT_NUMERIC);
        return array_values($eligible);
    }
}
