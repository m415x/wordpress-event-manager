<?php

if (!defined('ABSPATH')) {
    exit;
}

/** WEM-85: fail-closed administrative reassignment boundary. */
final class WEM_Seating_Administrative_Reassignment_Service
{
    public function reassign($guestId, $targetTableId, $reason)
    {
        throw new RuntimeException('Administrative seating reassignment is not available.');
    }
}
