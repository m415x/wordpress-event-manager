<?php

declare(strict_types=1);

namespace WEM\Tests\Integration;

use RuntimeException;
use WP_UnitTestCase;

/**
 * WEM-73: exercise the actual WordPress permissions and event taxonomy.
 * No credentials are emitted and no journal or private CSV is created.
 */
final class InvitationBulkWordPressScopeTest extends WP_UnitTestCase
{
    public function testWordPressAdministrativeGuardDeniesUnauthorizedAndCrossEventGuests(): void
    {
        require_once dirname(__DIR__, 2) . '/wordpress-event-manager.php';
        (new \WEM_Taxonomy_Manager())->register_taxonomy();

        $admin = self::factory()->user->create(['role' => 'administrator']);
        $subscriber = self::factory()->user->create(['role' => 'subscriber']);
        $eventA = self::factory()->term->create(['taxonomy' => 'evento', 'name' => 'WEM73-A']);
        $eventB = self::factory()->term->create(['taxonomy' => 'evento', 'name' => 'WEM73-B']);
        $guestA = self::factory()->post->create(['post_type' => 'invitado', 'post_status' => 'publish']);
        $guestB = self::factory()->post->create(['post_type' => 'invitado', 'post_status' => 'publish']);
        wp_set_object_terms($guestA, [$eventA], 'evento');
        wp_set_object_terms($guestB, [$eventB], 'evento');

        self::assertTrue(class_exists('WEM_Invitation_Bulk_WordPress_Scope'));
        $guard = new \WEM_Invitation_Bulk_WordPress_Scope();
        $calls = 0;
        $issue = static function () use (&$calls): void {
            $calls++;
        };

        foreach ([[$subscriber, $guestA, $eventA], [$admin, $guestB, $eventA]] as $case) {
            try {
                $guard->run($case[0], $case[1], $case[2], $issue);
                self::fail('Out-of-scope issuance must be denied.');
            } catch (RuntimeException $error) {
                self::assertNotSame('', $error->getMessage());
            }
        }
        self::assertSame(0, $calls);
        $guard->run($admin, $guestA, $eventA, $issue);
        self::assertSame(1, $calls);
    }
}
