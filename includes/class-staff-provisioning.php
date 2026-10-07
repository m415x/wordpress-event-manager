<?php

if (!defined('ABSPATH')) {
    exit;
}

final class WEM_Staff_Provisioning
{
    public const STATE_NONE = 'none';

    public const STATE_VIEWER = 'viewer';

    public const STATE_OPERATOR = 'operator';

    public const STATE_NON_CANONICAL = 'non_canonical';

    private const VIEW_CAPABILITY = 'wem_view_event_guests';

    private const OPERATE_CAPABILITY = 'wem_operate_event_guests';

    public static function get_state($user_id)
    {
        $user_id = (int) $user_id;
        if ($user_id <= 0 || !get_userdata($user_id)) {
            return null;
        }

        $can_view = user_can($user_id, self::VIEW_CAPABILITY);
        $can_operate = user_can($user_id, self::OPERATE_CAPABILITY);

        if (!$can_view && !$can_operate) {
            return self::STATE_NONE;
        }

        if ($can_view && !$can_operate) {
            return self::STATE_VIEWER;
        }

        if ($can_view && $can_operate) {
            return self::STATE_OPERATOR;
        }

        return self::STATE_NON_CANONICAL;
    }

    public static function apply_preset($user_id, $state)
    {
        $user_id = (int) $user_id;
        $user = $user_id > 0 ? get_userdata($user_id) : false;

        if (!$user instanceof WP_User) {
            return false;
        }

        if (
            !in_array(
                $state,
                array(self::STATE_NONE, self::STATE_VIEWER, self::STATE_OPERATOR),
                true
            )
        ) {
            return false;
        }

        self::set_effective_capability(
            $user,
            self::VIEW_CAPABILITY,
            $state === self::STATE_VIEWER || $state === self::STATE_OPERATOR
        );
        self::set_effective_capability(
            $user,
            self::OPERATE_CAPABILITY,
            $state === self::STATE_OPERATOR
        );

        return self::get_state($user_id) === $state;
    }

    private static function set_effective_capability($user, $capability, $enabled)
    {
        if ($enabled) {
            $user->add_cap($capability, true);
            return;
        }

        $user->remove_cap($capability);

        if ($user->has_cap($capability)) {
            $user->add_cap($capability, false);
        }
    }
}
