<?php
declare(strict_types=1);
namespace WEM\Tests\Integration;
use WP_UnitTestCase;

/** WEM-92/T5: second InnoDB connection witnesses the post-lock authorization gate. */
final class SeatingBearerRevocationRaceTest extends WP_UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        require_once dirname(__DIR__, 2) . '/wordpress-event-manager.php';
        (new \WEM_Taxonomy_Manager())->register_taxonomy();
        \WEM_Event_Table_Schema::install();
        \WEM_Table_Reservation_Schema::install();
        \WEM_Seating_Participation_Schema::install();
        \WEM_Invitation_Credential_Schema::install();
    }

    public function testAuthorizationCallbackRunsUnderGuestLockAgainstIndependentConnection(): void
    {
        global $wpdb;
        $eventId = self::factory()->term->create(['taxonomy' => 'evento', 'name' => 'T5 lock event']);
        $guestId = self::factory()->post->create(['post_type' => 'invitado', 'post_status' => 'publish']);
        wp_set_object_terms($guestId, [$eventId], 'evento', false);
        $wpdb->insert(\WEM_Event_Table_Schema::table_name(), [
            'event_term_id' => $eventId, 'table_code' => 'LOCK-' . $guestId, 'capacity' => 1,
        ]);
        $tableId = (int) $wpdb->insert_id;

        $host = DB_HOST;
        $port = 3306;
        if (preg_match('/^([^:]+):(\d+)$/', $host, $matches)) {
            $host = $matches[1];
            $port = (int) $matches[2];
        }
        $second = new \mysqli($host, DB_USER, DB_PASSWORD, DB_NAME, $port);
        self::assertSame(0, $second->connect_errno);
        self::assertNotSame($wpdb->dbh->thread_id, $second->thread_id);

        $callbackRan = false;
        try {
            $id = (new \WEM_Seating_Reservation_Service())->reserve_initial(
                $guestId, $tableId,
                static function ($lockedGuestId, $lockedEventId) use ($guestId, $eventId, $second, &$callbackRan): void {
                    self::assertSame($guestId, (int) $lockedGuestId);
                    self::assertSame($eventId, (int) $lockedEventId);
                    $callbackRan = true;
                    // WEM-33 revoke/rotate also starts by locking this guest row.
                    $result = $second->query(
                        'SELECT ID FROM ' . $GLOBALS['wpdb']->posts .
                        ' WHERE ID=' . (int) $guestId . ' FOR UPDATE NOWAIT'
                    );
                    self::assertFalse($result, 'Revocation connection must not overtake the locked reservation.');
                }
            );
            self::assertTrue($callbackRan, 'Missing post-lock callback: initial RED must fail here.');
            self::assertGreaterThan(0, $id);
        } finally {
            $second->close();
        }
    }
}
