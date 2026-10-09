<?php

declare(strict_types=1);

namespace WEM\Tests\Unit;

use PHPUnit\Framework\TestCase;

/** WEM-34: installed WordPress plugin must load Composer runtime itself. */
final class LocalQrPluginAutoloadTest extends TestCase
{
    public function testPluginBootstrapLoadsPackagedComposerAutoload(): void
    {
        $bootstrap = (string) file_get_contents(dirname(__DIR__, 2) . '/wordpress-event-manager.php');

        self::assertMatchesRegularExpression(
            '/require_once\s+WEM_PATH\s*\.\s*[\'\"]vendor\/autoload\.php[\'\"]\s*;/',
            $bootstrap,
            'Installed WEM must load packaged Composer autoload without Composer CLI or test bootstrap.'
        );
    }
}
