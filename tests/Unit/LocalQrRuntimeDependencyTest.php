<?php

declare(strict_types=1);

namespace WEM\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * WEM-34: executable dependency/packaging contract.
 * This test intentionally starts RED until the runtime QR library is locked.
 */
final class LocalQrRuntimeDependencyTest extends TestCase
{
    public function testLocalQrLibraryIsRuntimeDependencyWithRequiredExtension(): void
    {
        $manifest = json_decode(
            (string) file_get_contents(dirname(__DIR__, 2) . '/composer.json'),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        self::assertSame(
            '6.0.1',
            $manifest['require']['chillerlan/php-qrcode'] ?? null,
            'WEM-34 must pin the maintained QR encoder as a runtime dependency.'
        );

        self::assertArrayHasKey(
            'ext-mbstring',
            $manifest['require'],
            'WEM-34 must explicitly declare the required PHP extension.'
        );
    }

    public function testLocalQrLibraryIsPresentInLockedProductionDependencies(): void
    {
        $lock = json_decode(
            (string) file_get_contents(dirname(__DIR__, 2) . '/composer.lock'),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        $productionPackages = array_column($lock['packages'] ?? [], 'version', 'name');
        self::assertSame(
            '6.0.1',
            ltrim((string) ($productionPackages['chillerlan/php-qrcode'] ?? ''), 'v'),
            'WEM-34 encoder must be in Composer production packages.'
        );
    }
}
