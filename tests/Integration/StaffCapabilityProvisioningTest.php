<?php

declare(strict_types=1);

namespace WEM\Tests\Integration;

use WP_UnitTestCase;

final class StaffCapabilityProvisioningTest extends WP_UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        require_once dirname(__DIR__, 2) . '/wordpress-event-manager.php';
    }

    public function testDetectsCanonicalProvisioningStates(): void
    {
        $noneId = self::factory()->user->create(['role' => 'subscriber']);
        $viewerId = self::factory()->user->create(['role' => 'subscriber']);
        $operatorId = self::factory()->user->create(['role' => 'editor']);

        $viewer = get_user_by('id', $viewerId);
        $operator = get_user_by('id', $operatorId);

        self::assertInstanceOf(\WP_User::class, $viewer);
        self::assertInstanceOf(\WP_User::class, $operator);

        $viewer->add_cap('wem_view_event_guests');
        $operator->add_cap('wem_view_event_guests');
        $operator->add_cap('wem_operate_event_guests');

        self::assertSame(
            \WEM_Staff_Provisioning::STATE_NONE,
            \WEM_Staff_Provisioning::get_state($noneId)
        );
        self::assertSame(
            \WEM_Staff_Provisioning::STATE_VIEWER,
            \WEM_Staff_Provisioning::get_state($viewerId)
        );
        self::assertSame(
            \WEM_Staff_Provisioning::STATE_OPERATOR,
            \WEM_Staff_Provisioning::get_state($operatorId)
        );
    }

    public function testDetectsOperateOnlyAsNonCanonicalWithoutNormalizingIt(): void
    {
        $userId = self::factory()->user->create(['role' => 'subscriber']);
        $user = get_user_by('id', $userId);
        self::assertInstanceOf(\WP_User::class, $user);

        $user->add_cap('wem_operate_event_guests');

        self::assertFalse($user->has_cap('wem_view_event_guests'));
        self::assertTrue($user->has_cap('wem_operate_event_guests'));

        self::assertSame(
            \WEM_Staff_Provisioning::STATE_NON_CANONICAL,
            \WEM_Staff_Provisioning::get_state($userId)
        );

        $after = get_user_by('id', $userId);
        self::assertInstanceOf(\WP_User::class, $after);
        self::assertFalse($after->has_cap('wem_view_event_guests'));
        self::assertTrue($after->has_cap('wem_operate_event_guests'));
    }

    public function testExplicitPresetsConvergeOnlyWemCapabilitiesAndAreIdempotent(): void
    {
        $userId = self::factory()->user->create(['role' => 'editor']);
        $user = get_user_by('id', $userId);
        self::assertInstanceOf(\WP_User::class, $user);

        $initialRoles = $user->roles;
        $user->add_cap('wem_operate_event_guests');
        $user->add_cap('wem_unrelated_test_capability');

        self::assertSame(
            \WEM_Staff_Provisioning::STATE_NON_CANONICAL,
            \WEM_Staff_Provisioning::get_state($userId)
        );

        self::assertTrue(
            \WEM_Staff_Provisioning::apply_preset(
                $userId,
                \WEM_Staff_Provisioning::STATE_VIEWER
            )
        );
        self::assertSame(
            \WEM_Staff_Provisioning::STATE_VIEWER,
            \WEM_Staff_Provisioning::get_state($userId)
        );

        $viewer = get_user_by('id', $userId);
        self::assertInstanceOf(\WP_User::class, $viewer);
        self::assertSame($initialRoles, $viewer->roles);
        self::assertTrue($viewer->has_cap('wem_unrelated_test_capability'));

        self::assertTrue(
            \WEM_Staff_Provisioning::apply_preset(
                $userId,
                \WEM_Staff_Provisioning::STATE_OPERATOR
            )
        );
        self::assertTrue(
            \WEM_Staff_Provisioning::apply_preset(
                $userId,
                \WEM_Staff_Provisioning::STATE_OPERATOR
            )
        );
        self::assertSame(
            \WEM_Staff_Provisioning::STATE_OPERATOR,
            \WEM_Staff_Provisioning::get_state($userId)
        );

        self::assertTrue(
            \WEM_Staff_Provisioning::apply_preset(
                $userId,
                \WEM_Staff_Provisioning::STATE_NONE
            )
        );
        self::assertSame(
            \WEM_Staff_Provisioning::STATE_NONE,
            \WEM_Staff_Provisioning::get_state($userId)
        );

        $revoked = get_user_by('id', $userId);
        self::assertInstanceOf(\WP_User::class, $revoked);
        self::assertSame($initialRoles, $revoked->roles);
        self::assertTrue($revoked->has_cap('wem_unrelated_test_capability'));
    }

    public function testInvalidPresetAndDeletedUserAreRejectedWithoutMutation(): void
    {
        $userId = self::factory()->user->create(['role' => 'subscriber']);
        $user = get_user_by('id', $userId);
        self::assertInstanceOf(\WP_User::class, $user);
        $user->add_cap('wem_operate_event_guests');

        self::assertFalse(
            \WEM_Staff_Provisioning::apply_preset(
                $userId,
                \WEM_Staff_Provisioning::STATE_NON_CANONICAL
            )
        );
        self::assertSame(
            \WEM_Staff_Provisioning::STATE_NON_CANONICAL,
            \WEM_Staff_Provisioning::get_state($userId)
        );

        wp_delete_user($userId);

        self::assertFalse(
            \WEM_Staff_Provisioning::apply_preset(
                $userId,
                \WEM_Staff_Provisioning::STATE_VIEWER
            )
        );
    }

    public function testDeletedOrUnknownUserHasNoProvisioningState(): void
    {
        $userId = self::factory()->user->create(['role' => 'subscriber']);
        wp_delete_user($userId);

        self::assertNull(\WEM_Staff_Provisioning::get_state($userId));
        self::assertNull(\WEM_Staff_Provisioning::get_state(999999999));
    }
}
