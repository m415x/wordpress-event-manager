<?php

class WEM_Import_Export {
    
    public function register_menu() {
        add_action('admin_menu', array($this, 'add_import_export_page'));
    }
    
    public function add_import_export_page() {
        add_submenu_page(
            'edit.php?post_type=invitado',
            'Importar / Exportar',
            'Importar / Exportar',
            'manage_options',
            'wem_import_export',
            array($this, 'render_import_export_page')
        );
    }
    
    public function render_import_export_page() {
        if (!wem_current_user_can_manage()) {
            wp_die('No autorizado');
        }
        
        echo '<div class="wrap"><h1>Importar / Exportar Invitados</h1>';
        
        $this->render_import_section();
        $this->handle_import();
        
        $this->render_export_section();
        $this->handle_export();
        
        echo '</div>';
    }
    
    private function render_import_section() {
        ?>
        <h2>Importar CSV</h2>
        <p>Formato CSV esperado (encabezado EXACTO): <code>titulo,nombre,organizacion,mesa,evento,observaciones,checkin</code></p>
        
        <form method="post" enctype="multipart/form-data">
            <?php wp_nonce_field('wem_import_action', 'wem_import_nonce'); ?>
            <input type="file" name="wem_csv" accept=".csv" required>
            
            <label style="display: block; margin: 10px 0;">
                <input type="checkbox" name="update_existing"> 
                Actualizar existentes (por título + evento)
            </label>
            
            <?php $this->render_separator_selector(); ?>
            
            <?php submit_button('Importar Invitados', 'primary', 'wem_do_import'); ?>
        </form>
        <?php
    }
    
    private function render_export_section() {
        ?>
        <hr>
        <h2>Exportar CSV</h2>
        <form method="post">
            <?php wp_nonce_field('wem_export_action', 'wem_export_nonce'); ?>
            <?php submit_button('Descargar CSV de Invitados', 'secondary', 'wem_do_export'); ?>
        </form>
        <?php
    }
    
    private function render_separator_selector() {
        $separador = isset($_POST['separador']) ? $_POST['separador'] : ',';
        ?>
        <label><strong>Separador CSV:</strong>
            <select name="separador">
                <option value="," <?php selected($separador, ','); ?>>Coma (,)</option>
                <option value=";" <?php selected($separador, ';'); ?>>Punto y coma (;)</option>
                <option value="|" <?php selected($separador, '|'); ?>>Pipe (|)</option>
                <option value="tab" <?php selected($separador, 'tab'); ?>>Tabulador</option>
            </select>
        </label>
        <br><br>
        <?php
    }
    
    private function handle_import() {
        if (!isset($_POST['wem_do_import'])) {
            return;
        }
        
        if (!$this->verify_import_nonce()) {
            return;
        }
        
        if (empty($_FILES['wem_csv']['tmp_name'])) {
            echo '<div class="notice notice-error"><p>No se recibió archivo.</p></div>';
            return;
        }
        
        $this->process_csv_import();
    }
    
    private function handle_export() {
        if (!isset($_POST['wem_do_export'])) {
            return;
        }
        
        if (!$this->verify_export_nonce()) {
            return;
        }
        
        $this->generate_csv_export();
    }
    
    private function verify_import_nonce() {
        if (!isset($_POST['wem_import_nonce']) || 
            !wp_verify_nonce($_POST['wem_import_nonce'], 'wem_import_action')) {
            echo '<div class="notice notice-error"><p>Nonce inválido</p></div>';
            return false;
        }
        return true;
    }
    
    private function verify_export_nonce() {
        if (!isset($_POST['wem_export_nonce']) || 
            !wp_verify_nonce($_POST['wem_export_nonce'], 'wem_export_action')) {
            echo '<div class="notice notice-error"><p>Nonce inválido</p></div>';
            return false;
        }
        return true;
    }
    
    private function process_csv_import() {
        $update_existing = isset($_POST['update_existing']);
        $separador = $this->get_csv_separator();
        $file = $_FILES['wem_csv']['tmp_name'];
        
        // Convertir archivo completo a UTF-8 (a prueba de Excel)
        $raw = file_get_contents($file);
        $raw = $this->wem_force_utf8($raw);
        file_put_contents($file, $raw);

        $handle = fopen($file, 'r');
        if (!$handle) {
            echo '<div class="notice notice-error"><p>Error abriendo archivo CSV</p></div>';
            return;
        }
        
        // Saltar BOM UTF-8 si existe
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($handle);
        }
        
        $header = fgetcsv($handle, 0, $separador);
        if (!$header) {
            echo '<div class="notice notice-error"><p>CSV inválido o vacío</p></div>';
            fclose($handle);
            return;
        }
        
        $results = $this->import_csv_data($handle, $header, $separador, $update_existing);
        fclose($handle);
        
        $this->show_import_results($results);
    }
    
    private function import_csv_data($handle, $header, $separador, $update_existing) {
        $count = 0;
        $skipped = 0;
        $updated = 0;
        
        while (($row = fgetcsv($handle, 0, $separador)) !== false) {
            if (count($row) !== count($header)) {
                $skipped++;
                continue;
            }
            
            $row = array_map(array($this, 'wem_force_utf8'), $row);
            $header = array_map(array($this, 'wem_force_utf8'), $header);

            $data = array_combine($header, $row);
            $result = $this->process_single_row($data, $update_existing);
            
            if ($result === 'created') $count++;
            if ($result === 'updated') $updated++;
            if ($result === 'skipped') $skipped++;
        }
        
        return compact('count', 'skipped', 'updated');
    }
    
    private function process_single_row($data, $update_existing) {
        $titulo = sanitize_text_field($data['titulo'] ?? '');
        if (!$titulo) {
            return 'skipped';
        }
        
        $result = $this->find_or_create_invitado($titulo, $data, $update_existing);
        if (!$result) {
            return 'skipped';
        }

        $post_id = $result['post_id'];
        $this->update_invitado_data($post_id, $data);
        $this->set_invitado_evento($post_id, $data['evento'] ?? '');

        return $result['operation'];
    }
    
    private function find_or_create_invitado($titulo, $data, $update_existing) {
        $evento = sanitize_text_field($data['evento'] ?? '');
        $existing_post = $this->find_existing_invitado($titulo, $evento);
        
        if ($existing_post) {
            if (!$update_existing) {
                return false;
            }

            return array(
                'post_id' => $existing_post->ID,
                'operation' => 'updated'
            );
        }

        $post_id = wp_insert_post(array(
            'post_type' => 'invitado',
            'post_title' => $titulo,
            'post_status' => 'publish'
        ));

        if (!$post_id) {
            return false;
        }

        return array(
            'post_id' => $post_id,
            'operation' => 'created'
        );
    }
    
    private function find_existing_invitado($titulo, $evento) {
        $evento_term = term_exists($evento, 'evento');
        if (!$evento_term) {
            return false;
        }
        
        $evento_term_id = is_array($evento_term) ? $evento_term['term_id'] : $evento_term;
        
        $existing_posts = get_posts(array(
            'post_type' => 'invitado',
            'title' => $titulo,
            'tax_query' => array(array(
                'taxonomy' => 'evento',
                'field' => 'term_id',
                'terms' => $evento_term_id
            )),
            'numberposts' => 1,
            'post_status' => 'any'
        ));
        
        return !empty($existing_posts) ? $existing_posts[0] : false;
    }
    
    private function update_invitado_data($post_id, $data) {
        $meta_fields = array(
            'wem_ticket' => $data['titulo'],
            'wem_nombre' => $data['nombre'] ?? '',
            'wem_organizacion' => $data['organizacion'] ?? '',
            'wem_mesa' => $data['mesa'] ?? '',
            'wem_observaciones' => $data['observaciones'] ?? '',
            'wem_checkin' => $this->parse_checkin_value($data['checkin'] ?? '')
        );
        
        foreach ($meta_fields as $key => $value) {
            update_post_meta($post_id, $key, $value);
        }
        
        // Si tiene checkin, agregar timestamp
        if ($meta_fields['wem_checkin']) {
            update_post_meta($post_id, 'wem_checkin_at', current_time('Y-m-d H:i:s'));
        }
    }
    
    private function set_invitado_evento($post_id, $evento) {
        if (!$evento) {
            return;
        }
        
        $term = term_exists($evento, 'evento');
        if (!$term) {
            $term = wp_insert_term($evento, 'evento');
        }
        
        if (!is_wp_error($term)) {
            $term_id = is_array($term) ? $term['term_id'] : $term;
            if (!empty($term_id)) {
                wp_set_object_terms($post_id, intval($term_id), 'evento', false);
            }
        }
    }
    
    private function parse_checkin_value($checkin_val) {
        return ($checkin_val === '1' || strtolower($checkin_val) === 'true') ? 1 : 0;
    }
    
    private function show_import_results($results) {
        echo '<div class="notice notice-success"><p>Import finalizado. ' .
             'Nuevos: ' . $results['count'] . ' — ' .
             'Actualizados: ' . $results['updated'] . ' — ' .
             'Saltados: ' . $results['skipped'] . '</p></div>';
    }
    
    private function generate_csv_export() {
        $filename = 'invitados_export_' . date('Ymd') . '.csv';
        
        // Limpiar buffer de salida
        if (ob_get_level()) {
            ob_end_clean();
        }
        
        // Headers para descarga
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);
        header('Pragma: no-cache');
        header('Expires: 0');
        
        $output = fopen('php://output', 'w');
        
        // BOM para UTF-8 en Excel
        fwrite($output, "\xEF\xBB\xBF");
        
        // Encabezados
        fputcsv($output, array(
            'titulo', 'nombre', 'organizacion', 'mesa', 'evento', 'observaciones', 'checkin'
        ));
        
        // Datos
        $invitados = $this->get_all_invitados();
        foreach ($invitados as $invitado) {
            fputcsv($output, $this->prepare_invitado_for_export($invitado));
        }
        
        fclose($output);
        exit;
    }
    
    private function get_all_invitados() {
        $query = new WP_Query(array(
            'post_type' => 'invitado',
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'orderby' => 'title',
            'order' => 'ASC'
        ));
        
        return $query->posts;
    }
    
    private function prepare_invitado_for_export($invitado) {
        $data = wem_get_invitado_data($invitado->ID);
        $terms = wem_get_evento_terms($invitado->ID);
        $evento = $terms ? $terms[0]->name : '';
        
        return array(
            $invitado->post_title,
            $data['nombre'],
            $data['organizacion'],
            $data['mesa'],
            $evento,
            $data['observaciones'],
            $data['checkin'] ? '1' : '0'
        );
    }
    
    private function get_csv_separator() {
        $separador = isset($_POST['separador']) ? $_POST['separador'] : ',';
        return ($separador === 'tab') ? "\t" : $separador;
    }

    private function wem_force_utf8($string) {
        // Si ya está en UTF-8 válido, no tocamos nada
        if (mb_detect_encoding($string, 'UTF-8', true)) {
            return $string;
        }

        // Intentar CP1252 (Windows / Excel)
        $converted = @iconv('CP1252', 'UTF-8//IGNORE', $string);
        if ($converted !== false) {
            return $converted;
        }

        // Intentar ISO-8859-1
        $converted = @iconv('ISO-8859-1', 'UTF-8//IGNORE', $string);
        if ($converted !== false) {
            return $converted;
        }

        // Fallback final
        return mb_convert_encoding($string, 'UTF-8', 'auto');
    }
}