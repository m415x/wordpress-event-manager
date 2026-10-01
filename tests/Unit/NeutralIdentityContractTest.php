<?php

declare(strict_types=1);

namespace WEM\Tests\Unit;

use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveCallbackFilterIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class NeutralIdentityContractTest extends TestCase
{
    public function testCanonicalPluginEntrypointAndHeaders(): void
    {
        $entrypoint = dirname(__DIR__, 2) . '/wordpress-event-manager.php';

        self::assertFileExists($entrypoint);

        $source = file_get_contents($entrypoint);
        self::assertIsString($source);
        self::assertStringContainsString('Plugin Name: WordPress Event Manager', $source);
        self::assertStringContainsString('Text Domain: wordpress-event-manager', $source);
    }

    public function testMaintainedProjectSourceDoesNotContainHistoricalIdentity(): void
    {
        $root = dirname(__DIR__, 2);
        $skipDirectories = [
            '.git',
            '.cache',
            '.pnpm-store',
            '.phpunit.cache',
            'vendor',
            'node_modules',
            'coverage',
            'test-results',
            'playwright-report',
        ];
        $includedExtensions = ['php', 'js', 'jsx', 'ts', 'tsx', 'css', 'md', 'txt', 'json', 'xml', 'yaml', 'yml', 'neon', 'sh'];
        $excludedNames = ['composer.lock', 'pnpm-lock.yaml', 'package-lock.json'];

        // Compose historical match expressions without embedding banned
        // identifiers in maintained test source or fixtures.
        $shortPrefix = chr(99) . chr(56);
        $agencyName = 'codigo' . chr(56);
        $accentedAgencyName = 'código' . chr(56);
        $expression = '/(?:' . preg_quote($shortPrefix, '/') . '|'
            . preg_quote($agencyName, '/') . '|'
            . preg_quote($accentedAgencyName, '/') . ')/iu';

        $directory = new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS);
        $filtered = new RecursiveCallbackFilterIterator(
            $directory,
            static function (SplFileInfo $file) use ($skipDirectories): bool {
                return !$file->isDir() || !in_array($file->getFilename(), $skipDirectories, true);
            }
        );

        $offenders = [];
        foreach (new RecursiveIteratorIterator($filtered) as $file) {
            if (!$file instanceof SplFileInfo || !$file->isFile()) {
                continue;
            }

            $relativePath = substr($file->getPathname(), strlen($root) + 1);
            $filename = $file->getFilename();
            $extension = strtolower((string) pathinfo($filename, PATHINFO_EXTENSION));

            if (in_array($filename, $excludedNames, true) || !in_array($extension, $includedExtensions, true)) {
                continue;
            }

            if (preg_match($expression, $relativePath) === 1) {
                $offenders[] = $relativePath . ' (filename)';
            }

            $lines = file($file->getPathname());
            self::assertIsArray($lines);
            foreach ($lines as $lineNumber => $line) {
                if (preg_match($expression, $line) === 1) {
                    $offenders[] = $relativePath . ':' . ($lineNumber + 1);
                }
            }
        }

        self::assertSame(
            [],
            $offenders,
            'Historical identity remains in maintained source at: ' . implode(', ', array_slice($offenders, 0, 30))
        );
    }
}
