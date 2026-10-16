<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Read-only classification for explicit administrative bulk QR operations.
 *
 * This is only a projection. Actual issuance must revalidate using the
 * authoritative WEM-33 credential service at mutation time.
 */
final class WEM_Admin_Invitation_QR_Bulk_Eligibility
{
    /**
     * @param bool $valid_guest Whether the candidate is a valid invitation guest.
     * @param array $credentials Credential status and active_slot projections.
     * @return string
     */
    public static function classify($valid_guest, array $credentials): string
    {
        if ($valid_guest !== true) {
            return 'invalid';
        }

        if ($credentials === array()) {
            return 'never_issued';
        }

        $active_count = 0;
        foreach ($credentials as $credential) {
            if (!is_array($credential)
                || !array_key_exists('status', $credential)
                || !array_key_exists('active_slot', $credential)
            ) {
                return 'invalid';
            }

            if ($credential['status'] === 'active') {
                if ((int) $credential['active_slot'] !== 1) {
                    return 'invalid';
                }
                ++$active_count;
            } elseif (in_array($credential['status'], array('rotated', 'revoked'), true)) {
                if ($credential['active_slot'] !== null) {
                    return 'invalid';
                }
            } else {
                return 'invalid';
            }
        }

        if ($active_count > 1) {
            return 'invalid';
        }

        return $active_count === 1 ? 'active_unrecoverable' : 'inactive_reissue';
    }
}
