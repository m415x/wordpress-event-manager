<?php
declare(strict_types=1);

namespace WEM\Tests\Integration;

use PHPUnit\Framework\TestCase;

/** WEM-84/T4 — admin cancellation must reject unauthenticated actors first. */
final class SeatingAdministrativeCancellationAuthorizationTest extends TestCase
{
    public function testAnonymousActorCannotCancelOrCreateAudit(): void
    {
        global $wpdb;
        require_once dirname(__DIR__, 2) . '/wordpress-event-manager.php';

        $originalUser = get_current_user_id();
        $audit = $wpdb->prefix . 'wem_seating_admin_audit';
        $reservations = $wpdb->prefix . 'wem_table_reservations';
        $beforeAudit = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$audit}");
        $beforeActive = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$reservations} WHERE status='active' AND active_slot=1");

        try {
            wp_set_current_user(0);
            try {
                (new \WEM_Seating_Administrative_Cancellation_Service())->cancel(1, 'Unauthorized request');
                self::fail('Anonymous actor must not cancel reservations.');
            } catch (\DomainException $denied) {
                self::assertSame('Administrative seating permission denied.', $denied->getMessage());
            }
            self::assertSame($beforeAudit, (int) $wpdb->get_var("SELECT COUNT(*) FROM {$audit}"));
            self::assertSame($beforeActive, (int) $wpdb->get_var("SELECT COUNT(*) FROM {$reservations} WHERE status='active' AND active_slot=1"));
        } finally {
            wp_set_current_user($originalUser);
        }
    }
}
