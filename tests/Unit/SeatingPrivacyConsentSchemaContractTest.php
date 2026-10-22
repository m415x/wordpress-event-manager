<?php

declare(strict_types=1);

namespace WEM\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class SeatingPrivacyConsentSchemaContractTest extends TestCase
{
    public function testConsentIsPersistedPerGuestAndEventWithAffirmativeRevocableState(): void
    {
        $path = dirname(__DIR__, 2) . '/includes/class-seating-visibility-consent-schema.php';

        self::assertFileExists($path, 'WEM-74 must define a dedicated canonical per-guest/event consent schema.');

        $source = file_get_contents($path);
        self::assertIsString($source);
        self::assertStringContainsString('guest_id', $source);
        self::assertStringContainsString('event_term_id', $source);
        self::assertStringContainsString('visibility_opt_in', $source);
        self::assertMatchesRegularExpression('/DEFAULT\\s+0/i', $source, 'Consent must be hidden by default.');
        self::assertStringContainsString('UNIQUE KEY guest_event', $source, 'Consent identity must be unique.');
        self::assertStringContainsString('updated_at', $source, 'Consent withdrawal must be persistable.');
    }
}
