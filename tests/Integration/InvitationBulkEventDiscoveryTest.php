<?php

declare(strict_types=1);

namespace WEM\Tests\Integration;

use WP_UnitTestCase;

final class InvitationBulkEventDiscoveryTest extends WP_UnitTestCase
{
    public function testDiscoversOnlyPublishedGuestsAssignedExclusivelyToRequestedEvent(): void
    {
        require_once dirname(__DIR__, 2) . '/wordpress-event-manager.php';
        (new \WEM_Taxonomy_Manager())->register_taxonomy();

        $eventA = self::factory()->term->create(['taxonomy' => 'evento', 'name' => 'WEM73 Discovery A']);
        $eventB = self::factory()->term->create(['taxonomy' => 'evento', 'name' => 'WEM73 Discovery B']);
        $eligible = self::factory()->post->create(['post_type' => 'invitado', 'post_status' => 'publish']);
        $other = self::factory()->post->create(['post_type' => 'invitado', 'post_status' => 'publish']);
        $draft = self::factory()->post->create(['post_type' => 'invitado', 'post_status' => 'draft']);
        $multiple = self::factory()->post->create(['post_type' => 'invitado', 'post_status' => 'publish']);
        wp_set_object_terms($eligible, [$eventA], 'evento');
        wp_set_object_terms($other, [$eventB], 'evento');
        wp_set_object_terms($draft, [$eventA], 'evento');
        wp_set_object_terms($multiple, [$eventA, $eventB], 'evento');

        self::assertTrue(class_exists('WEM_Invitation_Bulk_Event_Discovery'), 'WEM-73 event discovery is required.');
        $discovery = new \WEM_Invitation_Bulk_Event_Discovery();
        self::assertSame([$eligible], $discovery->guests($eventA));
        self::assertSame([$other], $discovery->guests($eventB));
        self::assertSame([$eligible], $discovery->guests($eventA));
    }
}
