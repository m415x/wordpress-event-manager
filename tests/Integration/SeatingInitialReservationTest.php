<?php

declare(strict_types=1);

namespace WEM\Tests\Integration;

use WP_UnitTestCase;

/**
 * WEM-82/T2: initial reservation must derive event identity and eligibility
 * on the server, never from a caller-provided event label alone.
 */
final class SeatingInitialReservationTest extends WP_UnitTestCase
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

    public function testInitialReservationServiceProvidesServerValidatedEntryPoint(): void
    {
        self::assertTrue(
            class_exists('WEM_Seating_Reservation_Service'),
            'WEM-82/T2 requires the initial-reservation domain service boundary.'
        );
        self::assertTrue(
            method_exists('WEM_Seating_Reservation_Service', 'reserve_initial'),
            'WEM-82/T2 requires a reserve_initial entry point for validated atomic reservation.'
        );
    }
}
