<?php

/**
 * WEM-73: unit-level batch resume coordinator.
 *
 * The recovery callback must only return null after independently proving
 * that no issuance was attempted for this operation/guest. An uncertain COMMIT
 * is not an unissued guest and must raise, never return null.
 */
final class WEM_Invitation_Bulk_Resume
{
    private $recover;
    private $reconcile;
    private $issue;
    private $export;

    public function __construct(callable $recover, callable $reconcile, callable $issue, callable $export)
    {
        $this->recover = $recover;
        $this->reconcile = $reconcile;
        $this->issue = $issue;
        $this->export = $export;
    }

    public function run(array $guest_ids): void
    {
        $seen = array();
        foreach ($guest_ids as $guest_id) {
            if (!is_int($guest_id) || $guest_id <= 0 || isset($seen[$guest_id])) {
                throw new RuntimeException('Invalid or duplicate resumed invitation guest.');
            }
            $seen[$guest_id] = true;
        }

        $records = array();
        foreach ($guest_ids as $guest_id) {
            // null is a trusted NOT_STARTED determination by the recovery
            // boundary, not merely absence of a local journal file.
            $record = ($this->recover)($guest_id);
            if ($record === null) {
                $record = ($this->issue)($guest_id);
            }
            if (!is_array($record)
                || ($record['guest_id'] ?? null) !== $guest_id
                || !(($this->reconcile)($record))) {
                throw new RuntimeException('Resumed invitation credential reconciliation failed.');
            }
            $records[] = $record;
        }

        // A credential reconciled earlier may be revoked while later guests
        // are processed. Revalidate every record before any CSV publication.
        foreach ($records as $record) {
            if (!(($this->reconcile)($record))) {
                throw new RuntimeException('Resumed invitation credential reconciliation failed.');
            }
        }

        // Never publish partial batches.
        ($this->export)($records);
    }
}
