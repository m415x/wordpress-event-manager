<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Fail-closed operation-to-current-status rules for explicit bulk QR actions.
 *
 * This predicate does not read persistent state and does not authorize a
 * mutation. The server must obtain the current state for each guest, then
 * delegate eligible actions to the unchanged WEM-33 credential service.
 */
final class WEM_Admin_Invitation_QR_Bulk_Revalidation
{
    public static function permits($operation, $current_status): bool
    {
        if ($operation === 'issue') {
            return $current_status === 'never_issued';
        }

        if ($operation === 'reissue') {
            return $current_status === 'inactive_reissue';
        }

        return false;
    }
}
