<?php

declare(strict_types=1);

namespace WEM\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class FrontendIdentityContractTest extends TestCase
{
    private const SURFACES = [
        'includes/class-qr-generator.php',
        'includes/class-shortcode-manager.php',
        'includes/class-ajax-handler.php',
        'assets/css/frontend.css',
        'assets/css/admin.css',
        'assets/js/frontend.js',
    ];

    public function testFrontendSurfacesUseOnlyNeutralIdentifiers(): void
    {
        $root = dirname(__DIR__, 2);
        $forbiddenPrefix = '/' . chr(99) . chr(56) . '/i';
        $offenders = [];

        foreach (self::SURFACES as $path) {
            $file = $root . '/' . $path;
            self::assertFileExists($file);

            $source = file_get_contents($file);
            self::assertIsString($source);

            if (preg_match($forbiddenPrefix, $source) === 1) {
                $offenders[] = $path;
            }
        }

        self::assertSame([], $offenders, 'Frontend surfaces still contain historical identity.');
    }

    public function testQrShortcodeNamesMatchCanonicalPublicContract(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/includes/class-qr-generator.php');
        self::assertIsString($source);
        self::assertStringContainsString("add_shortcode('wem_qr_table'", $source);
        self::assertStringContainsString("add_shortcode('wem_qr_single'", $source);
    }

    public function testSharedFrontendSelectorsAndJavaScriptConfigMatch(): void
    {
        $root = dirname(__DIR__, 2);
        $js = file_get_contents($root . '/assets/js/frontend.js');
        $css = file_get_contents($root . '/assets/css/frontend.css');
        $shortcode = file_get_contents($root . '/includes/class-shortcode-manager.php');
        $ajax = file_get_contents($root . '/includes/class-ajax-handler.php');
        self::assertIsString($js);
        self::assertIsString($css);
        self::assertIsString($shortcode);
        self::assertIsString($ajax);

        self::assertStringContainsString('const WEM =', $js);
        self::assertStringContainsString('wem_ajax.url', $js);
        self::assertStringContainsString("'.wem-clickable-row'", $js);

        self::assertStringContainsString('.wem-clickable-row', $css);
        self::assertStringContainsString('class="wem-clickable-row', $ajax);
        self::assertStringContainsString('.wem-list-table', $css);
        self::assertStringContainsString('class="wem-list-table', $ajax);
    }
}
