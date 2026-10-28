<?php
declare(strict_types=1);

namespace WEM\Tests\Integration;

use PHPUnit\Framework\TestCase;

/** WEM-93/T6: HTTP boundary security contract; do not expose production route yet. */
final class SeatingPublicHttpBehaviorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        require_once dirname(__DIR__, 2) . '/wordpress-event-manager.php';
    }

    public function testAuthenticationDenialsDoNotEnumerateBearersOrGuests(): void
    {
        $route = new \WEM_Seating_Public_Http_Route();
        $invalid = ['absent' => null, 'malformed' => 'bad', 'unknown' => str_repeat('A', 43)];
        $responses = [];
        foreach ($invalid as $name => $bearer) {
            $response = $route->handle_operation('availability', $bearer, []);
            self::assertSame(403, $response['status']);
            self::assertSame(['code' => 'seating_access_unavailable'], $response['body']);
            self::assertSame('private, no-store', $response['headers']['Cache-Control']);
            self::assertSame('no-referrer', $response['headers']['Referrer-Policy']);
            self::assertSame('noindex, nofollow, noarchive', $response['headers']['X-Robots-Tag']);
            if (is_string($bearer) && $bearer !== '') {
                self::assertStringNotContainsString($bearer, json_encode($response), $name);
            }
            $responses[] = $response;
        }
        self::assertSame($responses[0], $responses[1]);
        self::assertSame($responses[1], $responses[2]);
    }

    public function testAbuseGateDefaultsToDenyWhenNotConfigured(): void
    {
        $guard = new \WEM_Seating_Public_Abuse_Guard();
        self::assertFalse($guard->permits_request());
        self::assertSame(403, (new \WEM_Seating_Public_Http_Route())->handle_operation(
            'members', null, []
        )['status']);
    }
}
