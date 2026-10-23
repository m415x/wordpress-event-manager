<?php

declare(strict_types=1);

namespace WEM\Tests\Integration;

use PHPUnit\Framework\TestCase;

/** Physical InnoDB probe: a direct SQL writer must not erase assigned custody. */
final class PreissuedInvitationDeletionGuardTest extends TestCase
{
    public function testAssignedInvitationCannotBeDeletedByDirectSql(): void
    {
        global $wpdb;
        require_once dirname(__DIR__, 2) . '/wordpress-event-manager.php';
        \WEM_Preissued_Invitation_Schema::install();
        $table = \WEM_Preissued_Invitation_Schema::table_name();

        self::assertNotFalse($wpdb->query('START TRANSACTION'));
        try {
            $created = $wpdb->insert($table, [
                'event_term_id' => 123456,
                'token_digest' => hash('sha256', random_bytes(32)),
                'state' => 'unassigned',
                'issued_by' => 1,
                'issued_at' => gmdate('Y-m-d H:i:s'),
            ], ['%d', '%s', '%s', '%d', '%s']);
            self::assertSame(1, $created);
            $id = (int) $wpdb->insert_id;
            $updated = $wpdb->update($table, [
                'state' => 'assigned',
                'assigned_guest_id' => 987654,
                'assigned_by' => 1,
                'assigned_at' => gmdate('Y-m-d H:i:s'),
            ], ['id' => $id], ['%s', '%d', '%d', '%s'], ['%d']);
            self::assertSame(1, $updated, 'Valid one-time transition must remain possible.');

            // An append-only one-time assignment cannot survive deletion of
            // its inventory identity. This is expected to fail before DELETE guard.
            $deleted = $wpdb->delete($table, ['id' => $id], ['%d']);
            self::assertFalse($deleted, 'Physical SQL DELETE must reject an assigned invitation.');
            self::assertSame('assigned', $wpdb->get_var($wpdb->prepare(
                "SELECT state FROM {$table} WHERE id = %d", $id
            )));
        } finally {
            $wpdb->query('ROLLBACK');
        }
    }
}
