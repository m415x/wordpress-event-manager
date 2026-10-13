<?php

declare(strict_types=1);

namespace WEM\Tests\Integration;

use WP_UnitTestCase;

/**
 * Reissue must not silently replace an existing active credential.
 */
final class AdminInvitationQrReissueActiveTest extends WP_UnitTestCase
{
    public function testReissueWithActiveCredentialIsRejectedWithoutAnyStateChange(): void
    {
        require_once dirname(__DIR__, 2) . '/wordpress-event-manager.php';
        \WEM_Invitation_Credential_Schema::install();

        $actorId = self::factory()->user->create(['role' => 'administrator']);
        $guestId = self::factory()->post->create([
            'post_type' => 'invitado',
            'post_status' => 'publish',
        ]);

        $service = new \WEM_Invitation_Credential_Service();
        $service->issue($guestId, $actorId);

        global $wpdb;
        $table = \WEM_Invitation_Credential_Schema::table_name();
        $before = $wpdb->get_results($wpdb->prepare(
            "SELECT generation, status, active_slot FROM {$table} WHERE guest_id = %d ORDER BY generation",
            $guestId
        ), ARRAY_A);

        try {
            $service->reissue($guestId, $actorId);
            self::fail('Reissue must reject a guest with an active credential.');
        } catch (\RuntimeException $error) {
            self::assertSame('Invitation already has an active credential.', $error->getMessage());
        }

        $after = $wpdb->get_results($wpdb->prepare(
            "SELECT generation, status, active_slot FROM {$table} WHERE guest_id = %d ORDER BY generation",
            $guestId
        ), ARRAY_A);
        self::assertSame($before, $after, 'DENY must leave generation and active slot unchanged.');
        self::assertCount(1, $after);
        self::assertSame('active', $after[0]['status']);
        self::assertSame(1, (int) $after[0]['generation']);
    }
}
