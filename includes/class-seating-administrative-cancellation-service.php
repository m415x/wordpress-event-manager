<?php

if (!defined('ABSPATH')) {
    exit;
}

/** WEM-84: fail-closed administrative cancellation boundary. */
final class WEM_Seating_Administrative_Cancellation_Service
{
    public function cancel($guestId, $reason)
    {
        throw new RuntimeException('Administrative seating cancellation is not available.');
    }
}
