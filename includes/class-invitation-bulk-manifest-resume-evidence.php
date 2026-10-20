<?php

/**
 * WEM-73: pure resume policy bound to an immutable manifest.
 *
 * The caller must verify the manifest, attempt record, custody and MySQL
 * state independently. This class does not collect or attest that evidence.
 */
final class WEM_Invitation_Bulk_Manifest_Resume_Evidence
{
    public function classify($manifest, $operation_id, $guest_id, $attempted, $custody, $credential_count): string
    {
        if (!is_array($manifest)
            || !is_string($operation_id)
            || !isset($manifest['operation_id'], $manifest['event_id'], $manifest['guest_ids'])
            || $manifest['operation_id'] !== $operation_id
            || !is_int($manifest['event_id']) || $manifest['event_id'] <= 0
            || !is_int($guest_id) || $guest_id <= 0
            || !is_array($manifest['guest_ids'])
            || !in_array($guest_id, $manifest['guest_ids'], true)
            || !is_bool($attempted)
            || !is_int($credential_count) || $credential_count < 0
            || ($custody !== null && !is_array($custody))) {
            return 'indeterminate';
        }

        if ($attempted && is_array($custody) && $credential_count === 1
            && ($custody['guest_id'] ?? null) === $guest_id) {
            return 'reconcile';
        }

        if (!$attempted && $custody === null && $credential_count === 0) {
            return 'not_started';
        }

        return 'indeterminate';
    }
}
