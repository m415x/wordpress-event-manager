<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Validation of an explicitly selected, bounded set of invitation guests.
 *
 * This check is not authorization. Every mutation must separately validate
 * administrative capability, current guest eligibility and WEM-33 state.
 */
final class WEM_Admin_Invitation_QR_Bulk_Selection
{
    private const MAX_GUESTS = 25;

    /**
     * @param mixed $guest_ids
     * @param mixed $operation
     * @return int[]
     */
    public static function validate($guest_ids, $operation): array
    {
        if (!in_array($operation, array('issue', 'reissue'), true)
            || !is_array($guest_ids)
            || count($guest_ids) === 0
            || count($guest_ids) > self::MAX_GUESTS
        ) {
            throw new InvalidArgumentException('Invalid invitation QR bulk selection.');
        }

        $seen = array();
        foreach ($guest_ids as $guest_id) {
            if (!is_int($guest_id) || $guest_id <= 0 || isset($seen[$guest_id])) {
                throw new InvalidArgumentException('Invalid invitation QR bulk selection.');
            }
            $seen[$guest_id] = true;
        }

        return array_values($guest_ids);
    }
}
