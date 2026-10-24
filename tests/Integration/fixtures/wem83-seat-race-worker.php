<?php
declare(strict_types=1);
// Executed only by the WEM-83 integration test, as an independent PHP process.
[$script, $gate, $guest, $table] = $argv;
require_once '/var/www/html/wp-load.php';
require_once dirname(__DIR__, 3) . '/wordpress-event-manager.php';
$deadline = microtime(true) + 12;
while (!is_file($gate) && microtime(true) < $deadline) {
    usleep(10000);
}
if (!is_file($gate)) { fwrite(STDERR, "GATE_TIMEOUT\n"); exit(2); }
global $wpdb;
$connection = (int) $wpdb->get_var('SELECT CONNECTION_ID()');
try {
    (new WEM_Seating_Reservation_Service())->reserve_initial((int) $guest, (int) $table);
    echo "WIN:$connection\n";
} catch (DomainException $e) {
    echo "DENY:$connection\n";
} catch (Throwable $e) {
    fwrite(STDERR, get_class($e) . ':' . $e->getMessage() . "\n");
    exit(3);
}
