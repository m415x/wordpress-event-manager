<?php

if (!defined('ABSPATH')) { exit; }

/**
 * P0 issuance and irreversible bind. No public routes or print delivery here.
 * The caller must provide an authorized WordPress administrator identity.
 */
final class WEM_Preissued_Invitation_Service
{
    private function authorize(int $actor): void
    {
        if ($actor <= 0 || !user_can($actor, 'manage_options')) {
            throw new RuntimeException('Preissued invitation operation unavailable.');
        }
    }

    public function issue_unassigned(int $eventId, int $actor, callable $custody): int
    {
        global $wpdb;
        $this->authorize($actor);
        if ($eventId <= 0 || !term_exists($eventId, 'evento')) {
            throw new RuntimeException('Invalid event.');
        }
        $table = WEM_Preissued_Invitation_Schema::table_name();
        $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $digest = hash('sha256', $token);
        // Custody callback is executed BEFORE commit so a storage failure
        // prevents issuance. It must be idempotent and securely persistent.
        if ($wpdb->query('START TRANSACTION') === false) {
            throw new RuntimeException('Unable to start invitation issuance.');
        }
        try {
            $inserted = $wpdb->insert($table, [
                'event_term_id' => $eventId,
                'token_digest' => $digest,
                'state' => 'unassigned',
                'issued_by' => $actor,
                'issued_at' => current_time('mysql', true),
            ], ['%d', '%s', '%s', '%d', '%s']);
            if ($inserted !== 1) {
                throw new RuntimeException('Unable to persist preissued invitation.');
            }
            $id = (int) $wpdb->insert_id;
            $custody($id, add_query_arg('wem_preissued', $token, home_url('/')));
            if ($wpdb->query('COMMIT') === false) {
                throw new RuntimeException('Invitation commit outcome indeterminate: reconcile custody before retry.');
            }
            return $id;
        } catch (Throwable $e) {
            $wpdb->query('ROLLBACK');
            throw $e;
        }
    }

    public function assign_once(int $invitationId, int $guestId, int $actor): void
    {
        global $wpdb;
        $this->authorize($actor);
        if ($invitationId <= 0 || $guestId <= 0) {
            throw new RuntimeException('Invalid invitation assignment.');
        }
        $table = WEM_Preissued_Invitation_Schema::table_name();
        $audit = WEM_Preissued_Invitation_Schema::audit_table_name();
        if ($wpdb->query('START TRANSACTION') === false) {
            throw new RuntimeException('Unable to start assignment.');
        }
        try {
            $invitation = $wpdb->get_row($wpdb->prepare(
                "SELECT id, event_term_id, state, assigned_guest_id FROM {$table} WHERE id = %d FOR UPDATE",
                $invitationId
            ), ARRAY_A);
            if (!is_array($invitation) || $invitation['state'] !== 'unassigned'
                || $invitation['assigned_guest_id'] !== null) {
                throw new RuntimeException('Invitation is unavailable for assignment.');
            }
            $lockedGuest = $wpdb->get_var($wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts} WHERE ID = %d FOR UPDATE", $guestId
            ));
            if ((int) $lockedGuest !== $guestId || get_post_type($guestId) !== 'invitado'
                || get_post_status($guestId) !== 'publish') {
                throw new RuntimeException('Guest is ineligible.');
            }
            $terms = wp_get_object_terms($guestId, 'evento', ['fields' => 'ids']);
            if (is_wp_error($terms) || count($terms) !== 1
                || (int) $terms[0] !== (int) $invitation['event_term_id']) {
                throw new RuntimeException('Guest canonical event mismatch.');
            }
            // Legacy WEM-33 active bearer cannot silently coexist with an
            // inventory bearer. A dedicated explicit bridge is required.
            $credentials = WEM_Invitation_Credential_Schema::table_name();
            $active = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$credentials} WHERE guest_id = %d AND status = 'active' AND active_slot = 1",
                $guestId
            ));
            if ($active !== 0) {
                throw new RuntimeException('Guest already has an active credential.');
            }
            $updated = $wpdb->update($table, [
                'state' => 'assigned', 'assigned_guest_id' => $guestId,
                'assigned_by' => $actor, 'assigned_at' => current_time('mysql', true),
            ], ['id' => $invitationId, 'state' => 'unassigned'],
                ['%s', '%d', '%d', '%s'], ['%d', '%s']);
            if ($updated !== 1) {
                throw new RuntimeException('Assignment rejected.');
            }
            $logged = $wpdb->insert($audit, [
                'invitation_id' => $invitationId, 'guest_id' => $guestId,
                'event_term_id' => (int) $invitation['event_term_id'],
                'actor_user_id' => $actor, 'operation' => 'assign',
                'occurred_at' => current_time('mysql', true),
            ], ['%d', '%d', '%d', '%d', '%s', '%s']);
            if ($logged !== 1 || $wpdb->query('COMMIT') === false) {
                throw new RuntimeException('Assignment audit or commit failed.');
            }
        } catch (Throwable $e) {
            $wpdb->query('ROLLBACK');
            throw $e;
        }
    }
}
