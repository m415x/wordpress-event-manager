<?php

declare(strict_types=1);

namespace WEM\Tests\Integration;

use WP_UnitTestCase;

final class AdministrativeHooksContractTest extends WP_UnitTestCase
{
    public function testPluginRegistersAdministrativeHooksOnce(): void
    {
        self::assertTrue(function_exists('add_action'));

        require_once dirname(__DIR__, 2) . '/wordpress-event-manager.php';

        // The integration test framework already fired plugins_loaded
        // before loading this file, so invoke its callbacks explicitly.
        do_action('plugins_loaded');

        $expected = [
            ['add_meta_boxes', 'WEM_Metabox_Manager', 'add_metaboxes'],
            ['save_post_invitado', 'WEM_Metabox_Manager', 'save_metabox_data'],
            ['manage_invitado_posts_columns', 'WEM_Admin_Columns', 'modify_columns'],
            ['manage_invitado_posts_custom_column', 'WEM_Admin_Columns', 'render_columns'],
            ['restrict_manage_posts', 'WEM_Admin_Columns', 'add_filters'],
            ['pre_get_posts', 'WEM_Admin_Columns', 'handle_filters'],
            ['posts_search', 'WEM_Admin_Columns', 'extend_search'],
            ['admin_menu', 'WEM_Import_Export', 'add_import_export_page'],
        ];

        foreach ($expected as [$hook, $class, $method]) {
            self::assertSame(
                1,
                $this->countClassCallbacks($hook, $class, $method),
                sprintf('%s::%s should register exactly once on %s', $class, $method, $hook)
            );
        }
    }

    private function countClassCallbacks(string $hook, string $class, string $method): int
    {
        global $wp_filter;

        if (!isset($wp_filter[$hook])) {
            return 0;
        }

        $matches = 0;
        foreach ($wp_filter[$hook]->callbacks as $callbacks) {
            foreach ($callbacks as $entry) {
                $callback = $entry['function'];
                if (
                    is_array($callback)
                    && isset($callback[0], $callback[1])
                    && is_object($callback[0])
                    && $callback[0] instanceof $class
                    && $callback[1] === $method
                ) {
                    $matches++;
                }
            }
        }

        return $matches;
    }
}
