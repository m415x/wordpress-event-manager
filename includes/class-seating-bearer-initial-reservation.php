<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * WEM-92/T5: one initial reservation for the holder of an active WEM-33 bearer.
 * Authorization is serialized with WEM-33 credential lifecycle through I2's
 * canonical guest lock; I2 remains the sole reservation write authority.
 */
final class WEM_Seating_Bearer_Initial_Reservation
{
    public function reserve_for_bearer($bearer, $tableId)
    {
        $identity = (new WEM_Seating_Bearer_Authorization())->resolve($bearer);
        if ($identity === null) {
            throw new DomainException('Seating authorization unavailable.');
        }

        $guestId = (int) $identity['guest_id'];
        $eventId = (int) $identity['event_term_id'];

        return (new WEM_Seating_Reservation_Service())->reserve_initial(
            $guestId,
            $tableId,
            static function ($lockedGuestId) use ($bearer, $guestId, $eventId): void {
                global $wpdb;

                if ((int) $lockedGuestId !== $guestId) {
                    throw new DomainException('Seating authorization unavailable.');
                }

                // The preceding I2 guest FOR UPDATE serializes this validation
                // with WEM-33 issue/rotate/revoke, which acquire that same lock.
                $digest = hash('sha256', $bearer);
                $credentialTable = WEM_Invitation_Credential_Schema::table_name();
                $active = $wpdb->get_row($wpdb->prepare(
                    "SELECT guest_id, status, active_slot
                       FROM {$credentialTable}
                      WHERE token_digest=%s LIMIT 1",
                    $digest
                ), ARRAY_A);
                if (
                    !is_array($active)
                    || (int) $active['guest_id'] !== $guestId
                    || $active['status'] !== 'active'
                    || (int) $active['active_slot'] !== 1
                ) {
                    throw new DomainException('Seating authorization unavailable.');
                }

                // Read canonical taxonomy rows, not cached terms or a supplied
                // event label. Exactly one valid event is required.
                $events = $wpdb->get_col($wpdb->prepare(
                    "SELECT tt.term_id
                       FROM {$wpdb->term_relationships} tr
                       JOIN {$wpdb->term_taxonomy} tt
                         ON tt.term_taxonomy_id=tr.term_taxonomy_id
                      WHERE tr.object_id=%d AND tt.taxonomy='evento'",
                    $guestId
                ));
                if (
                    !is_array($events)
                    || count($events) !== 1
                    || (int) $events[0] !== $eventId
                    || $wpdb->last_error !== ''
                ) {
                    throw new DomainException('Seating authorization unavailable.');
                }
            }
        );
    }
}
