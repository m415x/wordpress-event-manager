<?php

declare(strict_types=1);

namespace WEM\Tests\Integration;

use WP_UnitTestCase;

final class PreissuedPublicLifecycleTest extends WP_UnitTestCase
{
    public function testBearerRemainsPrivateBeforeAssignmentAndResolvesAfterBinding(): void
    {
        global $wpdb;
        require_once dirname(__DIR__, 2) . '/wordpress-event-manager.php';
        (new \WEM_CPT_Manager())->register_cpt();
        (new \WEM_Taxonomy_Manager())->register_taxonomy();
        \WEM_Preissued_Invitation_Schema::install();
        \WEM_Invitation_Credential_Schema::install();
        $actor = self::factory()->user->create(['role' => 'administrator']);
        $event = self::factory()->term->create(['taxonomy' => 'evento']);
        self::assertNotWPError($event);
        $table = \WEM_Preissued_Invitation_Schema::table_name();
        // PHPUnit persists WEM inventory across tests while WP fixtures may
        // reuse post IDs. Select an identity not already reserved by inventory.
        $guest = 0;
        for ($attempt = 0; $attempt < 50; ++$attempt) {
            $candidate = (int) self::factory()->post->create([
                'post_type' => 'invitado', 'post_status' => 'publish',
            ]);
            $claimed = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$table} WHERE assigned_guest_id = %d", $candidate
            ));
            if ($claimed === null) {
                $guest = $candidate;
                break;
            }
        }
        self::assertGreaterThan(0, $guest, 'Synthetic guest must not collide with persistent inventory.');
        self::assertNotWPError(wp_set_object_terms($guest, [(int) $event], 'evento'));
        $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        self::assertSame(1, $wpdb->insert($table, [
            'event_term_id' => (int) $event,
            'token_digest' => hash('sha256', $token),
            'state' => 'unassigned',
            'issued_by' => $actor,
            'issued_at' => gmdate('Y-m-d H:i:s'),
        ]));
        $invitationId = (int) $wpdb->insert_id;
        $route = new \WEM_Public_Invitation_Route();
        self::assertNull($route->resolve_request(['wem_preissued' => $token]));
        self::assertSame('', $route->render_request(), 'No guest data before assignment.');
        (new \WEM_Preissued_Invitation_Service())->assign_once($invitationId, (int) $guest, (int) $actor);
        self::assertSame(
            ['guest_id' => (int) $guest, 'event_term_id' => (int) $event],
            $route->resolve_request(['wem_preissued' => $token])
        );
        self::assertSame(
            ['Referrer-Policy' => 'no-referrer',
             'Cache-Control' => 'private, no-store',
             'X-Robots-Tag' => 'noindex, nofollow, noarchive'],
            $route->security_headers()
        );
        self::assertNull($route->resolve_request([
            'wem_preissued' => $token, 'ticket' => '1',
        ]), 'Unexpected public parameters must be rejected.');
    }
}
