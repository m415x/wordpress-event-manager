<?php

class WEM_QR_Generator {
    
    public function register_shortcodes() {
        add_shortcode('wem_qr_table', array($this, 'generate_qr_table'));
        add_shortcode('wem_qr_single', array($this, 'generate_single_qr'));
    }
    
    public function generate_qr_table($atts) {
        // WEM-11: deny ticket/QR disclosure pending WEM-13.
        return '<p>Guest access is temporarily unavailable.</p>';

        $atts = shortcode_atts(array(
            'event' => '',
            'start' => 1,
            'end' => 100,
            'columns' => 4,
            'size' => 150
        ), $atts);
        
        $event_slug = $this->get_event_slug($atts['event']);
        $start = intval($atts['start']);
        $end = intval($atts['end']);
        $columns = intval($atts['columns']);
        $size = intval($atts['size']);
        
        ob_start();
        ?>
        <div class="wem-qr-table-wrapper">
            <style>
            .wem-qr-table { width: 100%; border-collapse: collapse; margin: 20px 0; }
            .wem-qr-cell { border: 1px solid #ddd; padding: 15px; text-align: center; vertical-align: top; }
            .wem-qr-code { margin: 0 auto; display: block; }
            .wem-ticket-number { margin-top: 10px; font-weight: bold; font-size: 14px; }
            .wem-event-name { font-size: 12px; color: #666; margin-top: 5px; }
            </style>
            
            <table class="wem-qr-table">
                <?php
                $count = 0;
                for ($i = $start; $i <= $end; $i++):
                    if ($count % $columns == 0) echo '<tr>';
                ?>
                    <td class="wem-qr-cell">
                        <?php echo $this->generate_qr_code($event_slug, $i, $size); ?>
                        <div class="wem-ticket-number">Ticket <?php echo esc_html($i); ?></div>
                        <?php if ($event_slug): ?>
                            <div class="wem-event-name"><?php echo esc_html($event_slug); ?></div>
                        <?php endif; ?>
                    </td>
                <?php
                    $count++;
                    if ($count % $columns == 0) echo '</tr>';
                endfor;
                
                // Completar última fila si es necesario
                if ($count % $columns != 0) {
                    $remaining = $columns - ($count % $columns);
                    for ($i = 0; $i < $remaining; $i++) {
                        echo '<td class="wem-qr-cell">&nbsp;</td>';
                    }
                    echo '</tr>';
                }
                ?>
            </table>
        </div>
        <?php
        return ob_get_clean();
    }
    
    public function generate_single_qr($atts) {
        // WEM-11: do not generate invitation URLs before authorization review.
        return '<p>Guest access is temporarily unavailable.</p>';

        $atts = shortcode_atts(array(
            'event' => '',
            'ticket' => '1',
            'size' => 200
        ), $atts);
        
        $event_slug = $this->get_event_slug($atts['event']);
        $ticket = sanitize_text_field($atts['ticket']);
        $size = intval($atts['size']);
        
        return $this->generate_qr_code($event_slug, $ticket, $size);
    }
    
    private function generate_qr_code($event_slug, $ticket, $size = 150) {
        // Generar URL del ticket
        $url = $this->generate_ticket_url($event_slug, $ticket);
        
        // Usar Google Charts API como solución simple y gratuita
        $qr_url = $this->generate_google_qr_url($url, $size);
        
        return sprintf(
            '<img src="%s" alt="QR Code for Ticket %s" class="wem-qr-code" width="%d" height="%d">',
            esc_url($qr_url),
            esc_attr($ticket),
            $size,
            $size
        );
    }
    
    private function generate_google_qr_url($url, $size) {
        $params = array(
            'cht' => 'qr',
            'chs' => $size . 'x' . $size,
            'chl' => urlencode($url),
            'choe' => 'UTF-8'
        );
        
        return 'https://chart.googleapis.com/chart?' . http_build_query($params);
    }
    
    private function generate_ticket_url($event_slug, $ticket) {
        if ($event_slug) {
            return home_url("/{$event_slug}/?ticket=" . $ticket);
        } else {
            // Si no hay evento, usar la página actual
            global $post;
            $base_url = $post ? get_permalink($post->ID) : home_url();
            return add_query_arg('ticket', $ticket, $base_url);
        }
    }
    
    private function get_event_slug($event_param) {
        if (!empty($event_param)) {
            return sanitize_title($event_param);
        }
        
        global $post;
        if ($post) {
            return $post->post_name;
        }
        
        return '';
    }
}