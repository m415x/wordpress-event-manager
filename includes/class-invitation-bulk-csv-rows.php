<?php

/**
 * WEM-73: derive event-scoped private CSV rows from reconciled custody.
 * No public routes, file writes or credential issuance occur here.
 */
final class WEM_Invitation_Bulk_Csv_Rows
{
    private $reconcile;

    public function __construct(callable $reconcile)
    {
        $this->reconcile = $reconcile;
    }

    public function build(array $records, int $event_id): array
    {
        if ($event_id <= 0 || !$records) {
            throw new RuntimeException('Invalid invitation CSV event or batch.');
        }

        $rows = array();
        $seen = array();
        foreach ($records as $record) {
            if (!is_array($record)
                || !isset($record['guest_id'], $record['event_id'], $record['generation'], $record['token'])
                || !is_int($record['guest_id']) || $record['guest_id'] <= 0
                || !is_int($record['event_id']) || $record['event_id'] !== $event_id
                || !is_int($record['generation']) || $record['generation'] <= 0
                || !is_string($record['token'])
                || !preg_match('/^[A-Za-z0-9_-]{43}$/D', $record['token'])
                || isset($seen[$record['guest_id']])) {
                throw new RuntimeException('Invalid invitation CSV custody identity.');
            }
            $guest_id = $record['guest_id'];
            if (get_post_type($guest_id) !== 'invitado'
                || get_post_status($guest_id) !== 'publish') {
                throw new RuntimeException('Invitation CSV guest is not published.');
            }
            $terms = wp_get_object_terms($guest_id, 'evento', array('fields' => 'ids'));
            if (is_wp_error($terms) || count($terms) !== 1 || (int) $terms[0] !== $event_id) {
                throw new RuntimeException('Invitation CSV guest event mismatch.');
            }
            if (!(($this->reconcile)($record))) {
                throw new RuntimeException('Invitation CSV credential is not exportable.');
            }

            $seen[$guest_id] = true;
            $rows[] = array(
                'guest_id' => $guest_id,
                'guest_name' => get_the_title($guest_id),
                'event_id' => $event_id,
                'invitation_url' => add_query_arg('wem_invitation', $record['token'], home_url('/')),
            );
        }
        return $rows;
    }
}
