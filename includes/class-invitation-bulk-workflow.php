<?php

/**
 * WEM-73: compositional batch workflow. All callbacks must be wired by a
 * trusted WP-CLI composition root to real WordPress, MySQL, and private
 * storage. This class does not authorize issuance or infer safe resumption.
 */
final class WEM_Invitation_Bulk_Workflow
{
    private $preflight;
    private $lock;
    private $process;
    private $export;
    private $reconcile;

    public function __construct(
        callable $preflight,
        callable $lock,
        callable $process,
        callable $export,
        ?callable $reconcile = null
    ) {
        $this->preflight = $preflight;
        $this->lock = $lock;
        $this->process = $process;
        $this->export = $export;
        $this->reconcile = $reconcile;
    }

    public function run(array $request): void
    {
        $guests = ($this->preflight)($request);
        if (!is_array($guests)) {
            throw new RuntimeException('Invalid invitation batch selection.');
        }
        $seen = array();
        foreach ($guests as $guest_id) {
            if (!is_int($guest_id) || $guest_id <= 0 || isset($seen[$guest_id])) {
                throw new RuntimeException('Invalid or duplicate batch guest.');
            }
            $seen[$guest_id] = true;
        }

        $records = array();
        foreach ($guests as $guest_id) {
            $record = ($this->lock)($guest_id, function () use ($guest_id) {
                $candidate = ($this->process)($guest_id);
                if (!is_array($candidate)
                    || ($candidate['guest_id'] ?? null) !== $guest_id) {
                    throw new RuntimeException('Invalid invitation batch result.');
                }
                if ($this->reconcile !== null && !(($this->reconcile)($candidate))) {
                    throw new RuntimeException('Invitation reconciliation failed.');
                }
                return $candidate;
            });
            $records[] = $record;
        }

        // No partial CSV is published when a prior step fails.
        ($this->export)($records);
    }
}
