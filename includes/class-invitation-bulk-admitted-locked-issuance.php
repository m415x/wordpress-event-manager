<?php

/**
 * WEM-73: admits and classifies durable evidence within the per-guest lock.
 * A new issue is permitted only on independently verified 'not_started'.
 * No automatic retry is performed for reconciled or indeterminate states.
 */
final class WEM_Invitation_Bulk_Admitted_Locked_Issuance
{
    private $lock;
    private $admission_evidence;
    private $attempt;
    private $issue;

    public function __construct(
        callable $lock,
        callable $admission_evidence,
        callable $attempt,
        callable $issue
    ) {
        $this->lock = $lock;
        $this->admission_evidence = $admission_evidence;
        $this->attempt = $attempt;
        $this->issue = $issue;
    }

    public function run(string $operation_id, int $event_id, array $guest_ids, int $guest_id): array
    {
        if ($guest_id <= 0 || $event_id <= 0 || !in_array($guest_id, $guest_ids, true)) {
            throw new RuntimeException('Invalid admitted invitation guest.');
        }

        return ($this->lock)($guest_id, function () use ($operation_id, $event_id, $guest_ids, $guest_id): array {
            $state = ($this->admission_evidence)($operation_id, $event_id, $guest_ids, $guest_id);
            if ($state !== 'not_started') {
                throw new RuntimeException('Invitation issuance state does not permit a new issue.');
            }
            ($this->attempt)($operation_id, $guest_id);
            $issued = ($this->issue)($guest_id);
            if (!is_array($issued) || ($issued['guest_id'] ?? null) !== $guest_id) {
                throw new RuntimeException('Invalid invitation issuance result.');
            }
            return $issued;
        });
    }
}
