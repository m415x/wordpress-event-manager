<?php

/**
 * WEM-73: compose durable mixed-batch resume with private CSV publication.
 * This adapter does not expose tokens to WP-CLI or introduce HTTP endpoints.
 */
final class WEM_Invitation_Bulk_Mixed_Csv_Workflow
{
    private $admission;
    private $recovery;
    private $issuer;
    private $reconciliation;
    private $publication;

    public function __construct(
        WEM_Invitation_Bulk_Admission_Evidence $admission,
        WEM_Invitation_Bulk_Recovered_Storage $recovery,
        WEM_Invitation_Bulk_Admitted_Locked_Storage $issuer,
        WEM_Invitation_Custody_Database_Reconciliation $reconciliation,
        WEM_Invitation_Bulk_Csv_Publication $publication
    ) {
        $this->admission = $admission;
        $this->recovery = $recovery;
        $this->issuer = $issuer;
        $this->reconciliation = $reconciliation;
        $this->publication = $publication;
    }

    public function run(string $operation_id, int $event_id, array $guest_ids): void
    {
        $publication = $this->publication;
        $runner = new WEM_Invitation_Bulk_Mixed_Storage(
            $this->admission,
            $this->recovery,
            $this->issuer,
            $this->reconciliation,
            static function (array $records) use ($publication, $operation_id, $event_id): void {
                $publication->save($operation_id, $event_id, $records);
            }
        );
        $runner->run($operation_id, $event_id, $guest_ids);
    }
}
