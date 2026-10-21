<?php

/**
 * WEM-73: conservative classification of a resumed invitation operation.
 * It does not inspect the database or attest that no issuance was attempted.
 * Production callers must supply independently verified attempt evidence.
 */
final class WEM_Invitation_Bulk_Resume_State
{
    public function classify($custody, $attempt_evidence): string
    {
        if (is_array($custody)) {
            return 'custodied';
        }

        if (!is_array($attempt_evidence)
            || ($attempt_evidence['verified'] ?? null) !== true
            || ($attempt_evidence['attempted'] ?? null) !== false) {
            return 'indeterminate';
        }

        return 'not_started';
    }

    public function require_not_started($custody, $attempt_evidence): void
    {
        if ($this->classify($custody, $attempt_evidence) !== 'not_started') {
            throw new RuntimeException('Invitation issuance state is indeterminate.');
        }
    }
}
