<?php

declare(strict_types=1);

namespace WEM\Tests\Integration;

use WP_UnitTestCase;

final class PreissuedLocalQrRuntimeTest extends WP_UnitTestCase
{
    public function testLocalEncoderRendersPrivateInvitationUrlWithoutRemoteService(): void
    {
        require_once dirname(__DIR__, 2) . '/wordpress-event-manager.php';
        self::assertTrue(class_exists('WEM_Local_QR_Encoder'), 'P0 requires bundled local QR encoder.');
        $url = home_url('/?wem_preissued=' . str_repeat('A', 43));
        $svg = (new \WEM_Local_QR_Encoder())->render_svg($url);
        self::assertStringContainsString('<svg', $svg);
        self::assertStringContainsString('</svg>', $svg);
        self::assertStringNotContainsString('chart.googleapis.com', $svg);
        self::assertStringNotContainsString('https://api.qrserver.com', $svg);
    }
}
