<?php

/**
 * WEM-73: compose immutable batch admission with authoritative resume
 * classification. This class never issues, retries, or exports credentials.
 */
final class WEM_Invitation_Bulk_Admission_Evidence
{
    private $admission;
    private $evidence;

    public function __construct(
        WEM_Invitation_Bulk_Manifest $manifest,
        WEM_Invitation_Bulk_Attempt_Storage $attempts,
        WEM_Invitation_Private_Journal $journal
    ) {
        $this->admission = new WEM_Invitation_Bulk_Manifest_Admission_Storage($manifest);
        $this->evidence = new WEM_Invitation_Bulk_Evidence_Reader($manifest, $attempts, $journal);
    }

    public function admit_and_classify(
        string $operation_id,
        int $event_id,
        array $guest_ids,
        int $guest_id
    ): string {
        $admitted = $this->admission->admit($operation_id, $event_id, $guest_ids);
        if (!in_array($guest_id, $admitted, true)) {
            throw new RuntimeException('Invitation guest is outside the admitted batch.');
        }
        return $this->evidence->classify($operation_id, $guest_id);
    }
}
