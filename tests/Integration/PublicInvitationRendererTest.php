<?php

declare(strict_types=1);

namespace WEM\Tests\Integration;

use WP_UnitTestCase;

final class PublicInvitationRendererTest extends WP_UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        require_once dirname(__DIR__, 2) . '/wordpress-event-manager.php';
    }

    public function testRendererOutputsOnlyMinimalReadOnlyProjection(): void
    {
        self::assertTrue(
            class_exists('WEM_Public_Invitation_Renderer'),
            'WEM-60 requires a dedicated public invitation renderer.'
        );

        $renderer = new \WEM_Public_Invitation_Renderer();

        $html = $renderer->render([
            'guest_name' => '<script>alert(1)</script> Invitado',
            'event_name' => 'Evento & Privado',
        ]);

        self::assertStringContainsString(
            '&lt;script&gt;alert(1)&lt;/script&gt; Invitado',
            $html
        );
        self::assertStringContainsString('Evento &amp; Privado', $html);

        foreach ([
            '<form',
            '<button',
            'check-in',
            'checkout',
            'reingreso',
            'observ',
            'ticket',
            'guest_id',
            'event_term_id',
            'wem_',
        ] as $forbidden) {
            self::assertStringNotContainsStringIgnoringCase($forbidden, $html);
        }
    }

    public function testRendererRejectsUnexpectedProjectionShape(): void
    {
        $renderer = new \WEM_Public_Invitation_Renderer();

        self::assertSame(
            '',
            $renderer->render([
                'guest_name' => 'Invitado',
                'event_name' => 'Evento',
                'ticket' => 'SECRET',
            ])
        );

        self::assertSame(
            '',
            $renderer->render([
                'guest_name' => 'Invitado',
            ])
        );
    }
}
