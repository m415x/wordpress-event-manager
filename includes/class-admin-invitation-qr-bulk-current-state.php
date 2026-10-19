<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Fresh, fail-closed status validation for one selected bulk QR guest.
 *
 * The injected reader must retrieve current server-owned credential state.
 * This boundary never treats an earlier preview as authorization.
 */
final class WEM_Admin_Invitation_QR_Bulk_Current_State
{
    private $reader;

    public function __construct(callable $reader)
    {
        $this->reader = $reader;
    }

    public function permits($guest_id, $operation): bool
    {
        if (!is_int($guest_id) || $guest_id <= 0) {
            return false;
        }

        try {
            $projection = ($this->reader)($guest_id);
            if (!is_array($projection)
                || !array_key_exists('valid_guest', $projection)
                || !is_bool($projection['valid_guest'])
                || !isset($projection['credentials'])
                || !is_array($projection['credentials'])
            ) {
                return false;
            }

            $current_status = WEM_Admin_Invitation_QR_Bulk_Eligibility::classify(
                $projection['valid_guest'],
                $projection['credentials']
            );

            return WEM_Admin_Invitation_QR_Bulk_Revalidation::permits(
                $operation,
                $current_status
            );
        } catch (Throwable $error) {
            return false;
        }
    }
}
