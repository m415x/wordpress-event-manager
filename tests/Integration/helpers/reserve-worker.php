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
    $id = (new WEM_Seating_Bearer_Initial_Reservation())->reserve_for_bearer($token, $tableId);
    fwrite(STDOUT, 'RESERVED:' . $id . "\n");
} catch (Throwable $error) {
    fwrite(STDERR, get_class($error) . ': ' . $error->getMessage() . "\n");
    exit(1);
}
