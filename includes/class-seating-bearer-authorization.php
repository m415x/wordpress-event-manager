<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * WEM-88/T1: internal bearer-to-canonical-seating-identity authorization boundary.
 *
 * Only this trusted context may be passed to subsequent seating application services.
 * No guest ID, event slug, nonce or WP login substitutes for a valid active bearer.
 * This class intentionally registers no public route and exposes no mutation.
 */
final class WEM_Seating_Bearer_Authorization
{
    public function resolve($bearer)
    {
        $context = (new WEM_Public_Invitation_Resolver())->resolve($bearer);

        if (!is_array($context)) {
            return null;
        }

        $guestId = $context['guest_id'] ?? null;
        $eventId = $context['event_term_id'] ?? null;

        if (!is_int($guestId) || $guestId < 1 || !is_int($eventId) || $eventId < 1) {
            return null;
        }

        return array(
            'guest_id' => $guestId,
            'event_term_id' => $eventId,
        );
    }
}
