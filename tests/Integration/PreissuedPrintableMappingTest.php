<?php

declare(strict_types=1);

namespace WEM\Tests\Integration;

use WP_UnitTestCase;

final class PreissuedPrintableMappingTest extends WP_UnitTestCase
{
    public function testPrintSlotsKeepOneToOneUrlQrMappingAndRejectDuplicates(): void
    {
        require_once dirname(__DIR__, 2) . '/wordpress-event-manager.php';
        $encoder = new \WEM_Local_QR_Encoder();
        $sheet = new \WEM_Preissued_Print_Sheet();

        $urlA = 'https://example.invalid/?wem_preissued=' . str_repeat('A', 43);
        $urlB = 'https://example.invalid/?wem_preissued=' . str_repeat('B', 43);
        $html = $sheet->render([
            ['slot' => 'A-001', 'url' => $urlA],
            ['slot' => 'A-002', 'url' => $urlB],
        ]);

        // Compare the generated QR byte-for-byte to the same local encoder.
        // A distinct slot must not accidentally receive the other URL's QR.
        self::assertSame(1, substr_count($html, $encoder->render_svg($urlA)));
        self::assertSame(1, substr_count($html, $encoder->render_svg($urlB)));
        $positionA = strpos($html, $encoder->render_svg($urlA));
        $positionB = strpos($html, $encoder->render_svg($urlB));
        self::assertIsInt($positionA);
        self::assertIsInt($positionB);
        self::assertLessThan($positionB, $positionA);
        self::assertSame(2, substr_count($html, '<article class="wem-slot">'));

        foreach ([
            [['slot' => 'A-001', 'url' => $urlA], ['slot' => 'A-001', 'url' => $urlB]],
            [['slot' => 'A-001', 'url' => $urlA], ['slot' => 'A-002', 'url' => $urlA]],
        ] as $invalid) {
            try {
                $sheet->render($invalid);
                self::fail('Duplicate slot or URL must be rejected.');
            } catch (\InvalidArgumentException $expected) {
                self::assertSame('Duplicate or invalid print slot/URL.', $expected->getMessage());
            }
        }
    }
}
