<?php

declare(strict_types=1);

namespace WEM\Tests\Integration;

use WP_UnitTestCase;

final class PublicInvitationResolverTest extends WP_UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        require_once dirname(__DIR__, 2) . '/wordpress-event-manager.php';
        \WEM_Invitation_Credential_Schema::install();

        global $wpdb;

        $tableName = \WEM_Invitation_Credential_Schema::table_name();
        $wpdb->query("DELETE FROM {$tableName}");
    }

    public function testActiveBearerResolvesExactlyOneInvitationAndEvent(): void
    {
        self::assertTrue(
            class_exists('WEM_Public_Invitation_Resolver'),
            'WEM-59 requires a dedicated public invitation resolver.'
        );

        $adminId = self::factory()->user->create(['role' => 'administrator']);
        $guestId = self::factory()->post->create([
            'post_type' => 'invitado',
            'post_status' => 'publish',
            'post_title' => 'VISIBLE-TICKET-MUST-NOT-AUTHORIZE',
        ]);
        $eventId = self::factory()->term->create([
            'taxonomy' => 'evento',
            'name' => 'Resolver Event',
            'slug' => 'resolver-event',
        ]);
        wp_set_object_terms($guestId, [$eventId], 'evento', false);

        $service = new \WEM_Invitation_Credential_Service();
        $issued = $service->issue($guestId, $adminId);

        $resolver = new \WEM_Public_Invitation_Resolver();
        $resolved = $resolver->resolve($issued['token']);

        self::assertSame(
            [
                'guest_id' => $guestId,
                'event_term_id' => $eventId,
            ],
            $resolved
        );

        self::assertNull($resolver->resolve('VISIBLE-TICKET-MUST-NOT-AUTHORIZE'));
        self::assertNull($resolver->resolve('resolver-event'));
        self::assertNull($resolver->resolve((string) $guestId));
    }

    public function testMalformedUnknownRotatedAndRevokedBearersAreAllDenied(): void
    {
        $adminId = self::factory()->user->create(['role' => 'administrator']);

        $rotatedGuestId = self::factory()->post->create([
            'post_type' => 'invitado',
            'post_status' => 'publish',
            'post_title' => 'WEM-59-ROTATED',
        ]);
        $rotatedEventId = self::factory()->term->create([
            'taxonomy' => 'evento',
            'name' => 'Rotated Event',
        ]);
        wp_set_object_terms($rotatedGuestId, [$rotatedEventId], 'evento', false);

        $revokedGuestId = self::factory()->post->create([
            'post_type' => 'invitado',
            'post_status' => 'publish',
            'post_title' => 'WEM-59-REVOKED',
        ]);
        $revokedEventId = self::factory()->term->create([
            'taxonomy' => 'evento',
            'name' => 'Revoked Event',
        ]);
        wp_set_object_terms($revokedGuestId, [$revokedEventId], 'evento', false);

        $service = new \WEM_Invitation_Credential_Service();
        $rotatedFirst = $service->issue($rotatedGuestId, $adminId);
        $service->rotate($rotatedGuestId, $adminId);

        $revoked = $service->issue($revokedGuestId, $adminId);
        $service->revoke($revokedGuestId, $adminId);

        $resolver = new \WEM_Public_Invitation_Resolver();

        $denied = [
            '',
            'not-a-token',
            str_repeat('A', 43),
            $rotatedFirst['token'],
            $revoked['token'],
        ];

        foreach ($denied as $token) {
            self::assertNull($resolver->resolve($token));
        }
    }

    public function testResolverFailsClosedWhenGuestDoesNotHaveExactlyOneEvent(): void
    {
        $adminId = self::factory()->user->create(['role' => 'administrator']);
        $service = new \WEM_Invitation_Credential_Service();
        $resolver = new \WEM_Public_Invitation_Resolver();

        $zeroEventGuestId = self::factory()->post->create([
            'post_type' => 'invitado',
            'post_status' => 'publish',
            'post_title' => 'WEM-59-ZERO-EVENT',
        ]);
        $zeroEventCredential = $service->issue($zeroEventGuestId, $adminId);

        self::assertNull($resolver->resolve($zeroEventCredential['token']));

        $multiEventGuestId = self::factory()->post->create([
            'post_type' => 'invitado',
            'post_status' => 'publish',
            'post_title' => 'WEM-59-MULTI-EVENT',
        ]);
        $eventOne = self::factory()->term->create([
            'taxonomy' => 'evento',
            'name' => 'Event One',
        ]);
        $eventTwo = self::factory()->term->create([
            'taxonomy' => 'evento',
            'name' => 'Event Two',
        ]);
        wp_set_object_terms($multiEventGuestId, [$eventOne, $eventTwo], 'evento', false);
        $multiEventCredential = $service->issue($multiEventGuestId, $adminId);

        self::assertNull($resolver->resolve($multiEventCredential['token']));
    }
}
