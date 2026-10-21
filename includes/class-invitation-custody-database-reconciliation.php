<?php

/**
 * WEM-73: read-only reconciliation against committed WordPress/MySQL state.
 * No issuance, mutation, retry, or compensation is permitted here.
 */
final class WEM_Invitation_Custody_Database_Reconciliation
{
    public function is_exportable($custody): bool
    {
        global $wpdb;

        if (!is_array($custody)
            || !isset($custody['guest_id'], $custody['event_id'], $custody['generation'])
            || !is_int($custody['guest_id']) || $custody['guest_id'] <= 0
            || !is_int($custody['event_id']) || $custody['event_id'] <= 0
            || !is_int($custody['generation']) || $custody['generation'] <= 0
            || get_post_type($custody['guest_id']) !== 'invitado') {
            return false;
        }

        $terms = wp_get_object_terms($custody['guest_id'], 'evento', array('fields' => 'ids'));
        if (is_wp_error($terms) || count($terms) !== 1
            || (int) $terms[0] !== $custody['event_id']) {
            return false;
        }

        $table = WEM_Invitation_Credential_Schema::table_name();
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT guest_id, generation, token_digest, status, active_slot
                 FROM {$table} WHERE guest_id = %d AND generation = %d LIMIT 1",
                $custody['guest_id'],
                $custody['generation']
            ),
            ARRAY_A
        );
        if (!is_array($row)) {
            return false;
        }

        $credential = array(
            'guest_id' => (int) $row['guest_id'],
            'event_id' => (int) $terms[0],
            'generation' => (int) $row['generation'],
            'token_digest' => $row['token_digest'],
            'status' => $row['status'],
            'active_slot' => $row['active_slot'] === null ? null : (int) $row['active_slot'],
        );

        return (new WEM_Invitation_Custody_Reconciler())->is_exportable($custody, $credential);
    }
}
