<?php

declare(strict_types=1);

namespace WEM\Tests\Integration;

use WP_UnitTestCase;

final class SeatingSchemaInstallationTest extends WP_UnitTestCase
{
    public function testEntrypointRegistersBothSeatingInstallers(): void
    {
        $entrypoint = dirname(__DIR__, 2) . '/wordpress-event-manager.php';
        require_once $entrypoint;
        $source = file_get_contents($entrypoint);
        self::assertIsString($source);

        foreach (['WEM_Event_Table_Schema', 'WEM_Table_Reservation_Schema'] as $schema) {
            self::assertMatchesRegularExpression(
                '/register_activation_hook\\s*\\(\\s*__FILE__\\s*,\\s*array\\s*\\(\\s*[\'"]'
                    . preg_quote($schema, '/')
                    . '[\'"]\\s*,\\s*[\'"]install[\'"]\\s*\\)\\s*\\)/',
                $source,
                $schema . ' must be declared as a plugin activation installer.'
            );
            self::assertTrue(is_callable([$schema, 'install']), $schema . ' installer must be callable.');
        }
    }
}
