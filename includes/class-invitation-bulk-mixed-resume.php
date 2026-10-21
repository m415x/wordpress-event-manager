<?php

/**
 * WEM-73: coordinate a mixed batch without interpreting missing custody
 * as permission to issue. The state callback must use durable evidence,
 * and the issuer must enforce per-guest locking and attempt markers.
 */
final class WEM_Invitation_Bulk_Mixed_Resume
{
    private $state;
    private $recover;
    private $issue;
    private $reconcile;
    private $export;

    public function __construct(
        callable $state,
        callable $recover,
        callable $issue,
        callable $reconcile,
        callable $export
    ) {
        $this->state = $state;
        $this->recover = $recover;
        $this->issue = $issue;
        $this->reconcile = $reconcile;
        $this->export = $export;
    }

    public function run(array $guest_ids): void
    {
        $seen = array();
        foreach ($guest_ids as $id) {
            if (!is_int($id) || $id <= 0 || isset($seen[$id])) {
                throw new RuntimeException('Invalid or duplicate resumed invitation guest.');
            }
            $seen[$id] = true;
        }

        $records = array();
        foreach ($guest_ids as $id) {
            $state = ($this->state)($id);
            if ($state === 'reconcile') {
                $record = ($this->recover)($id);
            } elseif ($state === 'not_started') {
                $record = ($this->issue)($id);
            } else {
                throw new RuntimeException('Invitation issuance state is indeterminate.');
            }

            if (!is_array($record) || ($record['guest_id'] ?? null) !== $id
                || !(($this->reconcile)($record))) {
                throw new RuntimeException('Resumed invitation credential reconciliation failed.');
            }
            $records[] = $record;
        }

        foreach ($records as $record) {
            if (!(($this->reconcile)($record))) {
                throw new RuntimeException('Resumed invitation credential reconciliation failed.');
            }
        }
        ($this->export)($records);
    }
}
