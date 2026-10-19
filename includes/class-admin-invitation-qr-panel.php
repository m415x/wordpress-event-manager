<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * One-shot invitation QR controls on the guest editor, restricted to admins.
 */
final class WEM_Admin_Invitation_QR_Panel
{
    public function register_hooks()
    {
        add_action('add_meta_boxes_invitado', array($this, 'register_metabox'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
    }

    public function register_metabox()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        add_meta_box(
            'wem_invitation_qr',
            __('QR de invitación', 'wordpress-event-manager'),
            array($this, 'render_metabox'),
            'invitado',
            'side',
            'default'
        );
    }

    public function render_metabox($post)
    {
        if (!current_user_can('manage_options') || !$post || $post->post_type !== 'invitado') {
            return;
        }

        echo '<div id="wem-invitation-qr-panel" data-guest-id="' . esc_attr((string) $post->ID) . '"';
        echo ' data-nonce="' . esc_attr(wp_create_nonce('wem_invitation_qr_admin')) . '"';
        echo ' data-ajax-url="' . esc_url(admin_url('admin-ajax.php')) . '">';
        echo '<p>' . esc_html__('Cada acción crea una nueva credencial. El QR y la URL solo están disponibles en esta respuesta. Guardalos de forma segura ahora.', 'wordpress-event-manager') . '</p>';
        echo '<p><button type="button" class="button button-primary" data-wem-qr-action="issue">' . esc_html__('Emitir QR', 'wordpress-event-manager') . '</button></p>';
        echo '<p><button type="button" class="button" data-wem-qr-action="rotate">' . esc_html__('Rotar QR', 'wordpress-event-manager') . '</button> ';
        echo '<button type="button" class="button" data-wem-qr-action="reissue">' . esc_html__('Reemitir QR', 'wordpress-event-manager') . '</button></p>';
        echo '<div data-wem-qr-output role="status" aria-live="polite"></div>';
        echo '</div>';
    }

    public function enqueue_assets($hook)
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        if (!in_array($hook, array('post.php', 'post-new.php'), true)) {
            return;
        }

        $screen = get_current_screen();
        if (!$screen || $screen->post_type !== 'invitado') {
            return;
        }

        wp_enqueue_script(
            'wem-admin-invitation-qr',
            WEM_URL . 'assets/js/admin-invitation-qr.js',
            array(),
            '2.2.1',
            true
        );
    }
}
