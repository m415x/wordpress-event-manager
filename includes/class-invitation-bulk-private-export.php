<?php

/**
 * WEM-73: final reconciliation and private CSV publication.
 * The caller must supply complete, event-scoped, authorized records.
 * Never logs, prints, or returns invitation bearer material.
 */
final class WEM_Invitation_Bulk_Private_Export
{
    private $csv;
    private $storage;
    private $reconcile;

    public function __construct(
        WEM_Invitation_Private_Csv $csv,
        WEM_Invitation_Private_Csv_Storage $storage,
        callable $reconcile
    ) {
        $this->csv = $csv;
        $this->storage = $storage;
        $this->reconcile = $reconcile;
    }

    public function save(string $operation_id, array $records): void
    {
        if (!preg_match('/^[A-Za-z0-9_-]{1,128}$/D', $operation_id)
            || !$records) {
            throw new RuntimeException('Invalid private invitation export.');
        }

        $seen = array();
        $event_id = null;
        foreach ($records as $record) {
            if (!is_array($record)
                || !isset($record['guest_id'], $record['event_id'])
                || !is_int($record['guest_id']) || $record['guest_id'] <= 0
                || !is_int($record['event_id']) || $record['event_id'] <= 0
                || isset($seen[$record['guest_id']])) {
                throw new RuntimeException('Invalid private invitation export record.');
            }
            if ($event_id !== null && $event_id !== $record['event_id']) {
                throw new RuntimeException('Private invitation export event mismatch.');
            }
            $event_id = $record['event_id'];
            $seen[$record['guest_id']] = true;
            if (!(($this->reconcile)($record))) {
                throw new RuntimeException('Invitation reconciliation failed before private CSV publication.');
            }
        }

        $this->storage->save($operation_id, $this->csv->render($records));
    }
}
