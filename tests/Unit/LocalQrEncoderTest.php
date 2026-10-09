<?php

declare(strict_types=1);

namespace WEM\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * WEM-34: local SVG encoder accepts only an already-built WEM-33 URL.
 */
final class LocalQrEncoderTest extends TestCase
{
    public function testRuntimeEncoderProducesSelfContainedSvgForCanonicalInvitationUrl(): void
    {
        $path = dirname(__DIR__, 2) . '/includes/class-local-qr-encoder.php';

        self::assertFileExists(
            $path,
            'A dedicated QR encoder adapter must exist, separate from the WEM-33 credential service.'
        );

        require_once $path;

        self::assertTrue(class_exists('WEM_Local_QR_Encoder'));

        $url = 'https://example.test/?wem_invitation=' . str_repeat('A', 43);
        $encoder = new \WEM_Local_QR_Encoder();
        $svg = $encoder->render_svg($url);

        self::assertStringContainsString('<svg', $svg);
        self::assertStringContainsString('</svg>', $svg);
        self::assertStringNotContainsString('http://www.w3.org/1999/xlink', $svg);
        self::assertStringNotContainsString('chart.googleapis.com', $svg);
        self::assertStringNotContainsString('<image', $svg);
    }
}
