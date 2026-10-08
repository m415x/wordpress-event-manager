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

        $externalSurfaces = [
            'http://',
            'https://',
            '<script',
            '<img',
            '<iframe',
            '<link',
            '<form',
        ];

        foreach ($externalSurfaces as $externalSurface) {
            self::assertStringNotContainsStringIgnoringCase($externalSurface, $html);
        }
    }

    public function testPublicBearerHandlerAppliesSecurityHeadersToValidAndDeniedRequests(): void
    {
        $headers = [];
        $route = new \WEM_Public_Invitation_Route(
            static function ($line) use (&$headers): void {
                $headers[] = $line;
            }
        );

        $route->apply_security_headers();

        self::assertContains('Referrer-Policy: no-referrer', $headers);
        self::assertContains('Cache-Control: private, no-store', $headers);
        self::assertContains(
            'X-Robots-Tag: noindex, nofollow, noarchive',
            $headers
        );
    }

    public function testTemplateRedirectAppliesHardeningOnlyForPublicInvitationRequests(): void
    {
        $headers = [];
        $route = new \WEM_Public_Invitation_Route(
            static function ($line) use (&$headers): void {
                $headers[] = $line;
            }
        );

        $_GET = [];
        $route->handle_request();
        self::assertSame([], $headers);

        $_GET = [
            'wem_invitation' => str_repeat('A', 43),
        ];
        $route->handle_request();

        self::assertSame(
            [
                'Referrer-Policy: no-referrer',
                'Cache-Control: private, no-store',
                'X-Robots-Tag: noindex, nofollow, noarchive',
            ],
            $headers
        );

        $_GET = [
            'wem_invitation' => str_repeat('A', 43),
            'ticket' => 'forbidden-extra-authority',
        ];
        $route->handle_request();

        self::assertSame(
            [
                'Referrer-Policy: no-referrer',
                'Cache-Control: private, no-store',
                'X-Robots-Tag: noindex, nofollow, noarchive',
                'Referrer-Policy: no-referrer',
                'Cache-Control: private, no-store',
                'X-Robots-Tag: noindex, nofollow, noarchive',
            ],
            $headers
        );
    }
}
