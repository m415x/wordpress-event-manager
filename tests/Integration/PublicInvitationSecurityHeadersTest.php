<?php

declare(strict_types=1);

namespace WEM\Tests\Integration;

use WP_UnitTestCase;

final class PublicInvitationSecurityHeadersTest extends WP_UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        require_once dirname(__DIR__, 2) . '/wordpress-event-manager.php';
    }

    public function testPublicBearerResponsesDeclareNonLeakingCacheAndIndexPolicy(): void
    {
        $route = new \WEM_Public_Invitation_Route();

        self::assertSame(
            [
                'Referrer-Policy' => 'no-referrer',
                'Cache-Control' => 'private, no-store',
                'X-Robots-Tag' => 'noindex, nofollow, noarchive',
            ],
            $route->security_headers()
        );
    }

    public function testPublicRendererContainsNoExternalResourceReferences(): void
    {
        $renderer = new \WEM_Public_Invitation_Renderer();
        $html = $renderer->render([
            'guest_name' => 'Invitado',
            'event_name' => 'Evento',
        ]);

        foreach ([
            'http://',
            'https://',
            '<script',
            '<img',
            '<iframe',
            '<link',
            '<form',
        ] as $externalSurface) {
            self::assertStringNotContainsStringIgnoringCase($externalSurface, $html);
        }
    }
}
