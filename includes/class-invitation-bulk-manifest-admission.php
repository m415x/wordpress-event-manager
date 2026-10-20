<?php

/**
 * WEM-73: admit an immutable private batch manifest.
 * The reader and creator must be backed by the same trusted durable store.
 */
final class WEM_Invitation_Bulk_Manifest_Admission
{
    private $read;
    private $create;

    public function __construct(callable $read, callable $create)
    {
        $this->read = $read;
        $this->create = $create;
    }

    public function admit(string $operation_id, int $event_id, array $guest_ids): array
    {
        if (!preg_match('/^[A-Za-z0-9_-]{1,128}$/D', $operation_id) || $event_id <= 0
            || !$guest_ids || array_values($guest_ids) !== $guest_ids) {
            throw new RuntimeException('Invalid invitation batch admission.');
        }

        $seen = array();
        foreach ($guest_ids as $id) {
            if (!is_int($id) || $id <= 0 || isset($seen[$id])) {
                throw new RuntimeException('Invalid invitation batch selection.');
            }
            $seen[$id] = true;
        }

        $expected = array(
            'operation_id' => $operation_id,
            'event_id' => $event_id,
            'guest_ids' => $guest_ids,
        );
        $existing = ($this->read)($operation_id);
        if ($existing !== null) {
            if (!is_array($existing) || $existing !== $expected) {
                throw new RuntimeException('Invitation batch manifest identity mismatch.');
            }
            return $guest_ids;
        }

        ($this->create)($operation_id, $event_id, $guest_ids);
        // Verify actual durable publication before admitting any guest.
        if (($this->read)($operation_id) !== $expected) {
            throw new RuntimeException('Invitation batch manifest persistence was not verified.');
        }
        return $guest_ids;
    }
}
