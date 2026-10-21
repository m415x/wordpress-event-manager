<?php

declare(strict_types=1);

namespace WEM\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RuntimeException;

final class InvitationBulkIssuanceAuthorizationTest extends TestCase
{
    public function testRejectsUnauthorizedActorAndOutOfScopeGuestWithoutCallingIssuer(): void
    {
        $issued = 0;
        $issuer = static function () use (&$issued): void {
            $issued++;
        };
        $authorized = static function (int $actor): bool {
            return $actor === 1;
        };
        $event_membership = static function (int $guest, int $event): bool {
            return $guest === 10 && $event === 7;
        };

        require_once dirname(__DIR__, 2) . '/includes/class-invitation-bulk-issuance-authorizer.php';
        self::assertTrue(class_exists('WEM_Invitation_Bulk_Issuance_Authorizer'));
        $guard = new \WEM_Invitation_Bulk_Issuance_Authorizer($authorized, $event_membership);

        foreach ([[2, 10, 7], [1, 11, 7], [1, 10, 8]] as $input) {
            try {
                $guard->run($input[0], $input[1], $input[2], $issuer);
                self::fail('Unauthorized or out-of-scope issuance must be denied.');
            } catch (RuntimeException $error) {
                self::assertStringNotContainsString('token', strtolower($error->getMessage()));
            }
        }

        self::assertSame(0, $issued);
        $guard->run(1, 10, 7, $issuer);
        self::assertSame(1, $issued);
    }
}
