<?php

if (!defined('ABSPATH')) {
    exit;
}

/** WEM-86: fail-closed server-authorized cancellation of participation. */
final class WEM_Seating_Participation_Cancellation_Service
{
    public function cancel($guestId, $reason)
    {
        throw new RuntimeException('Participation cancellation is not available.');
    }
}
