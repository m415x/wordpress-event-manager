<?php

declare(strict_types=1);

namespace WEM\Tests\Integration;

use WP_UnitTestCase;

final class PluginBootstrapContractTest extends WP_UnitTestCase
{
    public function testPluginEntrypointResolvesAllModulesInWordPress(): void
    {
        self::assertTrue(
            function_exists('add_action'),
            'WordPress must bootstrap before this plugin integration contract runs.'
        );

        $entrypoint = dirname(__DIR__, 2) . '/wordpress-event-manager.php';
        self::assertFileExists($entrypoint);
        require_once $entrypoint;

        $modules = [
            'WEM_CPT_Manager',
            'WEM_Taxonomy_Manager',
            'WEM_Metabox_Manager',
            'WEM_Admin_Columns',
            'WEM_Ajax_Handler',
            'WEM_Shortcode_Manager',
            'WEM_Import_Export',
            'WEM_QR_Generator',
        ];

        foreach ($modules as $module) {
            self::assertTrue(
                class_exists($module),
                'Neutral plugin autoload cannot resolve module ' . $module
            );
        }
    }
}
