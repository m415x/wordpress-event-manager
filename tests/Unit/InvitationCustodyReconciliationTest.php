<?php

declare(strict_types=1);

namespace WEM\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * WEM-73: a durable bearer is not exportable until independently confirmed.
 * This test deliberately needs no WordPress database or WEM-72 harness.
 */
final class InvitationCustodyReconciliationTest extends TestCase
{
    public function testAmbiguousCommitRequiresExactActiveCredentialMatchBeforeExport(): void
    {
        if (!defined('ABSPATH')) {
            define('ABSPATH', dirname(__DIR__, 2) . '/');
        }
        require_once dirname(__DIR__, 2) . '/includes/class-invitation-custody-reconciler.php';
        $bearer = rtrim(strtr(base64_encode(str_repeat('a', 32)), '+/', '-_'), '=');
        $record = [
            'guest_id' => 17,
            'event_id' => 6,
            'generation' => 1,
            'token' => $bearer,
            'token_digest' => hash('sha256', $bearer),
        ];

        $matches = [
            'guest_id' => 17,
            'event_id' => 6,
            'generation' => 1,
            'token_digest' => hash('sha256', $bearer),
            'status' => 'active',
            'active_slot' => 1,
        ];

        $reconciler = new \WEM_Invitation_Custody_Reconciler();
        self::assertTrue($reconciler->is_exportable($record, $matches));

        foreach (
            [
            array_merge($matches, ['guest_id' => 18]),
            array_merge($matches, ['event_id' => 7]),
            array_merge($matches, ['generation' => 2]),
            array_merge($matches, ['token_digest' => str_repeat('0', 64)]),
            array_merge($matches, ['status' => 'revoked', 'active_slot' => null]),
            array_merge($matches, ['status' => 'rotated', 'active_slot' => null]),
            array_merge($matches, ['active_slot' => null]),
            null,
            ] as $invalid
        ) {
            self::assertFalse($reconciler->is_exportable($record, $invalid));
        }
    }
}
