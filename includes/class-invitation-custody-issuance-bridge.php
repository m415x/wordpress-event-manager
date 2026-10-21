<?php

/**
 * WEM-73 adapter: delegates issuance exclusively to the WEM-33 lifecycle.
 * No retry or automatic compensation is permitted after an uncertain commit.
 */
final class WEM_Invitation_Custody_Issuance_Bridge
{
    private $credential_service;
    private $journal;

    public function __construct($credential_service, $journal)
    {
        $this->credential_service = $credential_service;
        $this->journal = $journal;
    }

    public function issue(int $guest_id, int $actor_user_id, int $event_id, string $operation_id): array
    {
        if ($guest_id <= 0 || $actor_user_id <= 0 || $event_id <= 0
            || !preg_match('/^[A-Za-z0-9_-]{1,128}$/D', $operation_id)) {
            throw new InvalidArgumentException('Invalid private invitation issuance request.');
        }

        // WEM-33 invokes the callback after insert and BEFORE its COMMIT.
        // Exceptions are propagated; only WEM-33 controls its transaction.
        return $this->credential_service->issue(
            $guest_id,
            $actor_user_id,
            function (array $record) use ($guest_id, $event_id, $operation_id): void {
                if (($record['guest_id'] ?? null) !== $guest_id) {
                    throw new RuntimeException('Invitation custody guest mismatch.');
                }
                $record['event_id'] = $event_id;
                $record['operation_id'] = $operation_id;
                $this->journal->custody($record);
            }
        );
    }
}
