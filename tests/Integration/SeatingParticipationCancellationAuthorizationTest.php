<?php

declare(strict_types=1);

namespace WEM\Tests\Integration;

use PHPUnit\Framework\TestCase;

/** WEM-86: unauthenticated participation cancellation is rejected before mutation. */
final class SeatingParticipationCancellationAuthorizationTest extends TestCase
{
    public function testAnonymousCancellationCannotChangeParticipationSeatsOrAudit(): void
    {
        global $wpdb;
        require_once dirname(__DIR__, 2) . '/wordpress-event-manager.php';
        $previousActor = get_current_user_id();
        $participation = $wpdb->prefix . 'wem_seating_participation';
        $reservations = $wpdb->prefix . 'wem_table_reservations';
        $audit = $wpdb->prefix . 'wem_seating_admin_audit';
        $before = [
            (int) $wpdb->get_var("SELECT COUNT(*) FROM {$participation} WHERE participation_status='canceled'"),
            (int) $wpdb->get_var("SELECT COUNT(*) FROM {$reservations} WHERE status='active' AND active_slot=1"),
            (int) $wpdb->get_var("SELECT COUNT(*) FROM {$audit}"),
        ];
        try {
            wp_set_current_user(0);
            $failure = null;
            try {
                (new \WEM_Seating_Participation_Cancellation_Service())->cancel(1, 'Anonymous attempt');
            } catch (\Throwable $caught) {
                $failure = $caught;
            }
            self::assertInstanceOf(\DomainException::class, $failure);
            self::assertSame('Administrative seating permission denied.', $failure->getMessage());
            self::assertSame($before, [
                (int) $wpdb->get_var("SELECT COUNT(*) FROM {$participation} WHERE participation_status='canceled'"),
                (int) $wpdb->get_var("SELECT COUNT(*) FROM {$reservations} WHERE status='active' AND active_slot=1"),
                (int) $wpdb->get_var("SELECT COUNT(*) FROM {$audit}"),
            ]);
        } finally {
            wp_set_current_user($previousActor);
        }
    }
}
