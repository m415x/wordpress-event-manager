<?php

declare(strict_types=1);

namespace WEM\Tests\Integration;

use WP_UnitTestCase;

/** WEM-88/T1: authorization is independent of staff and never caller-selected. */
final class SeatingBearerAuthorizationTest extends WP_UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        require_once dirname(__DIR__, 2) . '/wordpress-event-manager.php';
        \WEM_Invitation_Credential_Schema::install();
        (new \WEM_Taxonomy_Manager())->register_taxonomy();
        global $wpdb;
        $wpdb->query('DELETE FROM ' . \WEM_Invitation_Credential_Schema::table_name());
    }

    public function testOnlyActiveBearerResolvesCanonicalGuestAndEvent(): void
    {
        $admin = self::factory()->user->create(['role' => 'administrator']);
        $guest = self::factory()->post->create([
            'post_type' => 'invitado',
            'post_status' => 'publish',
            'post_title' => 'UNTRUSTED-TICKET',
        ]);
        $event = self::factory()->term->create(['taxonomy' => 'evento', 'name' => 'T1 authorized event']);
        wp_set_object_terms($guest, [$event], 'evento', false);

        $credentials = new \WEM_Invitation_Credential_Service();
        $issued = $credentials->issue($guest, $admin);
        $boundary = new \WEM_Seating_Bearer_Authorization();

        self::assertSame(['guest_id' => $guest, 'event_term_id' => $event], $boundary->resolve($issued['token']));
        foreach (['', 'UNTRUSTED-TICKET', (string) $guest, (string) $event, str_repeat('A', 43)] as $invalid) {
            self::assertNull($boundary->resolve($invalid));
        }

        $old = $issued['token'];
        $rotated = $credentials->rotate($guest, $admin);
        self::assertNull($boundary->resolve($old));
        self::assertSame(['guest_id' => $guest, 'event_term_id' => $event], $boundary->resolve($rotated['token']));
        $credentials->revoke($guest, $admin);
        self::assertNull($boundary->resolve($rotated['token']));
    }

    public function testEventScopeCannotBeSuppliedByCaller(): void
    {
        $admin = self::factory()->user->create(['role' => 'administrator']);
        $guest = self::factory()->post->create(['post_type' => 'invitado', 'post_status' => 'publish']);
        $event = self::factory()->term->create(['taxonomy' => 'evento', 'name' => 'T1 own event']);
        $other = self::factory()->term->create(['taxonomy' => 'evento', 'name' => 'T1 other event']);
        wp_set_object_terms($guest, [$event], 'evento', false);
        $token = (new \WEM_Invitation_Credential_Service())->issue($guest, $admin)['token'];

        $boundary = new \WEM_Seating_Bearer_Authorization();
        self::assertSame(['guest_id' => $guest, 'event_term_id' => $event], $boundary->resolve($token));
        self::assertNotSame($other, $boundary->resolve($token)['event_term_id']);
        self::assertSame(1, (new \ReflectionMethod($boundary, 'resolve'))->getNumberOfParameters());
    }
}
