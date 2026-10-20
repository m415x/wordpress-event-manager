<?php

/**
 * WEM-73: recover an already-issued bearer without issuing or retrying.
 * Evidence and reconciliation are supplied by authoritative durable readers.
 */
final class WEM_Invitation_Bulk_Recovered_Credential
{
    private $evidence;
    private $journal;
    private $reconcile;

    public function __construct(callable $evidence, callable $journal, callable $reconcile)
    {
        $this->evidence = $evidence;
        $this->journal = $journal;
        $this->reconcile = $reconcile;
    }

    public function recover(string $operation_id, int $event_id, int $guest_id): array
    {
        if (!preg_match('/^[A-Za-z0-9_-]{1,128}$/D', $operation_id)
            || $event_id <= 0 || $guest_id <= 0) {
            throw new RuntimeException('Invalid invitation recovery request.');
        }

        if (($this->evidence)($operation_id, $guest_id) !== 'reconcile') {
            throw new RuntimeException('Invitation recovery evidence is not reconciled.');
        }

        $record = ($this->journal)($operation_id, $guest_id);
        if (!is_array($record)
            || ($record['operation_id'] ?? null) !== $operation_id
            || ($record['event_id'] ?? null) !== $event_id
            || ($record['guest_id'] ?? null) !== $guest_id
            || !isset($record['generation'], $record['token'])
            || !is_int($record['generation']) || $record['generation'] <= 0
            || !is_string($record['token']) || $record['token'] === '') {
            throw new RuntimeException('Invitation recovery identity mismatch.');
        }

        if (!(($this->reconcile)($record))) {
            throw new RuntimeException('Recovered invitation credential is not exportable.');
        }

        return $record;
    }
}
