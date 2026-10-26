<?php
declare(strict_types=1);

// Dedicated PHP worker: separate WordPress bootstrap and wpdb connection.
// Not a PHPUnit test file; invoked by a focused integration test.
$wpLoad = dirname(__DIR__, 6) . '/wp-load.php';
if (!is_file($wpLoad)) {
    fwrite(STDERR, 'WordPress runtime not found: ' . $wpLoad . "\n");
    exit(2);
}
// Do not launch the WordPress PHPUnit bootstrap in a second process: it
// reinstalls/resets test tables and destroys the first process's fixtures.
require_once $wpLoad;
require_once dirname(__DIR__, 3) . '/wordpress-event-manager.php';
if (!function_exists('get_post_type')) {
    fwrite(STDERR, "WordPress unavailable\n");
    exit(2);
}
if (!post_type_exists('invitado')) {
    (new WEM_CPT_Manager())->register_cpt();
}
$guestId = isset($argv[1]) ? (int)$argv[1] : 0;
$adminId = isset($argv[2]) ? (int)$argv[2] : 0;
if ($guestId < 1 || $adminId < 1) {
    fwrite(STDERR, "Invalid worker identity\n");
    exit(2);
}
global $wpdb;
$guestRow = $wpdb->get_row($wpdb->prepare("SELECT ID, post_type, post_status FROM {$wpdb->posts} WHERE ID=%d", $guestId), ARRAY_A);
if (!is_array($guestRow) || get_post_type($guestId) !== 'invitado') {
    $database = (string)$wpdb->get_var('SELECT DATABASE()');
    fwrite(STDERR, 'Worker fixture visibility: database=' . $database
        . ', prefix=' . $wpdb->prefix
        . ', guest_id=' . $guestId
        . ', post_type_registered=' . (post_type_exists('invitado') ? 'yes' : 'no')
        . ', raw_guest=' . json_encode($guestRow)
        . ', get_post_type=' . var_export(get_post_type($guestId), true) . "\n");
    exit(3);
}
$readyPath = $argv[3] ?? '';
if (is_string($readyPath) && $readyPath !== '') {
    if (!is_file($readyPath) || file_put_contents($readyPath, 'READY') === false) {
        fwrite(STDERR, "Worker readiness handshake failed\n");
        exit(4);
    }
}
try {
    (new WEM_Invitation_Credential_Service())->revoke($guestId, $adminId);
    fwrite(STDOUT, "REVOKED\n");
} catch (Throwable $error) {
    fwrite(STDERR, get_class($error) . ': ' . $error->getMessage() . "\n");
    exit(1);
}
