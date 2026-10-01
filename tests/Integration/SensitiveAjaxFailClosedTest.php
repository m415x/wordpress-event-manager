<?php

declare(strict_types=1);

namespace WEM\Tests\Integration;

use WP_Ajax_UnitTestCase;

final class SensitiveAjaxFailClosedTest extends WP_Ajax_UnitTestCase
{
    public function testGuestAjaxRoutesRegisterOnceForBothAuthenticationContexts(): void
    {
        require_once dirname(__DIR__, 2) . '/wordpress-event-manager.php';

        // WordPress' test bootstrap fired plugins_loaded before the test.
        do_action('plugins_loaded');

        foreach (['wem_checkin_ajax', 'wem_list_ajax'] as $action) {
            foreach (['wp_ajax_', 'wp_ajax_nopriv_'] as $prefix) {
                self::assertSame(
                    1,
                    $this->countHandlerCallbacks($prefix . $action),
                    $prefix . $action . ' must register one WEM handler'
                );
            }
        }
    }

    /**
     * @dataProvider guestActions
     */
    public function testGuestAjaxRejectsBeforeAnyGuestReadOrWrite(string $action, bool $authenticated): void
    {
        require_once dirname(__DIR__, 2) . '/wordpress-event-manager.php';
        do_action('plugins_loaded');

        if ($authenticated) {
            wp_set_current_user(self::factory()->user->create(['role' => 'administrator']));
        } else {
            wp_set_current_user(0);
        }

        $postId = self::factory()->post->create(['post_type' => 'post']);
        update_post_meta($postId, 'wem_checkin', 0);
        update_post_meta($postId, 'wem_nombre', 'private-guest-marker');

        $_POST = [
            'action' => $action,
            'post_id' => $postId,
            'check_action' => 'checkin',
            'q' => 'private-guest-marker',
            'nonce' => wp_create_nonce('wem_checkin_nonce'),
        ];
        $_REQUEST = $_POST;

        // PHP CLI does not reliably expose WordPress' emitted status via
        // http_response_code(). Observe the status_header API instead.
        $statusCodes = [];
        $captureStatus = static function ($header, $code) use (&$statusCodes) {
            $statusCodes[] = (int) $code;
            return $header;
        };
        add_filter('status_header', $captureStatus, 10, 2);

        try {
            $this->_handleAjax($action);
        } finally {
            remove_filter('status_header', $captureStatus, 10);
        }

        self::assertContains(403, $statusCodes, 'WordPress must emit HTTP 403');
        self::assertSame(
            ['success' => false, 'data' => ['code' => 'guest_access_unavailable']],
            json_decode($this->_last_response, true)
        );
        self::assertSame('0', (string) get_post_meta($postId, 'wem_checkin', true));
        self::assertSame('private-guest-marker', get_post_meta($postId, 'wem_nombre', true));
        self::assertStringNotContainsString('private-guest-marker', $this->_last_response);
    }

    public static function guestActions(): array
    {
        return [
            'anonymous check-in' => ['wem_checkin_ajax', false],
            'authenticated check-in' => ['wem_checkin_ajax', true],
            'anonymous guest listing' => ['wem_list_ajax', false],
            'authenticated guest listing' => ['wem_list_ajax', true],
        ];
    }

    private function countHandlerCallbacks(string $hook): int
    {
        global $wp_filter;

        if (!isset($wp_filter[$hook])) {
            return 0;
        }

        $count = 0;
        foreach ($wp_filter[$hook]->callbacks as $priorityCallbacks) {
            foreach ($priorityCallbacks as $entry) {
                $handler = $entry['function'];
                if (
                    is_array($handler)
                    && isset($handler[0], $handler[1])
                    && $handler[0] instanceof \WEM_Ajax_Handler
                    && in_array($handler[1], ['handle_checkin_ajax', 'handle_list_ajax'], true)
                ) {
                    $count++;
                }
            }
        }

        return $count;
    }
}
