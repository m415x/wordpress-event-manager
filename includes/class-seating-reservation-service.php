<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * WEM-82: server-authoritative initial reservation.
 * The caller provides only the real guest and table identities; event,
 * participation and available capacity are resolved under InnoDB locks.
 */
final class WEM_Seating_Reservation_Service
{
    public function reserve_initial($guestId, $tableId)
    {
        global $wpdb;

        $guestId = filter_var($guestId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $tableId = filter_var($tableId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($guestId === false || $tableId === false) {
            throw new DomainException('Invalid seating identity.');
        }

        return WEM_Seating_Transaction::run(static function () use ($wpdb, $guestId, $tableId) {
            $posts = $wpdb->posts;
            $tables = $wpdb->prefix . 'wem_event_tables';
            $reservations = $wpdb->prefix . 'wem_table_reservations';
            $participation = $wpdb->prefix . 'wem_seating_participation';

            // Lock order is fixed: canonical guest first, then target table.
            $guest = $wpdb->get_row($wpdb->prepare(
                "SELECT ID, post_type, post_status FROM {$posts} WHERE ID=%d FOR UPDATE",
                $guestId
            ), ARRAY_A);
            if (!$guest || $guest['post_type'] !== 'invitado' || $guest['post_status'] !== 'publish') {
                throw new DomainException('Guest is not eligible.');
            }

            $table = $wpdb->get_row($wpdb->prepare(
                "SELECT id,event_term_id,capacity FROM {$tables} WHERE id=%d FOR UPDATE",
                $tableId
            ), ARRAY_A);
            if (!$table || (int) $table['capacity'] < 1) {
                throw new DomainException('Table is not available.');
            }
            $eventId = (int) $table['event_term_id'];

            // Resolve event membership from WordPress canonical taxonomy rows,
            // not a caller-supplied event label or cached frontend projection.
            $member = $wpdb->get_var($wpdb->prepare(
                "SELECT 1 FROM {$wpdb->term_relationships} tr
                 INNER JOIN {$wpdb->term_taxonomy} tt
                   ON tt.term_taxonomy_id=tr.term_taxonomy_id
                 WHERE tr.object_id=%d AND tt.taxonomy='evento'
                   AND tt.term_id=%d LIMIT 1",
                $guestId, $eventId
            ));
            if ((string) $member !== '1') {
                throw new DomainException('Guest and table belong to different events.');
            }

            $state = $wpdb->get_var($wpdb->prepare(
                "SELECT participation_status FROM {$participation}
                 WHERE guest_id=%d AND event_term_id=%d",
                $guestId, $eventId
            ));
            if ($state !== null && $state !== 'eligible') {
                throw new DomainException('Participation is canceled or invalid.');
            }

            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$reservations}
                 WHERE guest_id=%d AND status='active' AND active_slot=1 LIMIT 1",
                $guestId
            ));
            if ($existing !== null) {
                throw new DomainException('Guest already has an active reservation.');
            }

            $occupied = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$reservations}
                 WHERE table_id=%d AND status='active' AND active_slot=1",
                $tableId
            ));
            if ($occupied === null || (int) $occupied >= (int) $table['capacity']) {
                throw new DomainException('Table is full or unavailable.');
            }

            $inserted = $wpdb->insert($reservations, [
                'guest_id' => $guestId,
                'table_id' => $tableId,
                'event_term_id' => $eventId,
                'status' => 'active',
                'active_slot' => 1,
                'created_at' => gmdate('Y-m-d H:i:s'),
            ], ['%d', '%d', '%d', '%s', '%d', '%s']);
            if ($inserted !== 1) {
                throw new RuntimeException('Could not persist initial reservation.');
            }
            return (int) $wpdb->insert_id;
        });
    }
}
