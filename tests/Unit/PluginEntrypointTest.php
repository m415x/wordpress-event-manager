<?php

declare(strict_types=1);

namespace WEM\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class PluginEntrypointTest extends TestCase
{
    public function testPluginEntrypointHasWordPressHeader(): void
    {
        $plugin = dirname(__DIR__, 2) . '/wordpress-event-checkin-manager.php';

        self::assertFileExists($plugin);
        $source = file_get_contents($plugin);

        self::assertIsString($source);
        self::assertStringContainsString('Plugin Name:', $source);
        self::assertStringContainsString('Version:', $source);
    }
}
