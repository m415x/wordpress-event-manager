<?php

declare(strict_types=1);

namespace WEM\Tests\Integration;

use WP_UnitTestCase;

final class WordPressPluginMetadataTest extends WP_UnitTestCase
{
    public function testWordPressCanReadPluginMetadataWithoutActivatingIt(): void
    {
        self::assertTrue(function_exists('add_action'));
        self::assertTrue(function_exists('get_option'));

        require_once ABSPATH . 'wp-admin/includes/plugin.php';

        $entrypoint = dirname(__DIR__, 2) . '/wordpress-event-checkin-manager.php';
        $metadata = get_plugin_data($entrypoint, false, false);

        self::assertNotEmpty($metadata['Name']);
        self::assertNotEmpty($metadata['Version']);
    }
}
