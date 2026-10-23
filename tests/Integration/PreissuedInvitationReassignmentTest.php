<?php

declare(strict_types=1);

namespace WEM\Tests\Integration;

use PHPUnit\Framework\TestCase;

/** Independent SQL transaction: no WordPress test transaction can hide mutations. */
final class PreissuedInvitationReassignmentTest extends TestCase
{
    public function testSqlCannotReassignAnAlreadyAssignedInvitation(): void
    {
        global $wpdb;
        require_once dirname(__DIR__, 2) . '/wordpress-event-manager.php';
        \WEM_Preissued_Invitation_Schema::install();
        $table = \WEM_Preissued_Invitation_Schema::table_name();

        self::assertNotFalse($wpdb->query('START TRANSACTION'));
        try {
            self::assertSame(1, $wpdb->insert($table, [
                'event_term_id' => 123456,
                'token_digest' => hash('sha256', random_bytes(32)),
                'state' => 'unassigned',
                'issued_by' => 1,
                'issued_at' => gmdate('Y-m-d H:i:s'),
            ]));
            $id = (int) $wpdb->insert_id;
            self::assertSame(1, $wpdb->update($table, [
                'state' => 'assigned',
                'assigned_guest_id' => 987654,
                'assigned_by' => 1,
                'assigned_at' => gmdate('Y-m-d H:i:s'),
            ], ['id' => $id]));
            $reassigned = $wpdb->update($table,
                ['assigned_guest_id' => 987655], ['id' => $id]);
            self::assertFalse($reassigned, 'Direct SQL reassignment must fail.');
            self::assertSame('987654', (string) $wpdb->get_var($wpdb->prepare(
                "SELECT assigned_guest_id FROM {$table} WHERE id = %d", $id
            )));
        } finally {
            $wpdb->query('ROLLBACK');
        }
    }

    public function testAssignedInvitationCannotChangeEvent(): void
    {
        global $wpdb;
        require_once dirname(__DIR__, 2) . '/wordpress-event-manager.php';
        \WEM_Preissued_Invitation_Schema::install();
        $table = \WEM_Preissued_Invitation_Schema::table_name();
        self::assertNotFalse($wpdb->query('START TRANSACTION'));
        try {
            self::assertSame(1, $wpdb->insert($table, [
                'event_term_id' => 123456, 'token_digest' => hash('sha256', random_bytes(32)),
                'state' => 'unassigned', 'issued_by' => 1, 'issued_at' => gmdate('Y-m-d H:i:s'),
            ]));
            $id = (int) $wpdb->insert_id;
            self::assertFalse($wpdb->update($table, ['event_term_id' => 123457], ['id' => $id]),
                'Invitation event identity must remain immutable.');
        } finally {
            $wpdb->query('ROLLBACK');
        }
    }
}
