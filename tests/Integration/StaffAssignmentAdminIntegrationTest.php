<?php

declare(strict_types=1);

namespace WEM\Tests\Integration;

use WP_UnitTestCase;

final class StaffAssignmentAdminIntegrationTest extends WP_UnitTestCase
{
    public function testPluginRegistersBoundedStaffAssignmentProfileManager(): void
    {
        require_once dirname(__DIR__, 2) . '/wordpress-event-manager.php';

        self::assertTrue(
            class_exists('WEM_Staff_Assignment_Manager'),
            'WEM-23 requires a bounded staff assignment manager.'
        );

        $manager = new \WEM_Staff_Assignment_Manager();
        $manager->register_hooks();

        self::assertSame(10, has_action('show_user_profile', [$manager, 'render_event_scope_fields']));
        self::assertSame(10, has_action('edit_user_profile', [$manager, 'render_event_scope_fields']));
        self::assertSame(10, has_action('personal_options_update', [$manager, 'save_event_scope_fields']));
        self::assertSame(10, has_action('edit_user_profile_update', [$manager, 'save_event_scope_fields']));
    }
}
