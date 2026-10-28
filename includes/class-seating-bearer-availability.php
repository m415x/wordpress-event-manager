<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * WEM-89/T2: internal, bearer-scoped availability projection.
 * No public route or member disclosure; client cannot choose the event.
 */
final class WEM_Seating_Bearer_Availability
{
    public function list_for_bearer($bearer)
    {
        $context = (new WEM_Seating_Bearer_Authorization())->resolve($bearer);
        if ($context === null) {
            return null;
        }

        global $wpdb;
        $tables = WEM_Event_Table_Schema::table_name();
        $reservations = WEM_Table_Reservation_Schema::table_name();

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT t.table_code, t.capacity,
                    COUNT(r.id) AS occupied
             FROM {$tables} t
             LEFT JOIN {$reservations} r
               ON r.table_id = t.id
              AND r.event_term_id = t.event_term_id
              AND r.status = 'active'
              AND r.active_slot = 1
             WHERE t.event_term_id = %d
             GROUP BY t.id, t.table_code, t.capacity
             ORDER BY t.id ASC",
            $context['event_term_id']
        ), ARRAY_A);

        if (!is_array($rows) || $wpdb->last_error !== '') {
            throw new RuntimeException('Seating availability is unavailable.');
        }

        $available = [];
        foreach ($rows as $row) {
            $capacity = (int) $row['capacity'];
            $occupied = (int) $row['occupied'];
            if ($capacity < 1 || $occupied < 0 || $occupied > $capacity) {
                throw new RuntimeException('Seating availability is inconsistent.');
            }
            $available[] = [
                'table_code' => (string) $row['table_code'],
                'capacity' => $capacity,
                'occupied' => $occupied,
                'free' => $capacity - $occupied,
            ];
        }

        return $available;
    }
}
