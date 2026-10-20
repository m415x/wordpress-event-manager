<?php

/**
 * WEM-73: storage-backed immutable manifest admission.
 * Delegates all filesystem durability and privacy checks to the existing
 * manifest repository; does not mutate an existing manifest.
 */
final class WEM_Invitation_Bulk_Manifest_Admission_Storage
{
    private $admission;

    public function __construct(WEM_Invitation_Bulk_Manifest $storage)
    {
        $this->admission = new WEM_Invitation_Bulk_Manifest_Admission(
            static function (string $operation_id) use ($storage): ?array {
                return $storage->read($operation_id);
            },
            static function (string $operation_id, int $event_id, array $guest_ids) use ($storage): void {
                $storage->create($operation_id, $event_id, $guest_ids);
            }
        );
    }

    public function admit(string $operation_id, int $event_id, array $guest_ids): array
    {
        return $this->admission->admit($operation_id, $event_id, $guest_ids);
    }
}
