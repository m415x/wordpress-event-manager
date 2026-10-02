<?php

if (!defined('ABSPATH')) exit;

/**
 * Helper function para obtener eventos de forma segura
 */
function wem_get_evento_terms($post_id) {
    $terms = get_the_terms($post_id, 'evento');
    if ($terms && !is_wp_error($terms) && !empty($terms)) {
        return $terms;
    }
    return false;
}

/**
 * Helper function para obtener datos del invitado
 */
function wem_get_invitado_data($post_id) {
    return array(
        'nombre' => get_post_meta($post_id, 'wem_nombre', true),
        'organizacion' => get_post_meta($post_id, 'wem_organizacion', true),
        'mesa' => get_post_meta($post_id, 'wem_mesa', true),
        'observaciones' => get_post_meta($post_id, 'wem_observaciones', true),
        'checkin' => get_post_meta($post_id, 'wem_checkin', true),
        'checkin_at' => get_post_meta($post_id, 'wem_checkin_at', true),
        'checkin_by' => get_post_meta($post_id, 'wem_checkin_by', true),
        'checkout' => get_post_meta($post_id, 'wem_checkout', true),
        'checkout_at' => get_post_meta($post_id, 'wem_checkout_at', true),
        'checkout_by' => get_post_meta($post_id, 'wem_checkout_by', true)
    );
}

/**
 * Helper function para verificar permisos
 */
function wem_current_user_can_manage() {
    return current_user_can('manage_options');
}

/**
 * Helper function para log de errores
 */
function wem_log_error($message) {
    if (WP_DEBUG === true) {
        error_log('WEM Error: ' . $message);
    }
}

/**
 * Helper function para obtener operador actual
 */
function wem_get_current_operator() {
    $user = wp_get_current_user();
    return $user && $user->exists() ? ($user->display_name ?: $user->user_login) : 'anon@'.$_SERVER['REMOTE_ADDR'];
}

/**
 * Helper function para sanitizar datos de búsqueda
 */
function wem_sanitize_search_query($query) {
    return sanitize_text_field(wp_unslash($query));
}