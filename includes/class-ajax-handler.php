<?php

class WEM_Ajax_Handler {
    
    public function register_ajax_handlers() {
        // Check-in actions
        add_action('wp_ajax_wem_checkin_ajax', array($this, 'handle_checkin_ajax'));
        add_action('wp_ajax_nopriv_wem_checkin_ajax', array($this, 'handle_checkin_ajax'));
        
        // List actions
        add_action('wp_ajax_wem_list_ajax', array($this, 'handle_list_ajax'));
        add_action('wp_ajax_nopriv_wem_list_ajax', array($this, 'handle_list_ajax'));
    }
    
    public function handle_checkin_ajax() {
        // WEM-11: deny all guest access until WEM-13 establishes authorization.
        wp_send_json_error(array('code' => 'guest_access_unavailable'), 403);

        $this->verify_nonce('wem_checkin_nonce');
        
        $post_id = $this->get_valid_post_id();
        $observ = $this->get_sanitized_observ();
        $check_action = $this->get_check_action();
        
        $this->process_checkin_action($post_id, $observ, $check_action);
    }
    
    public function handle_list_ajax() {
        // WEM-11: deny all guest access until WEM-13 establishes authorization.
        wp_send_json_error(array('code' => 'guest_access_unavailable'), 403);

        $q = isset($_POST['q']) ? wem_sanitize_search_query($_POST['q']) : '';
        $evento = isset($_POST['evento']) ? wem_sanitize_search_query($_POST['evento']) : '';
        $mesa = isset($_POST['mesa']) ? wem_sanitize_search_query($_POST['mesa']) : '';
        
        $posts = $this->get_filtered_invitados($q, $evento, $mesa);
        $this->render_list_table($posts);
    }
    
    private function verify_nonce($nonce_key) {
        $nonce = isset($_POST['nonce']) ? $_POST['nonce'] : '';
        if (!wp_verify_nonce($nonce, $nonce_key)) {
            wp_send_json_error('Nonce inválido');
        }
    }
    
    private function get_valid_post_id() {
        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        if (!$post_id || get_post_type($post_id) !== 'invitado') {
            wp_send_json_error('Ticket inválido');
        }
        return $post_id;
    }
    
    private function get_sanitized_observ() {
        return isset($_POST['observ']) ? sanitize_textarea_field($_POST['observ']) : '';
    }
    
    private function get_check_action() {
        return isset($_POST['check_action']) ? $_POST['check_action'] : 'checkin';
    }
    
    private function process_checkin_action($post_id, $observ, $check_action) {
        $current_time = current_time('Y-m-d H:i:s');
        $operator = wem_get_current_operator();
        
        switch($check_action) {
            case 'checkin':
                $this->process_checkin($post_id, $observ, $current_time, $operator);
                break;
                
            case 'checkout':
                $this->process_checkout($post_id, $current_time, $operator);
                break;
                
            case 'checkin_again':
                $this->process_checkin_again($post_id, $current_time, $operator);
                break;
                
            default:
                wp_send_json_error('Acción no válida');
        }
        
        wp_send_json_success(array('message' => 'Operación completada: ' . $current_time));
    }
    
    private function process_checkin($post_id, $observ, $current_time, $operator) {
        $already = get_post_meta($post_id, 'wem_checkin', true);
        if ($already) wp_send_json_error('Invitado ya ingresado');
        
        update_post_meta($post_id, 'wem_checkin', 1);
        update_post_meta($post_id, 'wem_checkin_at', $current_time);
        update_post_meta($post_id, 'wem_checkin_by', $operator);
        
        // Limpiar checkout si existe
        delete_post_meta($post_id, 'wem_checkout');
        delete_post_meta($post_id, 'wem_checkout_at');
        delete_post_meta($post_id, 'wem_checkout_by');
        
        if ($observ) {
            update_post_meta($post_id, 'wem_observaciones_checkin', $observ);
        }
    }
    
    private function process_checkout($post_id, $current_time, $operator) {
        $checked_in = get_post_meta($post_id, 'wem_checkin', true);
        if (!$checked_in) wp_send_json_error('Invitado no ha ingresado');
        
        $already_checked_out = get_post_meta($post_id, 'wem_checkout', true);
        if ($already_checked_out) wp_send_json_error('Invitado ya salió');
        
        update_post_meta($post_id, 'wem_checkout', 1);
        update_post_meta($post_id, 'wem_checkout_at', $current_time);
        update_post_meta($post_id, 'wem_checkout_by', $operator);
    }
    
    private function process_checkin_again($post_id, $current_time, $operator) {
        $checked_out = get_post_meta($post_id, 'wem_checkout', true);
        if (!$checked_out) wp_send_json_error('Invitado no ha salido');
        
        update_post_meta($post_id, 'wem_checkin_at', $current_time);
        update_post_meta($post_id, 'wem_checkin_by', $operator);
        
        // Limpiar checkout para permitir re-ingreso
        delete_post_meta($post_id, 'wem_checkout');
        delete_post_meta($post_id, 'wem_checkout_at');
        delete_post_meta($post_id, 'wem_checkout_by');
    }
    
    private function get_filtered_invitados($q, $evento, $mesa) {
        $args = array(
            'post_type' => 'invitado',
            'posts_per_page' => 200,
            'meta_key' => 'wem_organizacion',
            'orderby' => 'meta_value',
            'order' => 'ASC',
        );
        
        // Búsqueda múltiple
        if ($q) {
            $args['meta_query'] = array(
                'relation' => 'OR',
                array('key' => 'wem_ticket', 'value' => $q, 'compare' => 'LIKE'),
                array('key' => 'wem_nombre', 'value' => $q, 'compare' => 'LIKE'),
                array('key' => 'wem_organizacion', 'value' => $q, 'compare' => 'LIKE')
            );
        }
        
        // Búsqueda por mesa
        if ($mesa) {
            if (isset($args['meta_query'])) {
                $args['meta_query'] = array(
                    'relation' => 'AND',
                    $args['meta_query'],
                    array('key' => 'wem_mesa', 'value' => $mesa, 'compare' => '=')
                );
            } else {
                $args['meta_query'] = array(
                    array('key' => 'wem_mesa', 'value' => $mesa, 'compare' => '=')
                );
            }
        }
        
        // Filtro por evento
        if ($evento) {
            $args['tax_query'] = array(array(
                'taxonomy' => 'evento',
                'field' => 'slug',
                'terms' => $evento
            ));
        }
        
        return get_posts($args);
    }
    
    private function render_list_table($posts) {
        if (!$posts) {
            echo '<p>No se encontraron invitados.</p>';
            wp_die();
        }
        
        echo '<table class="wem-list-table"><thead><tr>
                <th>Ticket</th>
                <th class="wem-field-nombre">Nombre</th>
                <th class="wem-field-organizacion">Organización</th>
                <th class="wem-field-mesa">Mesa</th>
                <th>Evento</th>
                <th>Check-in</th>
              </tr></thead><tbody>';
        
        foreach($posts as $post) {
            $this->render_list_row($post);
        }
        
        echo '</tbody></table>';
        wp_die();
    }
    
    private function render_list_row($post) {
        $data = wem_get_invitado_data($post->ID);
        $terms = wem_get_evento_terms($post->ID);
        $evento_name = $terms ? $terms[0]->name : '';
        $evento_slug = $terms ? $terms[0]->slug : '';
        
        $checkin_url = $evento_slug ? 
            home_url("/{$evento_slug}/?ticket=" . $post->post_title) : '#';
        ?>
        
        <tr class="wem-clickable-row" data-href="<?php echo esc_url($checkin_url); ?>" style="cursor: pointer;">
            <td><strong><?php echo esc_html($post->post_title); ?></strong></td>
            <td class="wem-field-nombre"><?php echo esc_html($data['nombre']); ?></td>
            <td class="wem-field-organizacion"><?php echo esc_html($data['organizacion']); ?></td>
            <td class="wem-field-mesa"><?php echo esc_html($data['mesa']); ?></td>
            <td class="wem-field-evento"><?php echo esc_html($evento_name); ?></td>
            <td>
                <?php $this->render_checkin_status($post->ID, $data); ?>
            </td>
        </tr>
        <?php
    }
    
    private function render_checkin_status($post_id, $data) {
        if ($data['checkin']) {
            if ($data['checkout']) {
                echo '<strong style="color:blue">🚪 Salió</strong><br><small>' . esc_html($data['checkout_at']) . '</small>';
            } else {
                echo '<strong style="color:green">✅ Ingresó</strong><br><small>' . esc_html($data['checkin_at']) . '</small>';
                if ($data['checkin_by']) {
                    echo '<br><small style="color:#666">por: ' . esc_html($data['checkin_by']) . '</small>';
                }
            }
        } else {
            echo '<button class="wem-do-checkin wem-list-btn" data-id="' . esc_attr($post_id) . '">Ingresar</button>';
        }
    }
}