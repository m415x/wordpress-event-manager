<?php
declare(strict_types=1);

namespace WEM\Tests\Integration;

use PHPUnit\Framework\TestCase;

/** WEM-85: unauthorized reassignment fails before any ledger mutation. */
final class SeatingAdministrativeReassignmentAuthorizationTest extends TestCase
{
    public function testAnonymousActorCannotReassignOrAppendAudit(): void
    {
        global $wpdb;
        require_once dirname(__DIR__, 2) . '/wordpress-event-manager.php';

        $previousActor = get_current_user_id();
        $audit = $wpdb->prefix . 'wem_seating_admin_audit';
        $reservations = $wpdb->prefix . 'wem_table_reservations';
        $beforeAudit = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$audit}");
        $beforeActive = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$reservations} WHERE status='active' AND active_slot=1"
        );

        try {
            wp_set_current_user(0);
            $error = null;
            try {
                (new \WEM_Seating_Administrative_Reassignment_Service())->reassign(
                    1, 2, 'Unauthorized move'
                );
            } catch (\Throwable $caught) {
                $error = $caught;
            }
            self::assertInstanceOf(\DomainException::class, $error);
            self::assertSame('Administrative seating permission denied.', $error->getMessage());
            self::assertSame($beforeAudit, (int) $wpdb->get_var("SELECT COUNT(*) FROM {$audit}"));
            self::assertSame($beforeActive, (int) $wpdb->get_var(
                "SELECT COUNT(*) FROM {$reservations} WHERE status='active' AND active_slot=1"
            ));
        } finally {
            wp_set_current_user($previousActor);
        }
    }
}
