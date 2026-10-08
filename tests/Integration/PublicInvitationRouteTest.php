<?php

declare(strict_types=1);

namespace WEM\Tests\Integration;

use WP_UnitTestCase;

final class PublicInvitationRouteTest extends WP_UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        require_once dirname(__DIR__, 2) . '/wordpress-event-manager.php';
        \WEM_Invitation_Credential_Schema::install();

        $taxonomy = new \WEM_Taxonomy_Manager();
        $taxonomy->register_taxonomy();

        global $wpdb;
        $tableName = \WEM_Invitation_Credential_Schema::table_name();
        $wpdb->query("DELETE FROM {$tableName}");
    }

    public function testPublicRouteWorksWithSimplePermalinksAndBearerOnly(): void
    {
        self::assertTrue(
            class_exists('WEM_Public_Invitation_Route'),
            'WEM-59 requires a dedicated public invitation route.'
        );

        update_option('permalink_structure', '');

        $adminId = self::factory()->user->create(['role' => 'administrator']);
        $guestId = self::factory()->post->create([
            'post_type' => 'invitado',
            'post_status' => 'publish',
            'post_title' => 'ROUTE-TICKET-MUST-NOT-AUTHORIZE',
        ]);
        $eventId = self::factory()->term->create([
            'taxonomy' => 'evento',
            'name' => 'Simple Permalink Event',
            'slug' => 'simple-permalink-event',
        ]);
        wp_set_object_terms($guestId, [$eventId], 'evento', false);

        $service = new \WEM_Invitation_Credential_Service();
        $issued = $service->issue($guestId, $adminId);

        $route = new \WEM_Public_Invitation_Route();

        $resolved = $route->resolve_request([
            'wem_invitation' => $issued['token'],
        ]);

        self::assertSame(
            [
                'guest_id' => $guestId,
                'event_term_id' => $eventId,
            ],
            $resolved
        );

        self::assertNull($route->resolve_request([
            'ticket' => 'ROUTE-TICKET-MUST-NOT-AUTHORIZE',
        ]));
        self::assertNull($route->resolve_request([
            'event' => 'simple-permalink-event',
        ]));
        self::assertNull($route->resolve_request([
            'guest_id' => (string) $guestId,
        ]));
        self::assertNull($route->resolve_request([
            'wem_invitation' => $issued['token'],
            'ticket' => 'ROUTE-TICKET-MUST-NOT-AUTHORIZE',
            'event' => 'simple-permalink-event',
            'guest_id' => (string) $guestId,
        ]));
    }
}
