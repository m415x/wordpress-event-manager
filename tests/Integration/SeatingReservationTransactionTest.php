<?php
/**
 * WEM-81/T1 isolated, persistent InnoDB transaction contract.
 *
 * Run via WP-CLI 'wp eval-file' in the disposable WEM81 environment.
 * Do NOT run via WP_UnitTestCase or a shared PHPUnit database.
 */
declare(strict_types=1);

if (!defined('ABSPATH')) {
    throw new RuntimeException('Requires an isolated loaded WordPress instance.');
}
global $wpdb;
$tables = $wpdb->prefix . 'wem_event_tables';
$reservations = $wpdb->prefix . 'wem_table_reservations';
$audit = $wpdb->prefix . 'wem_seating_admin_audit';
foreach ([$tables, $reservations, $audit] as $name) {
    $row = $wpdb->get_row($wpdb->prepare(
        'SELECT ENGINE, TABLE_TYPE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s',
        $name
    ), ARRAY_A);
    if (!is_array($row) || strtoupper((string) $row['ENGINE']) !== 'INNODB' || $row['TABLE_TYPE'] !== 'BASE TABLE') {
        throw new RuntimeException('ERROR environment: persistent InnoDB missing for ' . $name);
    }
}
if ((int) $wpdb->get_var("SELECT COUNT(*) FROM {$reservations}") !== 0
    || (int) $wpdb->get_var("SELECT COUNT(*) FROM {$audit}") !== 0) {
    throw new RuntimeException('ERROR environment: expected pristine disposable reservation/audit tables');
}

$eventId = 915081;
$guestId = 995081;
$code = 'WEM81-T1-' . wp_generate_uuid4();
$insertedTable = $wpdb->insert($tables, [
    'event_term_id' => $eventId, 'table_code' => $code, 'capacity' => 2,
], ['%d', '%s', '%d']);
if ($insertedTable !== 1) {
    throw new RuntimeException('ERROR fixture: could not insert table ' . $wpdb->last_error);
}
$tableId = (int) $wpdb->insert_id;
$baseline = [
    'active' => (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$reservations} WHERE table_id = %d AND status = 'active' AND active_slot = 1", $tableId
    )),
    'audit' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$audit}"),
];
$insertObserved = false;
$commitObserved = false;
$exceptionObserved = false;

try {
    if (!class_exists('WEM_Seating_Transaction')) {
        throw new RuntimeException('NOT RED: WEM_Seating_Transaction absent; no reservation insert executed');
    }
    // Contract: a successful database INSERT must occur before this deterministic
    // post-insert exception. Transaction layer must rollback and rethrow.
    WEM_Seating_Transaction::run(static function () use (
        $wpdb, $reservations, $guestId, $tableId, $eventId, &$insertObserved
    ): void {
        $wrote = $wpdb->insert($reservations, [
            'guest_id' => $guestId,
            'table_id' => $tableId,
            'event_term_id' => $eventId,
            'status' => 'active',
            'active_slot' => 1,
            'created_at' => gmdate('Y-m-d H:i:s'),
        ], ['%d', '%d', '%d', '%s', '%d', '%s']);
        if ($wrote !== 1) {
            throw new RuntimeException('ERROR fixture INSERT: ' . $wpdb->last_error);
        }
        $insertObserved = true;
        throw new DomainException('WEM81_INJECTED_POST_INSERT_FAILURE');
    });
    $commitObserved = true;
} catch (DomainException $e) {
    if ($e->getMessage() !== 'WEM81_INJECTED_POST_INSERT_FAILURE') {
        throw $e;
    }
    $exceptionObserved = true;
} finally {
    // Avoid leaving fixture rows; never blind-delete shared state.
    $wpdb->delete($tables, ['id' => $tableId], ['%d']);
}
if (!$insertObserved) {
    throw new RuntimeException('NOT RED: no successful INSERT observed; transaction behavior was not exercised');
}
$actualActive = (int) $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM {$reservations} WHERE guest_id = %d AND status = 'active' AND active_slot = 1", $guestId
));
$actualAudit = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$audit}");
if (!$exceptionObserved || $commitObserved || $actualActive !== $baseline['active'] || $actualAudit !== $baseline['audit']) {
    throw new RuntimeException(sprintf(
        'BEHAVIORAL RED: inserted=%d injected_exception=%d return_without_error=%d active_after=%d active_before=%d audit_after=%d audit_before=%d',
        (int) $insertObserved, (int) $exceptionObserved, (int) $commitObserved,
        $actualActive, $baseline['active'], $actualAudit, $baseline['audit']
    ));
}
echo "WEM-81/T1 transaction rollback: GREEN (successful INSERT, injected error, no active reservation, no partial audit).\n";
