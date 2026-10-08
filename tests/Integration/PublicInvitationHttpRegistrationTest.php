<?php

declare(strict_types=1);

namespace WEM\Tests\Integration;

use WP_UnitTestCase;

final class PublicInvitationHttpRegistrationTest extends WP_UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        require_once dirname(__DIR__, 2) . '/wordpress-event-manager.php';
        \WEM_Invitation_Credential_Schema::install();

        $taxonomy = new \WEM_Taxonomy_Manager();
        $taxonomy->register_taxonomy();

        global $wpdb;
        $tableName = \WEM_Invitation_Credential_Schema::table_name();
        $wpdb->query("DELETE FROM {$tableName}");

        $_GET = [];
    }

    protected function tearDown(): void
    {
        $_GET = [];
        parent::tearDown();
    }

    public function testRouteRegistersQueryVarAndTemplateRedirectWithoutRewriteDependency(): void
    {
        update_option('permalink_structure', '');

        $route = new \WEM_Public_Invitation_Route();
        $route->register_hooks();

        $queryVars = apply_filters('query_vars', []);

        self::assertContains('wem_invitation', $queryVars);
        self::assertNotFalse(
            has_action('template_redirect', [$route, 'dispatch_request'])
        );
    }

    public function testTemplateRedirectDispatchRendersPublicViewForValidBearer(): void
    {
        update_option('permalink_structure', '');

        $adminId = self::factory()->user->create(['role' => 'administrator']);
        $guestId = self::factory()->post->create([
            'post_type' => 'invitado',
            'post_status' => 'publish',
            'post_title' => 'SECRET-DISPATCH-TICKET',
        ]);
        update_post_meta($guestId, 'wem_nombre', 'Invitado Dispatch');

        $eventId = self::factory()->term->create([
            'taxonomy' => 'evento',
            'name' => 'Evento Dispatch',
        ]);
        wp_set_object_terms($guestId, [$eventId], 'evento', false);

        $service = new \WEM_Invitation_Credential_Service();
        $issued = $service->issue($guestId, $adminId);

        $_GET = [
            'wem_invitation' => $issued['token'],
        ];

        $route = new \WEM_Public_Invitation_Route(
            static function (): void {
            }
        );

        ob_start();
        $handled = $route->dispatch_request();
        $output = ob_get_clean();

        self::assertTrue($handled);
        self::assertIsString($output);
        self::assertStringContainsString('Invitado Dispatch', $output);
        self::assertStringContainsString('Evento Dispatch', $output);
        self::assertStringNotContainsString('SECRET-DISPATCH-TICKET', $output);
    }

    public function testTemplateRedirectDispatchIgnoresUnrelatedRequests(): void
    {
        $_GET = [];

        $route = new \WEM_Public_Invitation_Route(
            static function (): void {
            }
        );

        ob_start();
        $handled = $route->dispatch_request();
        $output = ob_get_clean();

        self::assertFalse($handled);
        self::assertSame('', $output);
    }

    public function testTemplateRedirectHandlerResolvesBearerFromSimpleQueryString(): void
    {
        update_option('permalink_structure', '');

        $adminId = self::factory()->user->create(['role' => 'administrator']);
        $guestId = self::factory()->post->create([
            'post_type' => 'invitado',
            'post_status' => 'publish',
            'post_title' => 'WEM-59-HTTP-ROUTE',
        ]);
        $eventId = self::factory()->term->create([
            'taxonomy' => 'evento',
            'name' => 'HTTP Route Event',
        ]);
        wp_set_object_terms($guestId, [$eventId], 'evento', false);

        $service = new \WEM_Invitation_Credential_Service();
        $issued = $service->issue($guestId, $adminId);

        $_GET = [
            'wem_invitation' => $issued['token'],
        ];

        $route = new \WEM_Public_Invitation_Route(
            static function (): void {
            }
        );

        self::assertSame(
            [
                'guest_id' => $guestId,
                'event_term_id' => $eventId,
            ],
            $route->handle_request()
        );
    }

    public function testTemplateRedirectHandlerRejectsAdditionalLookupParameters(): void
    {
        update_option('permalink_structure', '');

        $adminId = self::factory()->user->create(['role' => 'administrator']);
        $guestId = self::factory()->post->create([
            'post_type' => 'invitado',
            'post_status' => 'publish',
            'post_title' => 'WEM-59-HTTP-STRICT',
        ]);
        $eventId = self::factory()->term->create([
            'taxonomy' => 'evento',
            'name' => 'HTTP Strict Event',
            'slug' => 'http-strict-event',
        ]);
        wp_set_object_terms($guestId, [$eventId], 'evento', false);

        $service = new \WEM_Invitation_Credential_Service();
        $issued = $service->issue($guestId, $adminId);

        $route = new \WEM_Public_Invitation_Route(
            static function (): void {
            }
        );

        $requests = [
            ['wem_invitation' => $issued['token'], 'ticket' => 'WEM-59-HTTP-STRICT'],
            ['wem_invitation' => $issued['token'], 'event' => 'http-strict-event'],
            ['wem_invitation' => $issued['token'], 'guest_id' => (string) $guestId],
            ['wem_invitation' => $issued['token'], 'unexpected' => '1'],
        ];

        foreach ($requests as $request) {
            $_GET = $request;
            self::assertNull($route->handle_request());
        }
    }

    public function testHttpHandlerRendersMinimalReadOnlyViewForValidBearer(): void
    {
        update_option('permalink_structure', '');

        $adminId = self::factory()->user->create(['role' => 'administrator']);
        $guestId = self::factory()->post->create([
            'post_type' => 'invitado',
            'post_status' => 'publish',
            'post_title' => 'SECRET-HTTP-TICKET',
        ]);
        update_post_meta($guestId, 'wem_nombre', 'Vista Pública');
        update_post_meta($guestId, 'wem_observaciones', 'Private note');

        $eventId = self::factory()->term->create([
            'taxonomy' => 'evento',
            'name' => 'Evento Visible',
        ]);
        wp_set_object_terms($guestId, [$eventId], 'evento', false);

        $service = new \WEM_Invitation_Credential_Service();
        $issued = $service->issue($guestId, $adminId);

        $_GET = [
            'wem_invitation' => $issued['token'],
        ];

        $route = new \WEM_Public_Invitation_Route(
            static function (): void {
            }
        );
        $output = $route->render_request();

        self::assertStringContainsString('Vista Pública', $output);
        self::assertStringContainsString('Evento Visible', $output);
        self::assertStringNotContainsString('SECRET-HTTP-TICKET', $output);
        self::assertStringNotContainsString('Private note', $output);
        self::assertStringNotContainsString('wem_observaciones', $output);
        self::assertStringNotContainsStringIgnoringCase('<form', $output);
        self::assertStringNotContainsStringIgnoringCase('<button', $output);
    }

    public function testHttpHandlerReturnsEmptyBodyForDeniedBearer(): void
    {
        $_GET = [
            'wem_invitation' => str_repeat('A', 43),
        ];

        $route = new \WEM_Public_Invitation_Route(
            static function (): void {
            }
        );

        self::assertSame('', $route->render_request());
    }
}
