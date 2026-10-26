<?php
declare(strict_types=1);
// Independent WordPress process; never invoke the PHPUnit bootstrap here.
$wpLoad = dirname(__DIR__, 6) . '/wp-load.php';
if (!is_file($wpLoad)) { fwrite(STDERR, "WordPress runtime unavailable\n"); exit(2); }
require_once $wpLoad;
require_once dirname(__DIR__, 3) . '/wordpress-event-manager.php';
if (!post_type_exists('invitado')) { (new WEM_CPT_Manager())->register_cpt(); }
$token = $argv[1] ?? '';
$tableId = isset($argv[2]) ? (int)$argv[2] : 0;
$readyPath = $argv[3] ?? '';
if (!is_string($token) || $token === '' || $tableId < 1 || !is_string($readyPath) || !is_file($readyPath)) {
    fwrite(STDERR, "Invalid worker arguments\n"); exit(2);
}
global $wpdb;
if (file_put_contents($readyPath, 'READY:' . (string)$wpdb->get_var('SELECT CONNECTION_ID()')) === false) {
    fwrite(STDERR, "Readiness failed\n"); exit(3);
}
try {
    $service = new class($readyPath) extends WEM_Seating_Bearer_Initial_Reservation {
        private $readyPath;
        public function __construct($readyPath) { $this->readyPath = $readyPath; }
        protected function resolve_identity($bearer) {
            $identity = parent::resolve_identity($bearer);
            if ($identity === null) {
                $db = $GLOBALS['wpdb'];
                $credentials = WEM_Invitation_Credential_Schema::table_name();
                $row = $db->get_row($db->prepare(
                    "SELECT guest_id,status,active_slot FROM {$credentials} WHERE token_digest=%s",
                    hash('sha256', $bearer)
                ), ARRAY_A);
                $guest = is_array($row) ? (int)$row['guest_id'] : 0;
                $raw = $guest ? $db->get_row($db->prepare(
                    "SELECT ID,post_type,post_status FROM {$db->posts} WHERE ID=%d", $guest
                ), ARRAY_A) : null;
                $terms = $guest ? $db->get_results($db->prepare(
                    "SELECT tt.term_id,tt.taxonomy FROM {$db->term_relationships} tr
                     JOIN {$db->term_taxonomy} tt ON tt.term_taxonomy_id=tr.term_taxonomy_id
                     WHERE tr.object_id=%d", $guest
                ), ARRAY_A) : null;
                fwrite(STDERR, 'PREFLIGHT_DIAG: credential=' . json_encode($row)
                    . ', guest=' . json_encode($raw) . ', terms=' . json_encode($terms)
                    . ', wp_type=' . var_export($guest ? get_post_type($guest) : null, true)
                    . ', sql_error=' . $db->last_error . "\n");
            }
            $stage = $identity === null ? 'PREFLIGHT_DENIED:' : 'PREFLIGHT_OK:';
            file_put_contents($this->readyPath, $stage . (string)$GLOBALS['wpdb']->get_var('SELECT CONNECTION_ID()'));
            return $identity;
        }
    };
    $id = $service->reserve_for_bearer($token, $tableId);
    fwrite(STDOUT, 'RESERVED:' . $id . "\n");
} catch (Throwable $error) {
    fwrite(STDERR, get_class($error) . ': ' . $error->getMessage() . "\n");
    exit(1);
}
