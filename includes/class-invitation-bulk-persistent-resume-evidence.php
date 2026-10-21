<?php

/**
 * WEM-73: fail-closed classification of externally attested issuance state.
 *
 * The input is evidence, not a claim made by this class. Production must
 * collect committed credential state and independent durable attempt history.
 */
final class WEM_Invitation_Bulk_Persistent_Resume_Evidence
{
    public function classify($custody, $evidence): string
    {
        if (is_array($custody)) {
            return 'custodied';
        }

        if (!is_array($evidence)
            || !isset($evidence['credential_count'])
            || !is_int($evidence['credential_count'])
            || $evidence['credential_count'] !== 0
            || ($evidence['attempt_verified'] ?? null) !== true
            || ($evidence['attempted'] ?? null) !== false) {
            return 'indeterminate';
        }

        return 'not_started';
    }

    public function require_not_started($custody, $evidence): void
    {
        if ($this->classify($custody, $evidence) !== 'not_started') {
            throw new RuntimeException('Invitation issuance state is indeterminate.');
        }
    }
}
