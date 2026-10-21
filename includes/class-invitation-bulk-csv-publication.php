<?php

/**
 * WEM-73: publish a complete, validated private CSV for one event.
 * Custody is reconciled when building rows; publication is delegated to
 * the immutable private CSV exporter. This adapter never issues credentials.
 */
final class WEM_Invitation_Bulk_Csv_Publication
{
    private $rows;
    private $export;

    public function __construct(
        WEM_Invitation_Bulk_Csv_Rows $rows,
        WEM_Invitation_Bulk_Private_Export $export
    ) {
        $this->rows = $rows;
        $this->export = $export;
    }

    public function save(string $operation_id, int $event_id, array $custody_records): void
    {
        if (!preg_match('/^[A-Za-z0-9_-]{1,128}$/D', $operation_id)
            || $event_id <= 0 || !$custody_records) {
            throw new RuntimeException('Invalid invitation CSV publication request.');
        }

        // Do not publish anything until every custody record has passed
        // event-scoped row validation and reconciliation.
        $rows = $this->rows->build($custody_records, $event_id);
        if (count($rows) !== count($custody_records)) {
            throw new RuntimeException('Incomplete invitation CSV batch.');
        }

        $this->export->save($operation_id, $rows);
    }
}
