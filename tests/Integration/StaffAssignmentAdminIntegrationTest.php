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
    public function testAdminProfileRendersCanonicalPresetControlAndSurfacesNonCanonicalState(): void
    {
        require_once dirname(__DIR__, 2) . '/wordpress-event-manager.php';

        $adminId = self::factory()->user->create(['role' => 'administrator']);
        $targetId = self::factory()->user->create(['role' => 'subscriber']);
        $target = get_user_by('id', $targetId);

        self::assertInstanceOf(\WP_User::class, $target);
        $target->add_cap('wem_operate_event_guests');

        wp_set_current_user($adminId);

        $manager = new \WEM_Staff_Assignment_Manager();

        ob_start();
        $manager->render_event_scope_fields($target);
        $html = (string) ob_get_clean();

        self::assertStringContainsString('name="wem_staff_provisioning_preset"', $html);
        self::assertStringContainsString('value="none"', $html);
        self::assertStringContainsString('value="viewer"', $html);
        self::assertStringContainsString('value="operator"', $html);
        self::assertStringContainsString('Non-canonical', $html);

        self::assertSame(
            \WEM_Staff_Provisioning::STATE_NON_CANONICAL,
            \WEM_Staff_Provisioning::get_state($targetId),
            'Rendering must not normalize an externally-created non-canonical state.'
        );
    }

    public function testAdminCanApplyExplicitPresetWithoutReplacingWordPressRoleOrOtherCapabilities(): void
    {
        require_once dirname(__DIR__, 2) . '/wordpress-event-manager.php';

        $adminId = self::factory()->user->create(['role' => 'administrator']);
        $targetId = self::factory()->user->create(['role' => 'editor']);
        $target = get_user_by('id', $targetId);

        self::assertInstanceOf(\WP_User::class, $target);
        $initialRoles = $target->roles;
        $target->add_cap('wem_profile_unrelated_capability');

        wp_set_current_user($adminId);

        $_POST = [
            'wem_staff_event_scope_nonce' => wp_create_nonce('wem_save_staff_event_scope'),
            'wem_staff_provisioning_preset' => \WEM_Staff_Provisioning::STATE_OPERATOR,
            'wem_authorized_event_ids' => [],
        ];

        $manager = new \WEM_Staff_Assignment_Manager();
        $manager->save_event_scope_fields($targetId);

        self::assertSame(
            \WEM_Staff_Provisioning::STATE_OPERATOR,
            \WEM_Staff_Provisioning::get_state($targetId)
        );

        $after = get_user_by('id', $targetId);
        self::assertInstanceOf(\WP_User::class, $after);
        self::assertSame($initialRoles, $after->roles);
        self::assertTrue($after->has_cap('wem_profile_unrelated_capability'));
    }

    public function testNonCanonicalStateIsPreservedUnlessAdminExplicitlyChoosesCanonicalPreset(): void
    {
        require_once dirname(__DIR__, 2) . '/wordpress-event-manager.php';

        $adminId = self::factory()->user->create(['role' => 'administrator']);
        $targetId = self::factory()->user->create(['role' => 'subscriber']);
        $target = get_user_by('id', $targetId);

        self::assertInstanceOf(\WP_User::class, $target);
        $target->add_cap('wem_operate_event_guests');

        wp_set_current_user($adminId);

        $_POST = [
            'wem_staff_event_scope_nonce' => wp_create_nonce('wem_save_staff_event_scope'),
            'wem_staff_provisioning_preset' => '',
            'wem_authorized_event_ids' => [],
        ];

        $manager = new \WEM_Staff_Assignment_Manager();
        $manager->save_event_scope_fields($targetId);

        self::assertSame(
            \WEM_Staff_Provisioning::STATE_NON_CANONICAL,
            \WEM_Staff_Provisioning::get_state($targetId)
        );
    }

    public function testNonAdminCannotApplyProvisioningPreset(): void
    {
        require_once dirname(__DIR__, 2) . '/wordpress-event-manager.php';

        $editorId = self::factory()->user->create(['role' => 'editor']);
        $targetId = self::factory()->user->create(['role' => 'subscriber']);

        wp_set_current_user($editorId);

        $_POST = [
            'wem_staff_event_scope_nonce' => wp_create_nonce('wem_save_staff_event_scope'),
            'wem_staff_provisioning_preset' => \WEM_Staff_Provisioning::STATE_OPERATOR,
        ];

        $manager = new \WEM_Staff_Assignment_Manager();
        $manager->save_event_scope_fields($targetId);

        self::assertSame(
            \WEM_Staff_Provisioning::STATE_NONE,
            \WEM_Staff_Provisioning::get_state($targetId)
        );
    }
}
