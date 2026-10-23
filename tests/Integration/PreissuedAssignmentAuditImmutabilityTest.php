<?php

declare(strict_types=1);

namespace WEM\Tests\Integration;

use PHPUnit\Framework\TestCase;

/** Direct-SQL probe: assignment audit must be append-only. */
final class PreissuedAssignmentAuditImmutabilityTest extends TestCase
{
    public function testAssignmentAuditCannotBeRewrittenOrDeleted(): void
    {
        global $wpdb;
        require_once dirname(__DIR__, 2) . '/wordpress-event-manager.php';
        \WEM_Preissued_Invitation_Schema::install();
        $table = \WEM_Preissued_Invitation_Schema::audit_table_name();

        self::assertNotFalse($wpdb->query('START TRANSACTION'));
        try {
            self::assertSame(1, $wpdb->insert($table, [
                'invitation_id' => 986712,
                'guest_id' => 986713,
                'event_term_id' => 986714,
                'actor_user_id' => 1,
                'operation' => 'assign',
                'occurred_at' => gmdate('Y-m-d H:i:s'),
            ]));
            $id = (int) $wpdb->insert_id;
            self::assertFalse(
                $wpdb->update($table, ['guest_id' => 986715], ['id' => $id]),
                'Audit UPDATE must be rejected by the database.'
            );
            self::assertFalse(
                $wpdb->delete($table, ['id' => $id]),
                'Audit DELETE must be rejected by the database.'
            );
            self::assertSame('986713', (string) $wpdb->get_var($wpdb->prepare(
                "SELECT guest_id FROM {$table} WHERE id = %d", $id
            )));
        } finally {
            $wpdb->query('ROLLBACK');
        }
    }
}
