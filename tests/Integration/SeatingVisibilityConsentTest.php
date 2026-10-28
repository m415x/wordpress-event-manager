<?php

declare(strict_types=1);

namespace WEM\Tests\Integration;

use WP_UnitTestCase;

final class SeatingVisibilityConsentTest extends WP_UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        require_once dirname(__DIR__, 2) . '/wordpress-event-manager.php';
        \WEM_Invitation_Credential_Schema::install();
        \WEM_Seating_Visibility_Consent_Schema::install();
        (new \WEM_Taxonomy_Manager())->register_taxonomy();
        global $wpdb;
        $wpdb->query('DELETE FROM ' . \WEM_Invitation_Credential_Schema::table_name());
        $wpdb->query('DELETE FROM ' . \WEM_Seating_Visibility_Consent_Schema::table_name());
    }

    public function testConsentDefaultsPrivateAndRemainsBoundToGuestEventAcrossBearerRotation(): void
    {
        $admin = self::factory()->user->create(['role' => 'administrator']);
        $event = self::factory()->term->create(['taxonomy' => 'evento', 'name' => 'Consent Event']);
        $otherEvent = self::factory()->term->create(['taxonomy' => 'evento', 'name' => 'Other Consent Event']);
        $guest = self::factory()->post->create(['post_type' => 'invitado', 'post_status' => 'publish']);
        $otherGuest = self::factory()->post->create(['post_type' => 'invitado', 'post_status' => 'publish']);
        wp_set_object_terms($guest, [$event], 'evento', false);
        wp_set_object_terms($otherGuest, [$otherEvent], 'evento', false);
        $credentials = new \WEM_Invitation_Credential_Service();
        $token = $credentials->issue($guest, $admin)['token'];
        $otherToken = $credentials->issue($otherGuest, $admin)['token'];
        $service = new \WEM_Seating_Bearer_Consent();

        self::assertFalse($service->get_for_bearer($token));
        self::assertFalse($service->get_for_bearer($otherToken));
        self::assertTrue($service->set_for_bearer($token, true));
        self::assertTrue($service->get_for_bearer($token));
        self::assertFalse($service->get_for_bearer($otherToken));

        $rotated = $credentials->rotate($guest, $admin)['token'];
        self::assertNull($service->get_for_bearer($token));
        self::assertTrue($service->get_for_bearer($rotated));
        self::assertTrue($service->set_for_bearer($rotated, false));
        self::assertFalse($service->get_for_bearer($rotated));

        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare(
            'SELECT guest_id,event_term_id,visibility_opt_in FROM ' . \WEM_Seating_Visibility_Consent_Schema::table_name() . ' WHERE guest_id=%d AND event_term_id=%d',
            $guest, $event
        ), ARRAY_A);
        self::assertSame((string) $guest, (string) $row['guest_id']);
        self::assertSame((string) $event, (string) $row['event_term_id']);
        self::assertSame(0, (int) $row['visibility_opt_in']);
    }

    public function testMalformedBearerAndNonBooleanConsentAreDeniedWithoutMutation(): void
    {
        $service = new \WEM_Seating_Bearer_Consent();
        self::assertNull($service->get_for_bearer('invalid'));
        self::assertFalse($service->set_for_bearer('invalid', true));
        self::assertFalse($service->set_for_bearer('invalid', 'yes'));
    }
}
