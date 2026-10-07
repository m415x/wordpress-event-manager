<?php

if (!defined('ABSPATH')) {
    exit;
}

final class WEM_Staff_Assignment_Manager
{
    public function register_hooks()
    {
        add_action('show_user_profile', array($this, 'render_event_scope_fields'));
        add_action('edit_user_profile', array($this, 'render_event_scope_fields'));
        add_action('personal_options_update', array($this, 'save_event_scope_fields'));
        add_action('edit_user_profile_update', array($this, 'save_event_scope_fields'));
    }

    public function render_event_scope_fields($user)
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $provisioning_state = WEM_Staff_Provisioning::get_state($user->ID);
        $event_ids = WEM_Authorization::get_authorized_event_ids($user->ID);
        $events = get_terms(
            array(
                'taxonomy' => 'evento',
                'hide_empty' => false,
                'orderby' => 'name',
                'order' => 'ASC',
            )
        );

        if (is_wp_error($events)) {
            $events = array();
        }

        wp_nonce_field('wem_save_staff_event_scope', 'wem_staff_event_scope_nonce');
        ?>
        <h2><?php echo esc_html__('WEM operational access', 'wordpress-event-manager'); ?></h2>
        <table class="form-table" role="presentation">
            <tr>
                <th>
                    <label for="wem_staff_provisioning_preset">
                        <?php echo esc_html__('Operational preset', 'wordpress-event-manager'); ?>
                    </label>
                </th>
                <td>
                    <select
                        id="wem_staff_provisioning_preset"
                        name="wem_staff_provisioning_preset"
                    >
                        <?php if ($provisioning_state === WEM_Staff_Provisioning::STATE_NON_CANONICAL) : ?>
                            <option value="" selected>
                                <?php echo esc_html__('Non-canonical capability state — unchanged', 'wordpress-event-manager'); ?>
                            </option>
                        <?php endif; ?>
                        <option
                            value="<?php echo esc_attr(WEM_Staff_Provisioning::STATE_NONE); ?>"
                            <?php selected($provisioning_state, WEM_Staff_Provisioning::STATE_NONE); ?>
                        >
                            <?php echo esc_html__('None', 'wordpress-event-manager'); ?>
                        </option>
                        <option
                            value="<?php echo esc_attr(WEM_Staff_Provisioning::STATE_VIEWER); ?>"
                            <?php selected($provisioning_state, WEM_Staff_Provisioning::STATE_VIEWER); ?>
                        >
                            <?php echo esc_html__('Viewer', 'wordpress-event-manager'); ?>
                        </option>
                        <option
                            value="<?php echo esc_attr(WEM_Staff_Provisioning::STATE_OPERATOR); ?>"
                            <?php selected($provisioning_state, WEM_Staff_Provisioning::STATE_OPERATOR); ?>
                        >
                            <?php echo esc_html__('Operator', 'wordpress-event-manager'); ?>
                        </option>
                    </select>
                    <?php if ($provisioning_state === WEM_Staff_Provisioning::STATE_NON_CANONICAL) : ?>
                        <p class="description">
                            <?php echo esc_html__(
                                'Non-canonical WEM capabilities were detected. They remain unchanged unless you explicitly choose None, Viewer or Operator.',
                                'wordpress-event-manager'
                            ); ?>
                        </p>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th><?php echo esc_html__('Authorized events', 'wordpress-event-manager'); ?></th>
                <td>
                    <?php foreach ($events as $event) : ?>
                        <label>
                            <input
                                type="checkbox"
                                name="wem_authorized_event_ids[]"
                                value="<?php echo esc_attr($event->term_id); ?>"
                                <?php checked(in_array((int) $event->term_id, $event_ids, true)); ?>
                            >
                            <?php echo esc_html($event->name); ?>
                        </label><br>
                    <?php endforeach; ?>
                </td>
            </tr>
        </table>
        <?php
    }

    public function save_event_scope_fields($user_id)
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        if (
            !isset($_POST['wem_staff_event_scope_nonce'])
            || !wp_verify_nonce(
                sanitize_text_field(wp_unslash($_POST['wem_staff_event_scope_nonce'])),
                'wem_save_staff_event_scope'
            )
        ) {
            return;
        }

        $preset = isset($_POST['wem_staff_provisioning_preset'])
            ? sanitize_key(wp_unslash($_POST['wem_staff_provisioning_preset']))
            : '';

        if (
            in_array(
                $preset,
                array(
                    WEM_Staff_Provisioning::STATE_NONE,
                    WEM_Staff_Provisioning::STATE_VIEWER,
                    WEM_Staff_Provisioning::STATE_OPERATOR,
                ),
                true
            )
        ) {
            WEM_Staff_Provisioning::apply_preset($user_id, $preset);
        }

        $event_ids = isset($_POST['wem_authorized_event_ids'])
            ? (array) wp_unslash($_POST['wem_authorized_event_ids'])
            : array();

        WEM_Authorization::set_authorized_event_ids($user_id, $event_ids);
    }
}
