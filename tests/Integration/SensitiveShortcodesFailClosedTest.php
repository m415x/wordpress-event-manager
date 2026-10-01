<?php

declare(strict_types=1);

namespace WEM\Tests\Integration;

use WP_UnitTestCase;

final class SensitiveShortcodesFailClosedTest extends WP_UnitTestCase
{
    public function testSensitiveShortcodesAreRegisteredAndDenyGuestAccess(): void
    {
        require_once dirname(__DIR__, 2) . '/wordpress-event-manager.php';

        // The WordPress test bootstrap has already fired plugins_loaded.
        do_action('plugins_loaded');

        $shortcodes = [
            'wem_checkin' => '[wem_checkin event="private-event"]',
            'wem_list' => '[wem_list event="private-event"]',
            'wem_qr_table' => '[wem_qr_table event="private-event" start="1" end="2"]',
            'wem_qr_single' => '[wem_qr_single event="private-event" ticket="sensitive-ticket"]',
        ];

        foreach ($shortcodes as $tag => $invocation) {
            self::assertTrue(shortcode_exists($tag), $tag . ' must be registered');

            $rendered = do_shortcode($invocation);
            self::assertSame(
                '<p>Guest access is temporarily unavailable.</p>',
                $rendered,
                $tag . ' must return only the fixed deny-all message before WEM-13'
            );
        }
    }
}
