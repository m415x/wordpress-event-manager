<?php

declare(strict_types=1);

namespace WEM\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class GuestMetadataNamingTest extends TestCase
{
    private const CONSUMERS = [
        'includes/helpers.php',
        'includes/class-metabox-manager.php',
        'includes/class-admin-columns.php',
        'includes/class-ajax-handler.php',
        'includes/class-import-export.php',
        'includes/class-shortcode-manager.php',
    ];

    public function testGuestMetadataConsumersUseCanonicalNames(): void
    {
        $root = dirname(__DIR__, 2);
        $forbidden = '/' . chr(99) . chr(56) . '/i';
        $remaining = [];

        foreach (self::CONSUMERS as $path) {
            $file = $root . '/' . $path;
            self::assertFileExists($file);
            $source = file_get_contents($file);
            self::assertIsString($source);

            if (preg_match($forbidden, $source) === 1) {
                $remaining[] = $path;
            }
        }

        self::assertSame([], $remaining, 'Metadata consumers still use historical identifiers.');
    }

    public function testGuestHelpersExposeCanonicalContracts(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/includes/helpers.php');
        self::assertIsString($source);

        foreach ([
            'wem_get_evento_terms',
            'wem_get_invitado_data',
            'wem_current_user_can_manage',
            'wem_get_current_operator',
            'wem_sanitize_search_query',
        ] as $helper) {
            self::assertStringContainsString('function ' . $helper . '(', $source);
        }

        foreach ([
            'wem_nombre',
            'wem_organizacion',
            'wem_mesa',
            'wem_observaciones',
            'wem_checkin',
            'wem_checkin_at',
            'wem_checkin_by',
            'wem_checkout',
            'wem_checkout_at',
            'wem_checkout_by',
        ] as $key) {
            self::assertStringContainsString("'" . $key . "'", $source);
        }
    }
}
