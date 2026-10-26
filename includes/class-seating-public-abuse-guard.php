<?php
if (!defined('ABSPATH')) { exit; }

/**
 * WEM-93/T6: reserved deny-by-default abuse boundary.
 * Do not permit public operations until a bounded, atomic guard is verified.
 */
final class WEM_Seating_Public_Abuse_Guard
{
    public function permits_request()
    {
        return false;
    }
}
