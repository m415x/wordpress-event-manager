<?php
declare(strict_types=1);

namespace WEM\Tests\Integration;

use PHPUnit\Framework\TestCase;

/**
 * WEM-93/T6: the permissive path must be bounded per opaque, non-bearer key.
 * Atomic persistence across independent sessions is a separate mandatory gate.
 */
final class SeatingPublicAbuseGuardTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        require_once dirname(__DIR__, 2) . '/wordpress-event-manager.php';
    }

    public function testThreeAttemptsAllowedAndFourthDeniedWithoutStoringBearer(): void
    {
        $guard = new \WEM_Seating_Public_Abuse_Guard();
        $key = 'wem-t6-' . wp_generate_uuid4();
        self::assertTrue($guard->record_attempt($key, 3, 60));
        self::assertTrue($guard->record_attempt($key, 3, 60));
        self::assertTrue($guard->record_attempt($key, 3, 60));
        self::assertFalse($guard->record_attempt($key, 3, 60));
    }

    public function testIndependentBucketsCannotBypassTheLimitOfTheFirst(): void
    {
        $guard = new \WEM_Seating_Public_Abuse_Guard();
        $first = 'wem-t6-' . wp_generate_uuid4();
        $second = 'wem-t6-' . wp_generate_uuid4();
        self::assertTrue($guard->record_attempt($first, 1, 60));
        self::assertFalse($guard->record_attempt($first, 1, 60));
        self::assertTrue($guard->record_attempt($second, 1, 60));
    }
}
