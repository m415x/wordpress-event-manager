<?php

/**
 * WEM-73: enforce the per-guest critical section for an issuance attempt.
 *
 * Injected callbacks must be wired to durable, authoritative implementations.
 * This coordinator does not retry failures or infer non-issuance from absence.
 */
final class WEM_Invitation_Bulk_Locked_Issuance
{
    private $lock;
    private $evidence;
    private $attempt;
    private $issuer;

    public function __construct(callable $lock, callable $evidence, callable $attempt, callable $issuer)
    {
        $this->lock = $lock;
        $this->evidence = $evidence;
        $this->attempt = $attempt;
        $this->issuer = $issuer;
    }

    public function run(int $guest_id)
    {
        if ($guest_id <= 0) {
            throw new InvalidArgumentException('Invalid invitation guest.');
        }

        return ($this->lock)($guest_id, function () use ($guest_id) {
            $state = ($this->evidence)($guest_id);
            if ($state !== 'not_started') {
                throw new RuntimeException('Invitation issuance state does not permit a new issue.');
            }
            ($this->attempt)($guest_id);
            return ($this->issuer)($guest_id);
        });
    }
}
