<?php

if (!defined('ABSPATH')) {
    exit;
}

/** WEM-86: atomic, server-authorized participation cancellation. */
final class WEM_Seating_Participation_Cancellation_Service
{
    public function cancel($guestId, $reason)
    {
        global $wpdb;

        $actorId = get_current_user_id();
        if ($actorId < 1 || !current_user_can('manage_options')) {
            throw new DomainException('Administrative seating permission denied.');
        }
        $guestId = filter_var($guestId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $reason = is_string($reason) ? trim($reason) : '';
        if ($guestId === false || $reason === '') {
            throw new DomainException('Invalid participation cancellation request.');
        }

        $posts = $wpdb->posts;
        $tables = $wpdb->prefix . 'wem_event_tables';
        $reservations = $wpdb->prefix . 'wem_table_reservations';
        $participation = $wpdb->prefix . 'wem_seating_participation';
        $audit = $wpdb->prefix . 'wem_seating_admin_audit';

        for ($attempt = 0; $attempt < 3; ++$attempt) {
            // Tentative source identity only determines the lock set.
            $sourceId = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT table_id FROM {$reservations}
                 WHERE guest_id=%d AND status='active' AND active_slot=1 LIMIT 1",
                $guestId
            ));
            if ($sourceId < 1) {
                throw new DomainException('Guest has no active reservation.');
            }

            try {
                return WEM_Seating_Transaction::run(static function () use (
                    $wpdb, $actorId, $guestId, $reason, $sourceId,
                    $posts, $tables, $reservations, $participation, $audit
                ) {
                    $guest = $wpdb->get_row($wpdb->prepare(
                        "SELECT ID,post_type,post_status FROM {$posts} WHERE ID=%d FOR UPDATE", $guestId
                    ), ARRAY_A);
                    if (!$guest || $guest['post_type'] !== 'invitado' || $guest['post_status'] !== 'publish') {
                        throw new DomainException('Guest is not eligible.');
                    }
                    $table = $wpdb->get_row($wpdb->prepare(
                        "SELECT id,event_term_id FROM {$tables} WHERE id=%d FOR UPDATE", $sourceId
                    ), ARRAY_A);
                    if ($wpdb->last_error !== '') {
                        $code = $wpdb->dbh instanceof mysqli ? (int) $wpdb->dbh->errno : 0;
                        if (in_array($code, [1205, 1213], true)) {
                            throw new WEM_Seating_Transient_Lock_Exception('Seating lock contention.');
                        }
                        throw new RuntimeException('Could not lock reserved table.');
                    }
                    if (!$table) {
                        throw new DomainException('Reserved table does not exist.');
                    }
                    $active = $wpdb->get_row($wpdb->prepare(
                        "SELECT id,table_id,event_term_id FROM {$reservations}
                         WHERE guest_id=%d AND status='active' AND active_slot=1 LIMIT 1", $guestId
                    ), ARRAY_A);
                    if (!$active || (int) $active['table_id'] !== $sourceId) {
                        throw new RuntimeException('Reservation changed during cancellation; retry request.');
                    }
                    $eventId = (int) $table['event_term_id'];
                    if ((int) $active['event_term_id'] !== $eventId) {
                        throw new DomainException('Reservation event mismatch.');
                    }
                    $member = $wpdb->get_var($wpdb->prepare(
                        "SELECT 1 FROM {$wpdb->term_relationships} tr
                         INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id=tr.term_taxonomy_id
                         WHERE tr.object_id=%d AND tt.taxonomy='evento' AND tt.term_id=%d LIMIT 1",
                        $guestId, $eventId
                    ));
                    if ((string) $member !== '1') {
                        throw new DomainException('Guest and table belong to different events.');
                    }
                    $state = $wpdb->get_var($wpdb->prepare(
                        "SELECT participation_status FROM {$participation}
                         WHERE guest_id=%d AND event_term_id=%d", $guestId, $eventId
                    ));
                    if ($state === 'canceled') {
                        throw new DomainException('Participation is already canceled.');
                    }
                    if ($state !== null && $state !== 'eligible') {
                        throw new DomainException('Participation state is invalid.');
                    }

                    $now = gmdate('Y-m-d H:i:s');
                    if ($state === null) {
                        $written = $wpdb->insert($participation, [
                            'guest_id' => $guestId, 'event_term_id' => $eventId,
                            'participation_status' => 'canceled',
                            'canceled_at' => $now, 'updated_at' => $now,
                        ], ['%d','%d','%s','%s','%s']);
                    } else {
                        $written = $wpdb->update($participation, [
                            'participation_status' => 'canceled',
                            'canceled_at' => $now, 'updated_at' => $now,
                        ], ['guest_id' => $guestId, 'event_term_id' => $eventId,
                            'participation_status' => 'eligible'],
                        ['%s','%s','%s'], ['%d','%d','%s']);
                    }
                    if ($written !== 1) {
                        throw new RuntimeException('Could not cancel participation.');
                    }

                    $released = $wpdb->update($reservations, [
                        'status' => 'canceled', 'active_slot' => null, 'canceled_at' => $now,
                    ], ['id' => (int) $active['id'], 'status' => 'active', 'active_slot' => 1],
                    ['%s','%d','%s'], ['%d','%s','%d']);
                    if ($released !== 1) {
                        throw new RuntimeException('Could not release participation seat.');
                    }

                    $recorded = $wpdb->insert($audit, [
                        'guest_id' => $guestId, 'event_term_id' => $eventId,
                        'actor_user_id' => $actorId, 'operation' => 'participation_cancel',
                        'old_table_id' => $sourceId, 'new_table_id' => null,
                        'occurred_at' => $now, 'reason' => $reason,
                    ], ['%d','%d','%d','%s','%d','%d','%s','%s']);
                    if ($recorded !== 1) {
                        throw new RuntimeException('Could not append participation cancellation audit.');
                    }
                    return (int) $active['id'];
                });
            } catch (WEM_Seating_Transient_Lock_Exception $error) {
                if ($attempt === 2) {
                    throw new RuntimeException('Seating temporarily unavailable.', 0, $error);
                }
            }
        }
        throw new RuntimeException('Seating temporarily unavailable.');
    }
}
