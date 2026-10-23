<?php

declare(strict_types=1);

namespace WEM\Tests\Integration;

use WP_UnitTestCase;

final class PreissuedPrintableSheetTest extends WP_UnitTestCase
{
    public function testSyntheticPrivatePrintSheetContainsOneLocalQrPerInvitation(): void
    {
        require_once dirname(__DIR__, 2) . '/wordpress-event-manager.php';

        self::assertTrue(class_exists('WEM_Preissued_Print_Sheet'),
            'P0 requires a dedicated private printable invitation renderer.');

        // Synthetic fixtures only. No issuance, persistence or real bearer.
        $records = [
            ['slot' => 'A-001', 'url' => 'https://example.invalid/?wem_preissued=' . str_repeat('A', 43)],
            ['slot' => 'A-002', 'url' => 'https://example.invalid/?wem_preissued=' . str_repeat('B', 43)],
        ];
        $html = (new \WEM_Preissued_Print_Sheet())->render($records);
        self::assertSame(2, substr_count($html, '<svg'), 'Every slot needs exactly one local QR.');
        self::assertStringContainsString('A-001', $html);
        self::assertStringContainsString('A-002', $html);
        self::assertStringContainsString('@media print', $html);
        self::assertStringNotContainsString('chart.googleapis.com', $html);
        self::assertStringNotContainsString('api.qrserver.com', $html);
        self::assertStringNotContainsString('<script', $html);
    }
}
