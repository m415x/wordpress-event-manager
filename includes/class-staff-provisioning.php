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
}
