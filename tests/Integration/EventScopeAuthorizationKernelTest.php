<?php

declare(strict_types=1);

namespace WEM\Tests\Integration;

use WP_UnitTestCase;

final class EventScopeAuthorizationKernelTest extends WP_UnitTestCase
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
    }

    public function testAuthorizationKernelIsAvailableThroughPluginAutoloading(): void
    {
        self::assertTrue(
            class_exists('WEM_Authorization'),
            'WEM-20 requires a reusable authorization kernel loaded through the plugin autoloader.'
        );
    }

    public function testAuthorizationKernelExposesBoundedViewOperateAndScopeContract(): void
    {
        foreach (
            [
                'get_guest_event_term_id',
                'get_authorized_event_ids',
                'can_view_guest',
                'can_operate_guest',
            ] as $method
        ) {
            self::assertTrue(
                method_exists('WEM_Authorization', $method),
                sprintf('WEM-20 authorization kernel must expose %s().', $method)
            );
        }
    }

    public function testCanonicalGuestEventRequiresExactlyOneEventoTerm(): void
    {
        $eventA = wp_insert_term('WEM-20 Event A', 'evento');
        $eventB = wp_insert_term('WEM-20 Event B', 'evento');

        self::assertNotWPError($eventA);
        self::assertNotWPError($eventB);

        $eventAId = (int) $eventA['term_id'];
        $eventBId = (int) $eventB['term_id'];

        $singleEventGuest = $this->createGuest('WEM-20 Single Event Guest');
        $noEventGuest = $this->createGuest('WEM-20 No Event Guest');
        $multiEventGuest = $this->createGuest('WEM-20 Multi Event Guest');

        wp_set_object_terms($singleEventGuest, [$eventAId], 'evento', false);
        wp_set_object_terms($multiEventGuest, [$eventAId, $eventBId], 'evento', false);

        self::assertSame(
            $eventAId,
            \WEM_Authorization::get_guest_event_term_id($singleEventGuest),
            'Exactly one evento term must resolve to its canonical numeric term_id.'
        );
        self::assertNull(
            \WEM_Authorization::get_guest_event_term_id($noEventGuest),
            'A guest with no evento term must fail closed.'
        );
        self::assertNull(
            \WEM_Authorization::get_guest_event_term_id($multiEventGuest),
            'A guest with multiple evento terms must fail closed.'
        );
    }

    private function createGuest(string $title): int
    {
        return self::factory()->post->create(
            [
                'post_type' => 'invitado',
                'post_title' => $title,
                'post_status' => 'publish',
            ]
        );
    }
}
