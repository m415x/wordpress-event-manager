<?php

class WEM_Metabox_Manager {
    
    public function register_metaboxes() {
        add_action('add_meta_boxes', array($this, 'add_metaboxes'));
        add_action('save_post_invitado', array($this, 'save_metabox_data'));
    }
    
    public function add_metaboxes() {
        add_meta_box('wem_invitado_data', 'Datos del Invitado', array($this, 'render_metabox'), 'invitado', 'normal', 'high');
    }
    
    public function render_metabox($post) {
        wp_nonce_field('wem_save_invitado', 'wem_nonce');
        
        $data = wem_get_invitado_data($post->ID);
        $eventos = $this->get_eventos_list();
        $evento_actual = $this->get_current_evento($post->ID);
        ?>
        
        <style><?php echo $this->get_metabox_styles(); ?></style>

        <div class="wem-metabox-field">
            <label for="wem_nombre"><strong>Nombre completo</strong></label>
            <input type="text" id="wem_nombre" name="wem_nombre" value="<?php echo esc_attr($data['nombre']); ?>">
        </div>

        <div class="wem-metabox-field">
            <label for="wem_organizacion"><strong>Organización</strong></label>
            <input type="text" id="wem_organizacion" name="wem_organizacion" value="<?php echo esc_attr($data['organizacion']); ?>">
        </div>

        <div class="wem-metabox-field">
            <label for="wem_mesa"><strong>Mesa asignada</strong></label>
            <input type="text" id="wem_mesa" name="wem_mesa" value="<?php echo esc_attr($data['mesa']); ?>">
        </div>

        <div class="wem-metabox-field">
            <label for="wem_evento"><strong>Evento</strong></label>
            <select id="wem_evento" name="wem_evento" style="width: 100%">
                <option value="">-- Seleccionar evento --</option>
                <?php foreach($eventos as $evento): ?>
                    <option value="<?php echo esc_attr($evento->term_id); ?>" <?php selected($evento_actual, $evento->term_id); ?>>
                        <?php echo esc_html($evento->name); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <p><small>Selecciona un evento existente. Si no existe, créalo primero en la pestaña "Eventos".</small></p>
        </div>

        <div class="wem-metabox-field">
            <label for="wem_observaciones"><strong>Observaciones (privadas)</strong></label>
            <textarea id="wem_observaciones" name="wem_observaciones" rows="3"><?php echo esc_textarea($data['observaciones']); ?></textarea>
        </div>

        <div class="wem-checkin-status">
            <?php $this->render_checkin_status($data); ?>
        </div>
        
        <?php
    }
    
    public function save_metabox_data($post_id) {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!isset($_POST['wem_nonce']) || !wp_verify_nonce($_POST['wem_nonce'], 'wem_save_invitado')) return;
        if (!wem_current_user_can_manage()) return;

        $this->save_meta_fields($post_id);
        $this->save_evento_taxonomy($post_id);
    }
    
    private function save_meta_fields($post_id) {
        $fields = array(
            'wem_nombre' => 'sanitize_text_field',
            'wem_organizacion' => 'sanitize_text_field',
            'wem_mesa' => 'sanitize_text_field',
            'wem_observaciones' => 'sanitize_textarea_field'
        );
        
        foreach ($fields as $field => $sanitize) {
            if (isset($_POST[$field])) {
                $value = call_user_func($sanitize, $_POST[$field]);
                update_post_meta($post_id, $field, $value);
            }
        }
        
        // Guardar ticket
        $titulo = get_the_title($post_id);
        update_post_meta($post_id, 'wem_ticket', $titulo);
    }
    
    private function save_evento_taxonomy($post_id) {
        $evento = isset($_POST['wem_evento']) ? intval($_POST['wem_evento']) : '';
        
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
        $evento_terms = wem_get_evento_terms($post_id);
        return $evento_terms ? $evento_terms[0]->term_id : '';
    }
    
    private function get_metabox_styles() {
        return '
        .wem-metabox-field { margin-bottom: 15px; }
        .wem-metabox-field label { display: block; margin-bottom: 5px; font-weight: bold; }
        .wem-metabox-field input[type="text"],
        .wem-metabox-field textarea,
        .wem-metabox-field select { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; }
        .wem-checkin-status { padding: 10px; background: #f9f9f9; border-radius: 4px; }
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