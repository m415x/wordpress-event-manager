<?php

if (!defined('ABSPATH')) {
    exit;
}

final class WEM_Authorization
{
    public static function get_guest_event_term_id($post_id)
    {
        $terms = get_the_terms($post_id, 'evento');

        if (!$terms || is_wp_error($terms) || count($terms) !== 1) {
            return null;
        }

        return (int) $terms[0]->term_id;
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
