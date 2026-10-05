<?php

if (!defined('ABSPATH')) {
    exit;
}

final class WEM_Authorization
{
    private const VIEW_CAPABILITY = 'wem_view_event_guests';

    private const OPERATE_CAPABILITY = 'wem_operate_event_guests';

    private const EVENT_SCOPE_META_KEY = 'wem_authorized_event_ids';

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
        $user_id = (int) $user_id;
        if ($user_id <= 0) {
            return array();
        }

        $event_ids = get_user_meta($user_id, self::EVENT_SCOPE_META_KEY, true);
        if (!is_array($event_ids)) {
            return array();
        }

        $event_ids = array_values(
            array_unique(
                array_filter(
                    array_map('absint', $event_ids)
                )
            )
        );

        return $event_ids;
    }

    public static function can_view_guest($user_id, $post_id)
    {
        return self::can_access_guest(
            $user_id,
            $post_id,
            self::VIEW_CAPABILITY
        );
    }

    public static function can_operate_guest($user_id, $post_id)
    {
        return self::can_access_guest(
            $user_id,
            $post_id,
            self::OPERATE_CAPABILITY
        );
    }

    private static function can_access_guest($user_id, $post_id, $capability)
    {
        $user_id = (int) $user_id;
        if ($user_id <= 0 || !user_can($user_id, $capability)) {
            return false;
        }

        $event_id = self::get_guest_event_term_id($post_id);
        if ($event_id === null) {
            return false;
        }

        return in_array($event_id, self::get_authorized_event_ids($user_id), true);
    }
}
