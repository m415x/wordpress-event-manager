<?php

class WEM_Metabox_Manager {
    
    public function register_metaboxes() {
        add_action('add_meta_boxes', array($this, 'add_metaboxes'));
        add_action('save_post_invitado', array($this, 'save_metabox_data'));
    }
    
    public function add_metaboxes() {
        add_meta_box('c8_invitado_data', 'Datos del Invitado', array($this, 'render_metabox'), 'invitado', 'normal', 'high');
    }
    
    public function render_metabox($post) {
        wp_nonce_field('c8_save_invitado', 'c8_nonce');
        
        $data = c8ecm_get_invitado_data($post->ID);
        $eventos = $this->get_eventos_list();
        $evento_actual = $this->get_current_evento($post->ID);
        ?>
        
        <style><?php echo $this->get_metabox_styles(); ?></style>

        <div class="c8-metabox-field">
            <label for="c8_nombre"><strong>Nombre completo</strong></label>
            <input type="text" id="c8_nombre" name="c8_nombre" value="<?php echo esc_attr($data['nombre']); ?>">
        </div>

        <div class="c8-metabox-field">
            <label for="c8_organizacion"><strong>Organización</strong></label>
            <input type="text" id="c8_organizacion" name="c8_organizacion" value="<?php echo esc_attr($data['organizacion']); ?>">
        </div>

        <div class="c8-metabox-field">
            <label for="c8_mesa"><strong>Mesa asignada</strong></label>
            <input type="text" id="c8_mesa" name="c8_mesa" value="<?php echo esc_attr($data['mesa']); ?>">
        </div>

        <div class="c8-metabox-field">
            <label for="c8_evento"><strong>Evento</strong></label>
            <select id="c8_evento" name="c8_evento" style="width: 100%">
                <option value="">-- Seleccionar evento --</option>
                <?php foreach($eventos as $evento): ?>
                    <option value="<?php echo esc_attr($evento->term_id); ?>" <?php selected($evento_actual, $evento->term_id); ?>>
                        <?php echo esc_html($evento->name); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <p><small>Selecciona un evento existente. Si no existe, créalo primero en la pestaña "Eventos".</small></p>
        </div>

        <div class="c8-metabox-field">
            <label for="c8_observaciones"><strong>Observaciones (privadas)</strong></label>
            <textarea id="c8_observaciones" name="c8_observaciones" rows="3"><?php echo esc_textarea($data['observaciones']); ?></textarea>
        </div>

        <div class="c8-checkin-status">
            <?php $this->render_checkin_status($data); ?>
        </div>
        
        <?php
    }
    
    public function save_metabox_data($post_id) {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!isset($_POST['c8_nonce']) || !wp_verify_nonce($_POST['c8_nonce'], 'c8_save_invitado')) return;
        if (!c8ecm_current_user_can_manage()) return;

        $this->save_meta_fields($post_id);
        $this->save_evento_taxonomy($post_id);
    }
    
    private function save_meta_fields($post_id) {
        $fields = array(
            'c8_nombre' => 'sanitize_text_field',
            'c8_organizacion' => 'sanitize_text_field',
            'c8_mesa' => 'sanitize_text_field',
            'c8_observaciones' => 'sanitize_textarea_field'
        );
        
        foreach ($fields as $field => $sanitize) {
            if (isset($_POST[$field])) {
                $value = call_user_func($sanitize, $_POST[$field]);
                update_post_meta($post_id, $field, $value);
            }
        }
        
        // Guardar ticket
        $titulo = get_the_title($post_id);
        update_post_meta($post_id, 'c8_ticket', $titulo);
    }
    
    private function save_evento_taxonomy($post_id) {
        $evento = isset($_POST['c8_evento']) ? intval($_POST['c8_evento']) : '';
        
        if (!empty($evento)) {
            wp_set_object_terms($post_id, $evento, 'evento', false);
        } else {
            wp_set_object_terms($post_id, null, 'evento');
        }
    }
    
    private function get_eventos_list() {
        return get_terms(array(
            'taxonomy' => 'evento',
            'hide_empty' => false,
            'orderby' => 'name',
            'order' => 'ASC'
        ));
    }
    
    private function get_current_evento($post_id) {
        $evento_terms = c8ecm_get_evento_terms($post_id);
        return $evento_terms ? $evento_terms[0]->term_id : '';
    }
    
    private function get_metabox_styles() {
        return '
        .c8-metabox-field { margin-bottom: 15px; }
        .c8-metabox-field label { display: block; margin-bottom: 5px; font-weight: bold; }
        .c8-metabox-field input[type="text"],
        .c8-metabox-field textarea,
        .c8-metabox-field select { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; }
        .c8-checkin-status { padding: 10px; background: #f9f9f9; border-radius: 4px; }
        ';
    }
    
    private function render_checkin_status($data) {
        echo '<strong>Estado check-in:</strong>';
        
        if ($data['checkin']):
            echo '<span style="color:green">✅ Ingresado</span> — ' . esc_html($data['checkin_at']); 
            if ($data['checkin_by']) echo ' ('.esc_html($data['checkin_by']).')';
            
            if ($data['checkout']):
                echo '<br><strong>Estado check-out:</strong>';
                echo '<span style="color:blue">🚪 Salió</span> — ' . esc_html($data['checkout_at']);
                if ($data['checkout_by']) echo ' ('.esc_html($data['checkout_by']).')';
            endif;
        else:
            echo '<span style="color:orange">⏳ Pendiente</span>';
        endif;
    }
}