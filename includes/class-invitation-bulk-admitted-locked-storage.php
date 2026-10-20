<?php

/**
 * WEM-73: concrete composition for one admitted, locked WEM-33 issuance.
 * Does not retry a prior attempt, nor provide batch CSV publication.
 */
final class WEM_Invitation_Bulk_Admitted_Locked_Storage
{
    private $lock;
    private $admission;
    private $attempts;
    private $bridge;
    private int $actor_id;

    public function __construct(
        WEM_Invitation_Bulk_Guest_Lock $lock,
        WEM_Invitation_Bulk_Admission_Evidence $admission,
        WEM_Invitation_Bulk_Attempt_Storage $attempts,
        WEM_Invitation_Custody_Issuance_Bridge $bridge,
        int $actor_id
    ) {
        if ($actor_id <= 0 || !user_can($actor_id, 'manage_options')) {
            throw new RuntimeException('Bulk invitation administration is not authorized.');
        }
        $this->lock = $lock;
        $this->admission = $admission;
        $this->attempts = $attempts;
        $this->bridge = $bridge;
        $this->actor_id = $actor_id;
    }

    public function run(string $operation_id, int $event_id, array $guest_ids, int $guest_id): array
    {
        $lock = $this->lock;
        $admission = $this->admission;
        $attempts = $this->attempts;
        $bridge = $this->bridge;
        $actor_id = $this->actor_id;

        $coordinator = new WEM_Invitation_Bulk_Admitted_Locked_Issuance(
            static fn (int $id, callable $callback) => $lock->run($id, $callback),
            static fn (string $operation, int $event, array $ids, int $id): string =>
                $admission->admit_and_classify($operation, $event, $ids, $id),
            static fn (string $operation, int $id) => $attempts->mark_started($operation, $id),
            static fn (int $id): array => $bridge->issue($id, $actor_id, $event_id, $operation_id)
        );

        return $coordinator->run($operation_id, $event_id, $guest_ids, $guest_id);
    }
}
