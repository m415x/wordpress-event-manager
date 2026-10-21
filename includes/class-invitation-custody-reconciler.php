<?php

/**
 * Pure WEM-73 verification policy. It never issues or recovers a credential.
 */
if (!defined('ABSPATH')) {
    exit;
}

final class WEM_Invitation_Custody_Reconciler
{
    public function is_exportable($custody, $credential): bool
    {
        if (!is_array($custody) || !is_array($credential)) {
            return false;
        }

        foreach (array('guest_id', 'event_id', 'generation') as $field) {
            if (!isset($custody[$field], $credential[$field])
                || !is_int($custody[$field]) || !is_int($credential[$field])
                || $custody[$field] <= 0 || $custody[$field] !== $credential[$field]) {
                return false;
            }
        }

        if (!isset($custody['token']) || !is_string($custody['token'])
            || !preg_match('/^[A-Za-z0-9_-]{43}$/D', $custody['token'])
            || !isset($custody['token_digest'], $credential['token_digest'])
            || !is_string($custody['token_digest'])
            || !is_string($credential['token_digest'])
            || !hash_equals(hash('sha256', $custody['token']), $custody['token_digest'])
            || !hash_equals($custody['token_digest'], $credential['token_digest'])) {
            return false;
        }

        return ($credential['status'] ?? null) === 'active'
            && ($credential['active_slot'] ?? null) === 1;
    }
}
