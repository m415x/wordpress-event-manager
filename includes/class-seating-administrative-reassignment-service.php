<?php

if (!defined('ABSPATH')) {
    exit;
}

/** WEM-85: server-authorized, atomic reassignment across locked tables. */
final class WEM_Seating_Administrative_Reassignment_Service
{
    public function reassign($guestId, $targetTableId, $reason)
    {
        global $wpdb;

        $actorId = get_current_user_id();
        if ($actorId < 1 || !current_user_can('manage_options')) {
            throw new DomainException('Administrative seating permission denied.');
        }
        $guestId = filter_var($guestId, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
        $targetTableId = filter_var($targetTableId, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
        $reason = is_string($reason) ? trim($reason) : '';
        if ($guestId === false || $targetTableId === false || $reason === '') {
            throw new DomainException('Invalid reassignment request.');
        }

        $posts = $wpdb->posts;
        $tables = $wpdb->prefix . 'wem_event_tables';
        $reservations = $wpdb->prefix . 'wem_table_reservations';
        $audit = $wpdb->prefix . 'wem_seating_admin_audit';

        for ($attempt = 0; $attempt < 3; ++$attempt) {
            // Snapshot is for determining locks only; never used as mutation authority.
            $sourceId = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT table_id FROM {$reservations}
                 WHERE guest_id=%d AND status='active' AND active_slot=1 LIMIT 1", $guestId
            ));
            if ($sourceId < 1) {
                throw new DomainException('Guest has no active reservation.');
            }
            if ($sourceId === $targetTableId) {
                throw new DomainException('Guest is already assigned to this table.');
            }

            try {
                return WEM_Seating_Transaction::run(static function () use (
                    $wpdb, $actorId, $guestId, $targetTableId, $reason,
                    $posts, $tables, $reservations, $audit, $sourceId
                ) {
                    $guest = $wpdb->get_row($wpdb->prepare(
                        "SELECT ID,post_type,post_status FROM {$posts} WHERE ID=%d FOR UPDATE", $guestId
                    ), ARRAY_A);
                    if (!$guest || $guest['post_type'] !== 'invitado' || $guest['post_status'] !== 'publish') {
                        throw new DomainException('Guest is not eligible.');
                    }

                    $locked = [];
                    $ids = [$sourceId, $targetTableId];
                    sort($ids, SORT_NUMERIC);
                    foreach ($ids as $id) {
                        $row = $wpdb->get_row($wpdb->prepare(
                            "SELECT id,event_term_id,capacity FROM {$tables} WHERE id=%d FOR UPDATE", $id
                        ), ARRAY_A);
                        if ($wpdb->last_error !== '') {
                            $code = $wpdb->dbh instanceof mysqli ? (int) $wpdb->dbh->errno : 0;
                            if (in_array($code, [1205, 1213], true)) {
                                throw new WEM_Seating_Transient_Lock_Exception('Seating lock contention.');
                            }
                            throw new RuntimeException('Could not acquire table lock.');
                        }
                        if (!$row) {
                            throw new DomainException('Reassignment table does not exist.');
                        }
                        $locked[$id] = $row;
                    }

                    $active = $wpdb->get_row($wpdb->prepare(
                        "SELECT id,table_id,event_term_id FROM {$reservations}
                         WHERE guest_id=%d AND status='active' AND active_slot=1 LIMIT 1", $guestId
                    ), ARRAY_A);
                    if (!$active || (int) $active['table_id'] !== $sourceId) {
                        throw new RuntimeException('Reservation changed during reassignment; retry request.');
                    }
                    $eventId = (int) $active['event_term_id'];
                    if ($eventId !== (int) $locked[$sourceId]['event_term_id']
                        || $eventId !== (int) $locked[$targetTableId]['event_term_id']) {
                        throw new DomainException('Guest and tables belong to different events.');
                    }
                    $member = $wpdb->get_var($wpdb->prepare(
                        "SELECT 1 FROM {$wpdb->term_relationships} tr
                         INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id=tr.term_taxonomy_id
                         WHERE tr.object_id=%d AND tt.taxonomy='evento' AND tt.term_id=%d LIMIT 1",
                        $guestId, $eventId
                    ));
                    if ((string) $member !== '1') {
                        throw new DomainException('Guest and tables belong to different events.');
                    }
                    $state = $wpdb->get_var($wpdb->prepare(
                        "SELECT participation_status FROM {$wpdb->prefix}wem_seating_participation
                         WHERE guest_id=%d AND event_term_id=%d", $guestId, $eventId
                    ));
                    if ($state !== null && $state !== 'eligible') {
                        throw new DomainException('Participation is canceled or invalid.');
                    }

                    $occupied = $wpdb->get_var($wpdb->prepare(
                        "SELECT COUNT(*) FROM {$reservations}
                         WHERE table_id=%d AND status='active' AND active_slot=1", $targetTableId
                    ));
                    if ($occupied === null || (int) $occupied >= (int) $locked[$targetTableId]['capacity']) {
                        throw new DomainException('Target table is full or unavailable.');
                    }

                    $updated = $wpdb->update($reservations, ['table_id'=>$targetTableId],
                        ['id'=>(int) $active['id'], 'table_id'=>$sourceId, 'status'=>'active','active_slot'=>1],
                        ['%d'], ['%d','%d','%s','%d']);
                    if ($updated !== 1) {
                        throw new RuntimeException('Could not reassign active reservation.');
                    }
                    $recorded = $wpdb->insert($audit, [
                        'guest_id'=>$guestId,
                        'event_term_id'=>$eventId,
                        'actor_user_id'=>$actorId,
                        'operation'=>'reassign',
                        'old_table_id'=>$sourceId,
                        'new_table_id'=>$targetTableId,
                        'occurred_at'=>gmdate('Y-m-d H:i:s'),
                        'reason'=>$reason,
                    ], ['%d','%d','%d','%s','%d','%d','%s','%s']);
                    if ($recorded !== 1) {
                        throw new RuntimeException('Could not append seating reassignment audit.');
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
