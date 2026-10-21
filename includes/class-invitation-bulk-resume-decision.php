<?php

/**
 * WEM-73: conservative resume decision over independently collected evidence.
 * "reconcile" is not permission to export; it requires a separate live
 * credential/digest/event match. "not_started" requires trusted batch evidence.
 */
final class WEM_Invitation_Bulk_Resume_Decision
{
    public function decide($attempted, $custody, $credential_count, $batch_verified = false): string
    {
        if (!is_bool($attempted) || !is_bool($batch_verified)
            || !is_int($credential_count) || $credential_count < 0
            || ($custody !== null && !is_array($custody))) {
            return 'indeterminate';
        }

        if ($attempted && is_array($custody) && $credential_count === 1) {
            return 'reconcile';
        }

        if (!$attempted && $custody === null && $credential_count === 0 && $batch_verified) {
            return 'not_started';
        }

        return 'indeterminate';
    }
}
