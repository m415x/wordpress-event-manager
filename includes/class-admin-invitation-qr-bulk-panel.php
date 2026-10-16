<?php

if (!defined('ABSPATH')) {
    exit;
}

/** Admin-only manual selection and one-shot presentation of bulk invitation QR codes. */
final class WEM_Admin_Invitation_QR_Bulk_Panel
{
    private $page_hook;

    public function register_hooks()
    {
        add_action('admin_menu', array($this, 'register_page'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
    }

    public function register_page()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $this->page_hook = add_submenu_page(
            'edit.php?post_type=invitado',
            'QR de invitaciones por lote',
            'QR por lote',
            'manage_options',
            'wem-invitation-qr-bulk',
            array($this, 'render')
        );
    }

    public function enqueue_assets($hook)
    {
        if (!current_user_can('manage_options') || $hook !== $this->page_hook) {
            return;
        }

        wp_enqueue_script(
            'wem-admin-invitation-qr-bulk',
            WEM_URL . 'assets/js/admin-invitation-qr-bulk.js',
            array(),
            '2.2.1',
            true
        );
    }

    public function render()
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('No tenés permiso para administrar códigos QR.', 'wordpress-event-manager'));
        }

        echo '<div class="wrap" data-wem-bulk-qr-panel';
        echo ' data-ajax-url="' . esc_url(admin_url('admin-ajax.php')) . '"';
        echo ' data-nonce="' . esc_attr(wp_create_nonce('wem_invitation_qr_bulk_admin')) . '">';
        echo '<h1>' . esc_html__('QR de invitaciones por lote', 'wordpress-event-manager') . '</h1>';
        echo '<p>' . esc_html__('Ingresá los ID de invitados separados por comas, espacios o saltos de línea. Máximo 25 invitados por operación.', 'wordpress-event-manager') . '</p>';
        echo '<p><label for="wem-bulk-guest-ids">' . esc_html__('Invitados seleccionados (ID)', 'wordpress-event-manager') . '</label></p>';
        echo '<textarea id="wem-bulk-guest-ids" rows="5" cols="55" autocomplete="off"></textarea>';
        echo '<p><button type="button" class="button" data-wem-bulk-action="preview">' . esc_html__('Previsualizar elegibilidad', 'wordpress-event-manager') . '</button></p>';
        echo '<div data-wem-bulk-preview role="status" aria-live="polite"></div>';
        echo '<p><button type="button" class="button button-primary" data-wem-bulk-action="issue" disabled>' . esc_html__('Emitir QR seleccionados', 'wordpress-event-manager') . '</button> ';
        echo '<button type="button" class="button" data-wem-bulk-action="reissue" disabled>' . esc_html__('Reemitir QR seleccionados', 'wordpress-event-manager') . '</button></p>';
        echo '<p>' . esc_html__('La previsualización no reserva credenciales. Las operaciones se verifican otra vez en el servidor. Guardá los QR y enlaces emitidos ahora: no se pueden recuperar después.', 'wordpress-event-manager') . '</p>';
        echo '<div data-wem-bulk-output role="status" aria-live="polite"></div>';
        echo '</div>';
    }
}
