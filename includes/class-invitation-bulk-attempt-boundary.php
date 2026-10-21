<?php

/**
 * WEM-73: gate issuance on durable registration of an attempt.
 *
 * The injected marker must synchronously persist and attest the attempt or
 * throw. This boundary itself has no persistence or retry behavior.
 */
final class WEM_Invitation_Bulk_Attempt_Boundary
{
    private $mark_started;

    public function __construct(callable $mark_started)
    {
        $this->mark_started = $mark_started;
    }

    public function issue(string $operation_id, int $guest_id, callable $issuer)
    {
        if (!preg_match('/^[A-Za-z0-9_-]{1,128}$/D', $operation_id) || $guest_id <= 0) {
            throw new InvalidArgumentException('Invalid invitation attempt identity.');
        }

        // On failure, never call WEM-33. On success, do not retry the issuer
        // in this boundary: its COMMIT outcome may become indeterminate.
        ($this->mark_started)($operation_id, $guest_id);

        return $issuer($guest_id);
    }
}
