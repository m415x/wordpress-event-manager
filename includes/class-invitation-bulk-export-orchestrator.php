<?php

/**
 * WEM-73: fail-closed batch coordination. The issuer must be the WEM-33
 * custody bridge; exporter must use private storage after reconciliation.
 */
final class WEM_Invitation_Bulk_Export_Orchestrator
{
    private $issue;
    private $reconcile;
    private $export;

    public function __construct(callable $issue, callable $reconcile, callable $export)
    {
        $this->issue = $issue;
        $this->reconcile = $reconcile;
        $this->export = $export;
    }

    public function run(array $guest_ids): void
    {
        $records = array();
        $seen = array();
        foreach ($guest_ids as $guest_id) {
            if (!is_int($guest_id) || $guest_id <= 0 || isset($seen[$guest_id])) {
                throw new RuntimeException('Invalid or duplicate bulk invitation guest.');
            }
            $seen[$guest_id] = true;
        }

        foreach ($guest_ids as $guest_id) {
            // Never retry after a thrown error: COMMIT may have succeeded.
            $record = ($this->issue)($guest_id);
            if (!is_array($record) || !(($this->reconcile)($record))) {
                throw new RuntimeException('Invitation credential reconciliation failed.');
            }
            $records[] = $record;
        }

        // One complete, verified batch; never export a partial subset.
        ($this->export)($records);
    }
}
