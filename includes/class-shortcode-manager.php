<?php

class WEM_Shortcode_Manager {
    
    public function register_shortcodes() {
        add_shortcode('c8ecm_checkin', array($this, 'render_checkin_shortcode'));
        add_shortcode('c8ecm_list', array($this, 'render_list_shortcode'));
    }
    
    public function render_checkin_shortcode($atts) {
        $atts = shortcode_atts(array('event' => ''), $atts);
        $ticket = $this->get_ticket_from_query();
        
        if (!$ticket) {
            return '<p>No se especificó ticket.</p>';
        }
        
        $invitado = $this->find_invitado($ticket, $atts['event']);
        if (!$invitado) {
            return '<p>Invitado no encontrado para el ticket: ' . esc_html($ticket) . '</p>';
        }
        
        return $this->render_checkin_interface($invitado, $atts['event']);
    }
    
    public function render_list_shortcode($atts) {
        $atts = shortcode_atts(array('event' => ''), $atts);
        ob_start();
        ?>
        <div class="c8-list-wrap">
            <?php $this->render_list_styles(); ?>
            
            <div class="c8-list-controls">
                <input class="c8-filter-input" id="c8_q" placeholder="Buscar por ticket, nombre, organización">
                <input class="c8-filter-input" id="c8_mesa_filter" placeholder="Buscar por Mesa">
                <button class="c8-list-btn" id="c8_refresh">Buscar</button>
            </div>
            <div id="c8_list_results">Cargando...</div>
        </div>
        
        <script>
        <?php $this->render_list_script($atts['event']); ?>
        </script>
        <?php
        return ob_get_clean();
    }
    
    private function get_ticket_from_query() {
        return isset($_GET['ticket']) ? sanitize_text_field($_GET['ticket']) : '';
    }
    
    private function find_invitado($ticket, $event_slug) {
        $args = array(
            'post_type' => 'invitado',
            'title' => $ticket,
            'numberposts' => 1
        );
        
        if ($event_slug) {
            $args['tax_query'] = array(array(
                'taxonomy' => 'evento',
                'field' => 'slug',
                'terms' => $event_slug
            ));
        } else {
            global $post;
            $event_slug = $post ? $post->post_name : '';
            if ($event_slug) {
                $args['tax_query'] = array(array(
                    'taxonomy' => 'evento',
                    'field' => 'slug',
                    'terms' => $event_slug
                ));
            }
        }
        
        $found = get_posts($args);
        return $found ? $found[0] : false;
    }
    
    private function render_checkin_interface($invitado, $event_slug) {
        $data = c8ecm_get_invitado_data($invitado->ID);
        $nombre = $data['nombre'] ?: $invitado->post_title;
        
        ob_start();
        ?>
        <div class="c8c_wrapper">
            <?php $this->render_checkin_styles(); ?>
            
            <h2 class="c8-field-nombre"><?php echo esc_html($nombre); ?></h2>
            <p class="c8-field-organizacion c8c_field"><strong>Organización:</strong> <?php echo esc_html($data['organizacion']); ?></p>
            <p class="c8-field-mesa c8c_field"><strong>Mesa:</strong> <?php echo esc_html($data['mesa']); ?></p>
            <p class="c8-field-evento c8c_field"><strong>Evento:</strong> <?php echo esc_html($event_slug); ?></p>
            
            <?php if ($data['checkin']): ?>
                <?php $this->render_checked_in_interface($invitado->ID, $data); ?>
            <?php else: ?>
                <?php $this->render_pending_interface($invitado->ID); ?>
            <?php endif; ?>
            
            <p id="c8c_msg" style="margin-top:10px;display:none;"></p>
        </div>
        
        <script>
        <?php $this->render_checkin_script($invitado->ID, $nombre); ?>
        </script>
        <?php
        return ob_get_clean();
    }
    
    private function render_checked_in_interface($post_id, $data) {
        if ($data['checkout']):
            ?>
            <p style="color:blue;font-weight:700;">🚪 Salió: <?php echo esc_html($data['checkout_at']); ?></p>
            <?php if($data['checkout_by']): ?>
                <p style="color:#666;font-size:0.9em;">Registrado por: <?php echo esc_html($data['checkout_by']); ?></p>
            <?php endif; ?>
            <button class="c8c_btn checkin_again" id="c8c_btn_checkin_again" data-postid="<?php echo esc_attr($post_id); ?>">
                🔄 Volver a ingresar
            </button>
            <?php
        else:
            ?>
            <p style="color:green;font-weight:700;">✅ Ingresó: <?php echo esc_html($data['checkin_at']); ?></p>
            <?php if($data['checkin_by']): ?>
                <p style="color:#666;font-size:0.9em;">Registrado por: <?php echo esc_html($data['checkin_by']); ?></p>
            <?php endif; ?>
            <button class="c8c_btn checkout" id="c8c_btn_checkout" data-postid="<?php echo esc_attr($post_id); ?>">
                🚪 Registrar salida
            </button>
            <?php
        endif;
        
        if ($data['observaciones']):
            ?>
            <p class="c8-field-observaciones"><strong>Observaciones:</strong><br><?php echo nl2br(esc_html($data['observaciones'])); ?></p>
            <?php
        endif;
    }
    
    private function render_pending_interface($post_id) {
        ?>
        <div class="c8c_obs c8-field-observaciones">
            <label for="c8_obs"><?php _e('Observaciones'); ?></label>
            <textarea id="c8_obs" placeholder="Ej: alergia, silla extra..."></textarea>
        </div>
        <button class="c8c_btn" id="c8c_btn" data-postid="<?php echo esc_attr($post_id); ?>">
            ✅ Marcar ingreso
        </button>
        <?php
    }
    
    private function render_checkin_styles() {
        ?>
        <style>
        .c8-list-wrap{max-width:1000px;margin:0 auto;font-family:system-ui,Arial,sans-serif;}
        .c8-list-controls{display:flex;gap:8px;margin-bottom:10px;flex-wrap:wrap;}
        .c8-list-table{width:100%;border-collapse:collapse;}
        .c8-list-table th, .c8-list-table td{padding:8px;border-bottom:1px solid #eee;text-align:left;}
        .c8-list-btn{padding:6px 10px;border-radius:6px;border:0;background:#2d89ef;color:#fff;cursor:pointer;}
        .c8-list-btn.green{background:#4caf50;}
        .c8-filter-input{padding:6px;border:1px solid #ddd;border-radius:6px;}
        
        .c8-clickable-row:hover { background-color: #f5f5f5; }
        .c8-clickable-row:active { background-color: #e9e9e9; }
        .c8-clickable-row td:first-child { position: relative; }
        .c8-clickable-row td:first-child::after {
            content: "🔗"; position: absolute; right: 5px; top: 50%; transform: translateY(-50%);
            opacity: 0.5; font-size: 12px;
        }
        
        @media (max-width: 768px) {
            .c8-list-table th:nth-child(5), .c8-list-table td:nth-child(5) { display: none; }
            .c8-list-controls { flex-direction: column; }
            .c8-filter-input, .c8-list-btn { width: 100%; margin-bottom: 5px; }
        }
        </style>
        <?php
    }
    
    private function render_list_styles() {
        // Los estilos se comparten con checkin_styles
    }
    
    private function render_checkin_script($post_id, $nombre) {
        $nonce = wp_create_nonce('c8ecm_checkin_nonce');
        ?>
        (function(){
            const msg = document.getElementById('c8c_msg');
            const postId = <?php echo $post_id; ?>;
            const nombre = '<?php echo esc_js($nombre); ?>';
            const nonce = '<?php echo $nonce; ?>';
            const ajaxUrl = '<?php echo admin_url('admin-ajax.php'); ?>';
            
            function handleAction(btn, action, confirmText) {
                if(!confirm(confirmText)) return;
                btn.disabled = true;
                btn.classList.add('disabled');
                btn.textContent = 'Procesando...';
                
                const observ = document.getElementById('c8_obs') ? document.getElementById('c8_obs').value : '';
                const data = new URLSearchParams();
                data.append('action','c8ecm_checkin_ajax');
                data.append('post_id', postId);
                data.append('observ', observ);
                data.append('check_action', action);
                data.append('nonce', nonce);

                fetch(ajaxUrl, {
                    method:'POST',
                    credentials:'same-origin',
                    headers:{'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'},
                    body: data.toString()
                }).then(r=>r.json()).then(res=>{
                    if(res.success){
                        location.reload();
                    } else {
                        msg.style.display='block';
                        msg.style.color='red';
                        msg.innerText = 'Error: ' + (res.data ? res.data : 'Error');
                        btn.disabled = false;
                        btn.classList.remove('disabled');
                        btn.textContent = getButtonText(action);
                    }
                }).catch(e=>{
                    msg.style.display='block';
                    msg.style.color='red';
                    msg.innerText = 'Error de red';
                    btn.disabled = false;
                    btn.classList.remove('disabled');
                    btn.textContent = getButtonText(action);
                });
            }

            function getButtonText(action) {
                switch(action) {
                    case 'checkin': return '✅ Marcar ingreso';
                    case 'checkout': return '🚪 Registrar salida';
                    case 'checkin_again': return '🔄 Volver a ingresar';
                    default: return 'Acción';
                }
            }

            // Asignar eventos
            const btnCheckin = document.getElementById('c8c_btn');
            const btnCheckout = document.getElementById('c8c_btn_checkout');
            const btnCheckinAgain = document.getElementById('c8c_btn_checkin_again');

            if(btnCheckin) btnCheckin.addEventListener('click', () => 
                handleAction(btnCheckin, 'checkin', 'Confirmar check-in para ' + nombre + '?'));
            if(btnCheckout) btnCheckout.addEventListener('click', () => 
                handleAction(btnCheckout, 'checkout', 'Confirmar check-out para ' + nombre + '?'));
            if(btnCheckinAgain) btnCheckinAgain.addEventListener('click', () => 
                handleAction(btnCheckinAgain, 'checkin_again', 'Confirmar re-ingreso para ' + nombre + '?'));
        })();
        <?php
    }
    
    private function render_list_script($event_slug) {
        $nonce = wp_create_nonce('c8ecm_checkin_nonce');
        ?>
        (function(){
            const ajaxUrl = '<?php echo admin_url('admin-ajax.php'); ?>';
            const results = document.getElementById('c8_list_results');
            const eventSlug = '<?php echo esc_js($event_slug); ?>';
            const nonce = '<?php echo $nonce; ?>';

            function loadList(){
                const q = document.getElementById('c8_q').value;
                const mesa = document.getElementById('c8_mesa_filter').value;
                results.innerHTML = 'Buscando...';
                
                const data = new URLSearchParams();
                data.append('action','c8ecm_list_ajax');
                data.append('q', q);
                data.append('mesa', mesa);
                data.append('evento', eventSlug);
                
                fetch(ajaxUrl, {
                    method:'POST', 
                    credentials:'same-origin', 
                    headers:{'Content-Type':'application/x-www-form-urlencoded'}, 
                    body:data.toString()
                }).then(r=>r.text()).then(html=>{ 
                    results.innerHTML = html; 
                    attachHandlers(); 
                    attachRowHandlers();
                }).catch(()=> results.innerHTML = 'Error de red');
            }

            function attachHandlers(){
                document.querySelectorAll('.c8-do-checkin').forEach(btn=>{
                    btn.addEventListener('click', function(e){
                        e.stopPropagation();
                        const id = this.dataset.id;
                        if(!confirm('Confirmar check-in?')) return;
                        
                        this.disabled = true;
                        this.textContent = 'Procesando...';
                        
                        const data = new URLSearchParams();
                        data.append('action','c8ecm_checkin_ajax');
                        data.append('post_id', id);
                        data.append('observ', '');
                        data.append('check_action', 'checkin');
                        data.append('nonce', nonce);
                        
                        fetch(ajaxUrl, {
                            method:'POST', 
                            credentials:'same-origin', 
                            headers:{'Content-Type':'application/x-www-form-urlencoded'}, 
                            body:data.toString()
                        }).then(r=>r.json()).then(res=>{
                            if(res.success){
                                btn.classList.add('green');
                                btn.textContent = 'Ingresó';
                            } else {
                                alert('Error: ' + (res.data || ''));
                                btn.disabled = false;
                                btn.textContent = 'Ingresar';
                            }
                        }).catch(()=>{ 
                            alert('Error de red'); 
                            btn.disabled=false; 
                            btn.textContent='Ingresar'; 
                        });
                    });
                });
            }
            
            function attachRowHandlers() {
                document.querySelectorAll('.c8-clickable-row').forEach(row => {
                    row.addEventListener('click', function(e) {
                        if (e.target.tagName === 'BUTTON' || e.target.closest('button')) return;
                        const href = this.dataset.href;
                        if (href && href !== '#') {
                            window.location.href = href;
                        }
                    });
                });
            }

            // Event listeners
            document.getElementById('c8_refresh').addEventListener('click', loadList);
            document.getElementById('c8_q').addEventListener('keyup', function(e){ 
                if(e.key === 'Enter') loadList(); 
            });

            loadList();
        })();
        <?php
    }
}