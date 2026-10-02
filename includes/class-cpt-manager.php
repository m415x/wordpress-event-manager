<?php
if (!defined('ABSPATH')) exit;

class WEM_CPT_Manager {

    public function __construct() {
        add_action('init', [$this, 'register_cpt']);
    }

    public function register_cpt() {
        $labels = array(
            'name'               => 'Invitados',
            'singular_name'      => 'Invitado',
            'menu_name'          => 'Invitados',
            'name_admin_bar'     => 'Invitado',
            'add_new'            => 'Añadir nuevo',
            'add_new_item'       => 'Añadir nuevo invitado',
            'new_item'           => 'Nuevo invitado',
            'edit_item'          => 'Editar invitado',
            'view_item'          => 'Ver invitado',
            'all_items'          => 'Todos los invitados',
            'search_items'       => 'Buscar invitados',
            'not_found'          => 'No se encontraron invitados',
            'not_found_in_trash' => 'No hay invitados en la papelera',
        );

        $args = array(
            'labels'             => $labels,
            'public'             => false,
            'show_ui'            => true,
            'show_in_menu'       => current_user_can('administrator') ? true : false,
            'menu_position'      => 6,
            'menu_icon'          => 'dashicons-tickets',
            'supports'           => array('title', 'custom-fields'),
            'capability_type'    => 'invitado',
            'map_meta_cap'       => false,
            'capabilities' => array(
                'edit_post'              => 'administrator',
                'read_post'              => 'administrator',
                'delete_post'            => 'administrator',
                'edit_posts'             => 'administrator',
                'edit_others_posts'      => 'administrator',
                'delete_posts'           => 'administrator',
                'publish_posts'          => 'administrator',
                'read_private_posts'     => 'administrator',
            ),
        );

        register_post_type('invitado', $args);
    }
}
