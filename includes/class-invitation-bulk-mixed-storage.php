<?php

/**
 * WEM-73: compose mixed-batch resume against persistent admission, custody,
 * issuance and database reconciliation. Export remains supplied explicitly.
 */
final class WEM_Invitation_Bulk_Mixed_Storage
{
    private $admission;
    private $recovery;
    private $issuer;
    private $reconciliation;
    private $export;

    public function __construct(
        WEM_Invitation_Bulk_Admission_Evidence $admission,
        WEM_Invitation_Bulk_Recovered_Storage $recovery,
        WEM_Invitation_Bulk_Admitted_Locked_Storage $issuer,
        WEM_Invitation_Custody_Database_Reconciliation $reconciliation,
        callable $export
    ) {
        $this->admission = $admission;
        $this->recovery = $recovery;
        $this->issuer = $issuer;
        $this->reconciliation = $reconciliation;
        $this->export = $export;
    }

    public function run(string $operation_id, int $event_id, array $guest_ids): void
    {
        $admission = $this->admission;
        $recovery = $this->recovery;
        $issuer = $this->issuer;
        $reconciliation = $this->reconciliation;

        $runner = new WEM_Invitation_Bulk_Mixed_Resume(
            static fn (int $guest_id): string =>
                $admission->admit_and_classify($operation_id, $event_id, $guest_ids, $guest_id),
            static fn (int $guest_id): array =>
                $recovery->recover($operation_id, $event_id, $guest_ids, $guest_id),
            static fn (int $guest_id): array =>
                $issuer->run($operation_id, $event_id, $guest_ids, $guest_id),
            static fn (array $record): bool => $reconciliation->is_exportable($record),
            $this->export
        );
        $runner->run($guest_ids);
    }
}
