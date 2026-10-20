<?php

declare(strict_types=1);

namespace WEM\Tests\Integration;

use WP_UnitTestCase;

/**
 * Run alone on the disposable PHPUnit environment due to WEM-72.
 */
final class InvitationCustodyDatabaseReconciliationTest extends WP_UnitTestCase
{
    public function testExportRequiresConfirmedActiveCredentialAndExactEvent(): void
    {
        require_once dirname(__DIR__, 2) . '/wordpress-event-manager.php';
        \WEM_Invitation_Credential_Schema::install();

        $admin = self::factory()->user->create(['role' => 'administrator']);
        $event = self::factory()->term->create(['taxonomy' => 'evento', 'name' => 'WEM-73 synthetic event']);
        $guest = self::factory()->post->create(['post_type' => 'invitado', 'post_status' => 'publish']);
        wp_set_object_terms($guest, [$event], 'evento');
        $issued = (new \WEM_Invitation_Credential_Service())->issue($guest, $admin);
        $custody = [
            'guest_id' => $guest,
            'event_id' => $event,
            'generation' => $issued['generation'],
            'token' => $issued['token'],
            'token_digest' => hash('sha256', $issued['token']),
        ];

        self::assertTrue(class_exists('WEM_Invitation_Custody_Database_Reconciliation'));
        $reader = new \WEM_Invitation_Custody_Database_Reconciliation();
        self::assertTrue($reader->is_exportable($custody));
        self::assertFalse($reader->is_exportable(array_merge($custody, ['event_id' => $event + 1])));
        self::assertFalse($reader->is_exportable(array_merge($custody, ['token_digest' => str_repeat('0', 64)])));

        (new \WEM_Invitation_Credential_Service())->revoke($guest, $admin);
        self::assertFalse($reader->is_exportable($custody));
    }
}
