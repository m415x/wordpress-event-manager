<?php

declare(strict_types=1);

namespace WEM\Tests\Integration;

use RuntimeException;
use WP_UnitTestCase;

final class MovementAtomicRollbackTest extends WP_UnitTestCase
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

    public function testProjectionFailureRollsBackLedgerAppendAndProjection(): void
    {
        $event = wp_insert_term('WEM-27 Rollback Event', 'evento');
        self::assertNotWPError($event);

        $eventId = (int) $event['term_id'];
        $guestId = self::factory()->post->create([
            'post_type' => 'invitado',
            'post_title' => 'WEM-27 Rollback Guest',
            'post_status' => 'publish',
        ]);
        wp_set_object_terms($guestId, [$eventId], 'evento', false);

        $actorId = self::factory()->user->create(['role' => 'subscriber']);

        $failProjection = static function ($check, $objectId, $metaKey) use ($guestId) {
            if ((int) $objectId === $guestId && $metaKey === 'wem_checkin_at') {
                return false;
            }

            return $check;
        };

        add_filter('update_post_metadata', $failProjection, 10, 3);

        $service = new \WEM_Movement_Service();

        try {
            $service->checkin($guestId, $eventId, $actorId, 'staff_web');
            self::fail('Projection failure must abort the transition.');
        } catch (RuntimeException $exception) {
            self::assertSame('Unable to update checkin time projection.', $exception->getMessage());
        } finally {
            remove_filter('update_post_metadata', $failProjection, 10);
        }

        $ledger = new \WEM_Movement_Ledger();
        $history = $ledger->find_by_guest_event($guestId, $eventId);

        self::assertCount(0, $history, 'Rolled-back append must not remain in the ledger.');
        self::assertSame('', (string) get_post_meta($guestId, 'wem_checkin', true));
        self::assertSame('', (string) get_post_meta($guestId, 'wem_checkin_at', true));
        self::assertSame('', (string) get_post_meta($guestId, 'wem_checkin_by', true));
        self::assertSame('', (string) get_post_meta($guestId, 'wem_checkout', true));
        self::assertSame('', (string) get_post_meta($guestId, 'wem_checkout_at', true));
        self::assertSame('', (string) get_post_meta($guestId, 'wem_checkout_by', true));
    }
}
