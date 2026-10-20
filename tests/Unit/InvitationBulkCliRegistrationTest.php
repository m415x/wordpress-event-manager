<?php

declare(strict_types=1);

namespace WEM\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class InvitationBulkCliRegistrationTest extends TestCase
{
    public function testRegistersOnlyInCliContext(): void
    {
        require_once dirname(__DIR__, 2) . '/includes/class-invitation-bulk-cli-registration.php';
        self::assertTrue(class_exists('WEM_Invitation_Bulk_Cli_Registration'));
        $calls = [];
        $register = static function (string $name, $handler) use (&$calls): void {
            $calls[] = $name;
        };
        $registration = new \WEM_Invitation_Bulk_Cli_Registration($register);
        $registration->register(false);
        self::assertSame([], $calls);
        $registration->register(true);
        self::assertSame(['wem invitations'], $calls);
    }
}
