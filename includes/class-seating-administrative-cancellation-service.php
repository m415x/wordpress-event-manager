<?php

if (!defined('ABSPATH')) {
    exit;
}

/** WEM-84: server-authorized administrative seat cancellation. */
final class WEM_Seating_Administrative_Cancellation_Service
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
            throw new DomainException('Invalid cancellation request.');
        }

        $posts = $wpdb->posts;
        $reservations = $wpdb->prefix . 'wem_table_reservations';
        $tables = $wpdb->prefix . 'wem_event_tables';
        $audit = $wpdb->prefix . 'wem_seating_admin_audit';

        // Discover the tentative table without trusting it for mutation.
        // Always lock guest first and table second in the common WEM-75 order.
        for ($attempt = 0; $attempt < 3; ++$attempt) {
            $tentativeTableId = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT table_id FROM {$reservations}
                 WHERE guest_id=%d AND status='active' AND active_slot=1 LIMIT 1",
                $guestId
            ));
            if ($tentativeTableId < 1) {
                throw new DomainException('Guest has no active reservation.');
            }

            try {
                return WEM_Seating_Transaction::run(static function () use (
                    $wpdb, $actorId, $guestId, $reason, $posts,
                    $reservations, $tables, $audit, $tentativeTableId
                ) {
                    $guest = $wpdb->get_row($wpdb->prepare(
                        "SELECT ID,post_type,post_status FROM {$posts} WHERE ID=%d FOR UPDATE",
                        $guestId
                    ), ARRAY_A);
                    if (!$guest || $guest['post_type'] !== 'invitado') {
                        throw new DomainException('Guest is not eligible.');
                    }

                    $table = $wpdb->get_row($wpdb->prepare(
                        "SELECT id,event_term_id FROM {$tables} WHERE id=%d FOR UPDATE",
                        $tentativeTableId
                    ), ARRAY_A);
                    if (!$table) {
                        throw new DomainException('Reserved table is unavailable.');
                    }

                    $active = $wpdb->get_row($wpdb->prepare(
                        "SELECT id,table_id,event_term_id FROM {$reservations}
                         WHERE guest_id=%d AND status='active' AND active_slot=1 LIMIT 1",
                        $guestId
                    ), ARRAY_A);
                    if (!$active) {
                        throw new DomainException('Guest has no active reservation.');
                    }
                    if ((int) $active['table_id'] !== $tentativeTableId) {
                        throw new RuntimeException('Reservation changed during cancellation; retry request.');
                    }
                    if ((int) $active['event_term_id'] !== (int) $table['event_term_id']) {
                        throw new DomainException('Reservation event mismatch.');
                    }

                    $updated = $wpdb->update($reservations, [
                        'status' => 'canceled',
                        'active_slot' => null,
                        'canceled_at' => gmdate('Y-m-d H:i:s'),
                    ], ['id' => (int) $active['id'], 'status' => 'active', 'active_slot' => 1],
                    ['%s', '%d', '%s'], ['%d', '%s', '%d']);
                    if ($updated !== 1) {
                        throw new RuntimeException('Could not cancel active reservation.');
                    }

                    $recorded = $wpdb->insert($audit, [
                        'guest_id' => $guestId,
                        'event_term_id' => (int) $active['event_term_id'],
                        'actor_user_id' => $actorId,
                        'operation' => 'cancel',
                        'old_table_id' => $tentativeTableId,
                        'new_table_id' => null,
                        'occurred_at' => gmdate('Y-m-d H:i:s'),
                        'reason' => $reason,
                    ], ['%d', '%d', '%d', '%s', '%d', '%d', '%s', '%s']);
                    if ($recorded !== 1) {
                        throw new RuntimeException('Could not append seating cancellation audit.');
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
