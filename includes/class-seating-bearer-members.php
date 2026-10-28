<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * WEM-91/T4: bearer-scoped member projection.
 * No verified structured first/surname authority exists, so all members
 * remain private regardless of consent. A later approved identity contract
 * is required before implementing a public 'Nombre A.' formatter.
 */
final class WEM_Seating_Bearer_Members
{
    public function list_for_bearer($bearer)
    {
        $identity = (new WEM_Seating_Bearer_Authorization())->resolve($bearer);
        if ($identity === null) {
            return null;
        }

        global $wpdb;
        $tables = WEM_Event_Table_Schema::table_name();
        $reservations = WEM_Table_Reservation_Schema::table_name();

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT t.id, t.table_code, COUNT(r.id) AS occupied
               FROM {$tables} t
               LEFT JOIN {$reservations} r ON r.table_id=t.id
                 AND r.event_term_id=t.event_term_id
                 AND r.status='active' AND r.active_slot=1
              WHERE t.event_term_id=%d
              GROUP BY t.id,t.table_code
              ORDER BY t.id ASC",
            $identity['event_term_id']
        ), ARRAY_A);
        if (!is_array($rows) || $wpdb->last_error !== '') {
            throw new RuntimeException('Members are unavailable.');
        }

        $result = [];
        foreach ($rows as $row) {
            $count = (int) $row['occupied'];
            if ($count < 0) {
                throw new RuntimeException('Members are inconsistent.');
            }
            $result[] = [
                'table_code' => (string) $row['table_code'],
                'members' => array_fill(0, $count, 'Integrante privado'),
            ];
        }
        return $result;
    }
}
