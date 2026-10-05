<?php

if (!defined('ABSPATH')) {
    exit;
}

final class WEM_Authorization
{
    public static function get_guest_event_term_id($post_id)
    {
        return null;
    }

    public static function get_authorized_event_ids($user_id)
    {
        return array();
    }

    public static function can_view_guest($user_id, $post_id)
    {
        return false;
    }

    public static function can_operate_guest($user_id, $post_id)
    {
        return false;
    }
}
