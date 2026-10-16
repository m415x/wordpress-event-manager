<?php

declare(strict_types=1);

namespace WEM\Tests\Integration;

require_once __DIR__ . '/Support/WemTransactionalTestCase.php';

use WEM\Tests\Integration\Support\WemTransactionalTestCase;

/** Focal isolation acceptance; not part of the default integration gate yet. */
final class HarnessWemScopedCleanupSpikeTest extends WemTransactionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (!post_type_exists('invitado')) {
            register_post_type('invitado', array('public' => false));
        }
        if (!taxonomy_exists('evento')) {
            register_taxonomy('evento', 'invitado');
        }
    }

    private function createUniqueGuest(): int
    {
        global $wpdb;
        $movement = \WEM_Movement_Schema::table_name();
        $credential = \WEM_Invitation_Credential_Schema::table_name();
        $max = (int) $wpdb->get_var("SELECT GREATEST(
            COALESCE((SELECT MAX(guest_id) FROM {$movement}), 0),
            COALESCE((SELECT MAX(guest_id) FROM {$credential}), 0),
            COALESCE((SELECT MAX(ID) FROM {$wpdb->posts}), 0)
        )");
        $id = wp_insert_post(array(
            'import_id' => $max + 10000,
            'post_type' => 'invitado',
            'post_status' => 'publish',
            'post_title' => 'WEM scoped cleanup acceptance',
        ), true);
        self::assertNotWPError($id);
        self::assertSame($max + 10000, (int) $id);
        $this->registerOwnedGuest((int) $id);
        return (int) $id;
    }

    private function createActor(string $role): int
    {
        $id = self::factory()->user->create(array('role' => $role));
        $this->registerOwnedUser($id);
        return $id;
    }

    public function test01MovementCommitLeavesNoOwnedFixturesAfterTeardown(): void
    {
        global $wpdb;
        $guestId = $this->createUniqueGuest();
        $actorId = $this->createActor('subscriber');
        $term = wp_insert_term('WEM scoped test ' . wp_generate_uuid4(), 'evento');
        self::assertNotWPError($term);
        $eventId = (int) $term['term_id'];
        $this->registerOwnedEvent($eventId);
        wp_set_object_terms($guestId, array($eventId), 'evento', false);

        self::assertCount(0, (new \WEM_Movement_Ledger())->find_by_guest_event($guestId, $eventId));
        $result = (new \WEM_Movement_Service())->checkin($guestId, $eventId, $actorId, 'staff_web');
        self::assertTrue($result['ok']);
        self::assertSame('0', (string) $wpdb->get_var('SELECT @@in_transaction'));
    }

    /** @depends test01MovementCommitLeavesNoOwnedFixturesAfterTeardown */
    public function test02CredentialCommitLeavesNoOwnedFixturesAfterTeardown(): void
    {
        global $wpdb;
        $guestId = $this->createUniqueGuest();
        $actorId = $this->createActor('administrator');
        $credential = \WEM_Invitation_Credential_Schema::table_name();
        self::assertSame(0, (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$credential} WHERE guest_id = %d", $guestId
        )));
        $issued = (new \WEM_Invitation_Credential_Service())->issue($guestId, $actorId);
        self::assertSame(1, $issued['generation']);
        self::assertSame('0', (string) $wpdb->get_var('SELECT @@in_transaction'));
    }

    /** @depends test02CredentialCommitLeavesNoOwnedFixturesAfterTeardown */
    public function test03FollowUpCaseStartsAtIdenticalBaseline(): void
    {
        global $wpdb;
        self::assertSame('1', (string) $wpdb->get_var('SELECT @@in_transaction'));
        self::assertSame(0, (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts}"));
    }
}
