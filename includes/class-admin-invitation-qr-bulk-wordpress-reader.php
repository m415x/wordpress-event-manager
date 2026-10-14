<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Read-only WordPress projection for a guest's current credential statuses.
 * Never selects token digests or recoverable invitation material.
 */
final class WEM_Admin_Invitation_QR_Bulk_WordPress_Reader
{
    private $database;

    public function __construct($database = null)
    {
        if ($database === null) {
            global $wpdb;
            $database = $wpdb;
        }
        $this->database = $database;
    }

    public function read($guest_id): array
    {
        if (!is_int($guest_id) || $guest_id <= 0 || get_post_type($guest_id) !== 'invitado') {
            return array('valid_guest' => false, 'credentials' => array());
        }

        $table = $this->database->prefix . WEM_Invitation_Credential_Schema::TABLE_SUFFIX;
        $sql = $this->database->prepare(
            "SELECT status, active_slot FROM {$table} WHERE guest_id = %d",
            $guest_id
        );
        $rows = $this->database->get_results($sql, ARRAY_A);

        if (!is_array($rows)) {
            throw new RuntimeException('Invitation credential state is unavailable.');
        }

        return array('valid_guest' => true, 'credentials' => $rows);
    }
}
