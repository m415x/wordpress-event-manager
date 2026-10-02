<?php
if (!defined('ABSPATH')) exit;

class WEM_Taxonomy_Manager {

    public function __construct() {
        add_action('init', [$this, 'register_taxonomy']);
    }

    public function register_taxonomy() {
        $labels = array(
            'name'              => 'Eventos',
            'singular_name'     => 'Evento',
            'search_items'      => 'Buscar Eventos',
            'all_items'         => 'Todos los Eventos',
            'parent_item'       => 'Evento Padre',
            'parent_item_colon' => 'Evento Padre:',
            'edit_item'         => 'Editar Evento',
            'update_item'       => 'Actualizar Evento',
            'add_new_item'      => 'Añadir Nuevo Evento',
            'new_item_name'     => 'Nombre del Nuevo Evento',
            'menu_name'         => 'Eventos',
        );

        $args = array(
            'hierarchical'      => false,
            'labels'            => $labels,
            'show_ui'           => true,
            'show_admin_column' => true,
            'query_var'         => true,
            'rewrite'           => array('slug' => 'evento'),
            'show_in_rest'      => true,
        );

        register_taxonomy('evento', array('invitado'), $args);
    }
}
