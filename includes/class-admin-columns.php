<?php

class WEM_Admin_Columns {
    
    public function setup_columns() {
        add_filter('manage_invitado_posts_columns', array($this, 'modify_columns'));
        add_action('manage_invitado_posts_custom_column', array($this, 'render_columns'), 10, 2);
        add_action('restrict_manage_posts', array($this, 'add_filters'));
        add_action('pre_get_posts', array($this, 'handle_filters'));
        add_filter('posts_search', array($this, 'extend_search'), 10, 2);
    }
    
    public function modify_columns($cols) {
        $cols_new = array();
        $cols_new['cb'] = $cols['cb'];
        $cols_new['title'] = 'Ticket';
        $cols_new['c8_nombre'] = 'Nombre';
        $cols_new['c8_organizacion'] = 'Organización';
        $cols_new['c8_mesa'] = 'Mesa';
        $cols_new['evento'] = 'Evento';
        $cols_new['c8_checkin'] = 'Check-in';
        $cols_new['date'] = $cols['date'];
        return $cols_new;
    }
    
    public function render_columns($column, $post_id) {
        switch ($column) {
            case 'c8_nombre':
                echo esc_html(get_post_meta($post_id, 'c8_nombre', true));
                break;
                
            case 'c8_organizacion':
                echo esc_html(get_post_meta($post_id, 'c8_organizacion', true));
                break;
                
            case 'c8_mesa':
                echo esc_html(get_post_meta($post_id, 'c8_mesa', true));
                break;
                
            case 'evento':
                $terms = get_the_terms($post_id, 'evento');
                if ($terms && !is_wp_error($terms)) {
                    echo esc_html($terms[0]->name);
                }
                break;
                
            case 'c8_checkin':
                $this->render_checkin_column($post_id);
                break;
        }
    }
    
    private function render_checkin_column($post_id) {
        $checkin = get_post_meta($post_id, 'c8_checkin', true);
        
        if ($checkin) {
            echo '<span style="color:green">Ingresó</span> — ' . esc_html(get_post_meta($post_id, 'c8_checkin_at', true));
            $by = get_post_meta($post_id, 'c8_checkin_by', true);
            if ($by) echo ' ('.esc_html($by).')';
        } else {
            echo '<span style="color:orange">Pendiente</span>';
        }
    }
    
    public function add_filters() {
        global $typenow;
        if ($typenow !== 'invitado') return;

        $this->render_evento_filter();
        $this->render_organizacion_filter();
        $this->render_mesa_filter();
        $this->render_checkin_status_filter();
    }
    
    private function render_evento_filter() {
        $taxonomy = 'evento';
        $selected = isset($_GET[$taxonomy]) ? $_GET[$taxonomy] : '';
        
        wp_dropdown_categories(array(
            'show_option_all' => 'Todos los eventos',
            'taxonomy'        => $taxonomy,
            'name'            => $taxonomy,
            'selected'        => $selected,
            'value_field'     => 'slug',
            'orderby'         => 'name',
            'hide_empty'      => false,
            'hierarchical'    => true,
        ));
    }
    
    private function render_organizacion_filter() {
        $org_val = isset($_GET['c8_organizacion']) ? esc_attr($_GET['c8_organizacion']) : '';
        echo '<input type="text" name="c8_organizacion" placeholder="Filtrar por organización" value="'.$org_val.'" style="margin-left:8px;">';
    }
    
    private function render_mesa_filter() {
        $mesa_val = isset($_GET['c8_mesa']) ? esc_attr($_GET['c8_mesa']) : '';
        echo '<input type="text" name="c8_mesa" placeholder="Filtrar por mesa" value="'.$mesa_val.'" style="margin-left:8px;">';
    }
    
    private function render_checkin_status_filter() {
        $st = isset($_GET['c8_checkin_status']) ? esc_attr($_GET['c8_checkin_status']) : '';
        echo '<select name="c8_checkin_status" style="margin-left:8px;">
                <option value="">Todos los estados</option>
                <option value="1" '.selected($st,'1',false).'>Ingresado</option>
                <option value="0" '.selected($st,'0',false).'>Pendiente</option>
              </select>';
    }
    
    public function handle_filters($query) {
        global $pagenow, $typenow;
        if (!is_admin() || $pagenow !== 'edit.php' || $typenow !== 'invitado') return;

        $this->apply_evento_filter($query);
        $this->apply_organizacion_filter($query);
        $this->apply_mesa_filter($query);
        $this->apply_checkin_status_filter($query);
    }
    
    private function apply_evento_filter($query) {
        if (!empty($_GET['evento'])) {
            $query->set('tax_query', array(array(
                'taxonomy' => 'evento',
                'field' => 'slug',
                'terms' => sanitize_text_field($_GET['evento']),
            )));
        }
    }
    
    private function apply_organizacion_filter($query) {
        if (!empty($_GET['c8_organizacion'])) {
            $meta = $query->get('meta_query') ?: array();
            $meta[] = array(
                'key' => 'c8_organizacion',
                'value' => sanitize_text_field($_GET['c8_organizacion']),
                'compare' => 'LIKE'
            );
            $query->set('meta_query', $meta);
        }
    }
    
    private function apply_mesa_filter($query) {
        if (!empty($_GET['c8_mesa'])) {
            $meta = $query->get('meta_query') ?: array();
            $meta[] = array(
                'key' => 'c8_mesa',
                'value' => sanitize_text_field($_GET['c8_mesa']),
                'compare' => 'LIKE'
            );
            $query->set('meta_query', $meta);
        }
    }
    
    private function apply_checkin_status_filter($query) {
        if (isset($_GET['c8_checkin_status']) && $_GET['c8_checkin_status'] !== '') {
            $meta = $query->get('meta_query') ?: array();
            $meta[] = array(
                'key' => 'c8_checkin',
                'value' => intval($_GET['c8_checkin_status']),
                'compare' => '='
            );
            $query->set('meta_query', $meta);
        }
    }
    
    public function extend_search($search, $query) {
        global $wpdb;
        
        if (is_admin() && $query->is_main_query() && $query->get('post_type') === 'invitado' && $search) {
            $s = $query->get('s');
            $s_esc = esc_sql($wpdb->esc_like($s));
            $search = " AND ( ({$wpdb->posts}.post_title LIKE '%{$s_esc}%') OR EXISTS (
                SELECT 1 FROM {$wpdb->postmeta} pm WHERE pm.post_id = {$wpdb->posts}.ID
                AND pm.meta_key = 'c8_nombre' AND pm.meta_value LIKE '%{$s_esc}%'
            ) ) ";
        }
        
        return $search;
    }
}