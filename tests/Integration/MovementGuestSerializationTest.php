<?php

declare(strict_types=1);

namespace WEM\Tests\Integration;

use RuntimeException;
use WP_UnitTestCase;

final class MovementGuestSerializationTest extends WP_UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        require_once dirname(__DIR__, 2) . '/wordpress-event-manager.php';

        if (!taxonomy_exists('evento')) {
            register_taxonomy('evento', 'invitado');
        }

        if (!post_type_exists('invitado')) {
            register_post_type('invitado', ['public' => false]);
        }

        \WEM_Movement_Schema::install();
    }

    public function testSecondCheckinAfterSerializedStateRereadIsRejectedWithoutSecondMovement(): void
    {
        $event = wp_insert_term('WEM-27 Serialization Event', 'evento');
        self::assertNotWPError($event);

        $eventId = (int) $event['term_id'];
        $guestId = self::factory()->post->create([
            'post_type' => 'invitado',
            'post_title' => 'WEM-27 Serialized Guest',
            'post_status' => 'publish',
        ]);
        wp_set_object_terms($guestId, [$eventId], 'evento', false);

        $actorId = self::factory()->user->create(['role' => 'subscriber']);

        $service = new \WEM_Movement_Service();
        $first = $service->checkin($guestId, $eventId, $actorId, 'staff_web');

        self::assertTrue($first['ok']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Guest is already inside.');

        try {
            $service->checkin($guestId, $eventId, $actorId, 'staff_web');
        } finally {
            $ledger = new \WEM_Movement_Ledger();
            $history = $ledger->find_by_guest_event($guestId, $eventId);

            self::assertCount(1, $history);
            self::assertSame('checkin', $history[0]['movement_type']);
            self::assertSame('1', (string) get_post_meta($guestId, 'wem_checkin', true));
            self::assertSame('', (string) get_post_meta($guestId, 'wem_checkout', true));
        }
    }

    public function testServiceUsesDatabaseGuestSerializationBeforeProjectionReread(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/includes/class-movement-service.php');

        self::assertIsString($source);
        self::assertStringContainsString('FOR UPDATE', $source);
        self::assertStringContainsString('SELECT ID', $source);
        self::assertStringContainsString('$wpdb->posts', $source);
    }
}
