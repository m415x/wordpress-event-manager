<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * WEM-82 initial reservation entry point.
 *
 * Fail closed until T2 adds and tests server-owned guest identity,
 * eligibility, event/table locks and capacity enforcement.
 */
final class WEM_Seating_Reservation_Service
{
    public function reserve_initial(...$arguments)
    {
        throw new RuntimeException('Initial seating reservation is not yet available.');
    }
}
