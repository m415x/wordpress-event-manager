<?php
declare(strict_types=1);

// Dedicated PHP worker: separate WordPress bootstrap and wpdb connection.
// Not a PHPUnit test file; invoked by a focused integration test.
require_once dirname(__DIR__, 2) . '/integration-bootstrap.php';
require_once dirname(__DIR__, 3) . '/wordpress-event-manager.php';
if (!function_exists('get_post_type')) {
    fwrite(STDERR, "WordPress unavailable\n");
    exit(2);
}
$guestId = isset($argv[1]) ? (int)$argv[1] : 0;
$adminId = isset($argv[2]) ? (int)$argv[2] : 0;
if ($guestId < 1 || $adminId < 1) {
    fwrite(STDERR, "Invalid worker identity\n");
    exit(2);
}
try {
    (new WEM_Invitation_Credential_Service())->revoke($guestId, $adminId);
    fwrite(STDOUT, "REVOKED\n");
} catch (Throwable $error) {
    fwrite(STDERR, get_class($error) . ': ' . $error->getMessage() . "\n");
    exit(1);
}
