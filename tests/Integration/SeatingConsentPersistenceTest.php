<?php

declare(strict_types=1);

namespace WEM\Tests\Integration;

use WP_UnitTestCase;

final class SeatingConsentPersistenceTest extends WP_UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        require_once dirname(__DIR__, 2) . '/wordpress-event-manager.php';
    }

    public function testConsentDefaultsPrivateCanBeWithdrawnAndIsScopedToGuestAndEvent(): void
    {
        global $wpdb;

        \WEM_Seating_Visibility_Consent_Schema::install();
        \WEM_Seating_Visibility_Consent_Schema::install();
        \WEM_Seating_Visibility_Consent_Schema::install();
        $table = \WEM_Seating_Visibility_Consent_Schema::table_name();
        $guest = 990001;
        $eventA = 880001;
        $eventB = 880002;

        self::assertSame(1, $wpdb->insert($table, [
            'guest_id' => $guest,
            'event_term_id' => $eventA,
            'updated_at' => '2026-10-21 00:00:00',
        ], ['%d', '%d', '%s']));

        self::assertSame('0', (string) $wpdb->get_var($wpdb->prepare(
            "SELECT visibility_opt_in FROM {$table} WHERE guest_id = %d AND event_term_id = %d",
            $guest,
            $eventA
        )), 'Consent must default to private without explicit opt-in.');

        self::assertSame(1, $wpdb->update(
            $table,
            ['visibility_opt_in' => 1],
            ['guest_id' => $guest, 'event_term_id' => $eventA],
            ['%d'],
            ['%d', '%d']
        ));
        self::assertSame(1, $wpdb->update(
            $table,
            ['visibility_opt_in' => 0],
            ['guest_id' => $guest, 'event_term_id' => $eventA],
            ['%d'],
            ['%d', '%d']
        ));
        self::assertSame('0', (string) $wpdb->get_var($wpdb->prepare(
            "SELECT visibility_opt_in FROM {$table} WHERE guest_id = %d AND event_term_id = %d",
            $guest,
            $eventA
        )), 'Revocation must persist as private.');

        self::assertSame(1, $wpdb->insert($table, [
            'guest_id' => $guest,
            'event_term_id' => $eventB,
            'updated_at' => '2026-10-21 00:00:00',
        ], ['%d', '%d', '%s']));
        self::assertSame('0', (string) $wpdb->get_var($wpdb->prepare(
            "SELECT visibility_opt_in FROM {$table} WHERE guest_id = %d AND event_term_id = %d",
            $guest,
            $eventB
        )));

        $wpdb->suppress_errors(true);
        try {
            $invalid = $wpdb->insert($table, [
                'guest_id' => $guest + 1,
                'event_term_id' => $eventA,
                'visibility_opt_in' => 2,
                'updated_at' => '2026-10-21 00:00:00',
            ], ['%d', '%d', '%d', '%s']);
        } finally {
            $wpdb->suppress_errors(false);
        }
        self::assertFalse($invalid, 'Non-binary consent values must be rejected at the database boundary.');
    }
}
