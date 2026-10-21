<?php

/**
 * WEM-73: reads persistent recovery evidence; does not issue or export.
 * A missing attempt is NOT proof of no issuance outside a valid manifest
 * and an independently queried zero credential count.
 */
final class WEM_Invitation_Bulk_Evidence_Reader
{
    private $manifest;
    private $attempts;
    private $journal;

    public function __construct($manifest, $attempts, $journal)
    {
        $this->manifest = $manifest;
        $this->attempts = $attempts;
        $this->journal = $journal;
    }

    public function classify(string $operation_id, int $guest_id): string
    {
        global $wpdb;

        if ($guest_id <= 0) {
            return 'indeterminate';
        }

        try {
            $manifest = $this->manifest->read($operation_id);
            if (!is_array($manifest)
                || ($manifest['operation_id'] ?? null) !== $operation_id
                || !in_array($guest_id, $manifest['guest_ids'] ?? array(), true)) {
                return 'indeterminate';
            }

            $attempted = $this->attempts->has_started($operation_id, $guest_id);

            $table = WEM_Invitation_Credential_Schema::table_name();
            // Read all generations: a rotated or revoked credential must
            // never be interpreted as a never-started issuance.
            $wpdb->last_error = '';
            $rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT generation FROM {$table} WHERE guest_id = %d ORDER BY generation ASC",
                    $guest_id
                ),
                ARRAY_A
            );
            if (!is_array($rows) || $wpdb->last_error !== '') {
                return 'indeterminate';
            }

            $count = count($rows);
            $custody = null;
            if ($attempted && $count === 1) {
                $generation = (int) $rows[0]['generation'];
                if ($generation <= 0) {
                    return 'indeterminate';
                }
                $custody = $this->journal->recover($operation_id, $guest_id, $generation);
            }

            return (new WEM_Invitation_Bulk_Manifest_Resume_Evidence())->classify(
                $manifest,
                $operation_id,
                $guest_id,
                $attempted,
                $custody,
                $count
            );
        } catch (Throwable $error) {
            // No inference of non-issuance when the filesystem or SQL read fails.
            return 'indeterminate';
        }
    }
}
