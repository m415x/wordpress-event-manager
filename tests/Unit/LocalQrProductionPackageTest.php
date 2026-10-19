<?php

declare(strict_types=1);

namespace WEM\Tests\Unit;

use PHPUnit\Framework\TestCase;

/** WEM-34: production ZIP must contain a runtime Composer autoloader. */
final class LocalQrProductionPackageTest extends TestCase
{
    public function testReproducibleProductionPackageScriptIsPresent(): void
    {
        $root = dirname(__DIR__, 2);
        $script = $root . '/scripts/package-plugin.sh';

        self::assertFileExists(
            $script,
            'WEM-34 needs a reproducible release packager; ignored vendor/ does not enter a Git archive.'
        );

        $source = (string) file_get_contents($script);
        self::assertStringContainsString('composer install', $source);
        self::assertStringContainsString('--no-dev', $source);
        self::assertStringContainsString('--no-scripts', $source);
        self::assertStringContainsString('vendor/autoload.php', $source);
        self::assertStringContainsString('chillerlan/php-qrcode', $source);
        self::assertStringContainsString('zip', $source);
    }
}
