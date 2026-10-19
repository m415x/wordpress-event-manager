<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Read-only eligibility preview for explicitly selected invitation guests.
 *
 * The preview is informational only; mutations must revalidate fresh state.
 */
final class WEM_Admin_Invitation_QR_Bulk_Preview
{
    private $reader;

    public function __construct($reader)
    {
        $this->reader = $reader;
    }

    public function inspect($guest_ids): array
    {
        $ids = WEM_Admin_Invitation_QR_Bulk_Selection::validate($guest_ids, 'issue');
        $results = array();

        foreach ($ids as $guest_id) {
            $eligibility = 'invalid';
            try {
                $state = $this->reader->read($guest_id);
                if (is_array($state)
                    && array_key_exists('valid_guest', $state)
                    && is_bool($state['valid_guest'])
                    && isset($state['credentials'])
                    && is_array($state['credentials'])
                ) {
                    $eligibility = WEM_Admin_Invitation_QR_Bulk_Eligibility::classify(
                        $state['valid_guest'],
                        $state['credentials']
                    );
                }
            } catch (Throwable $error) {
                // Never expose a reader exception or credential material.
                $eligibility = 'invalid';
            }

            $results[] = array(
                'guest_id' => $guest_id,
                'eligibility' => $eligibility,
            );
        }

        return $results;
    }
}
