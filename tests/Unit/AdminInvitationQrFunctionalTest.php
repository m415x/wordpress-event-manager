<?php

declare(strict_types=1);

namespace {
    if (!defined('ABSPATH')) {
        define('ABSPATH', '/tmp/wem34-unit/');
    }
    if (!function_exists('user_can')) {
        function user_can($user, $capability): bool
        {
            return (int) $user === 42 && $capability === 'manage_options';
        }
    }
    if (!function_exists('home_url')) {
        function home_url($path = ''): string
        {
            return 'https://example.test' . $path;
        }
    }
    if (!function_exists('add_query_arg')) {
        function add_query_arg($key, $value, $url): string
        {
            return $url . '?' . rawurlencode($key) . '=' . rawurlencode($value);
        }
    }
}

namespace WEM\Tests\Unit {
    use PHPUnit\Framework\TestCase;

    final class AdminInvitationQrFunctionalTest extends TestCase
    {
        public function testEncoderFailureAfterCommittedIssueIsProtectedAndNotCompensated(): void
        {
            require_once dirname(__DIR__, 2) . '/includes/class-admin-invitation-qr-orchestrator.php';

            $token = str_repeat('Z', 43);
            $service = new class($token) {
                public $operations = array();
                private $token;

                public function __construct(string $token)
                {
                    $this->token = $token;
                }

                public function issue(int $guest, int $actor): array
                {
                    $this->operations[] = 'issue';
                    return array('token' => $this->token, 'generation' => 1);
                }

                public function revoke(): void
                {
                    $this->operations[] = 'revoke';
                }

                public function rotate(): void
                {
                    $this->operations[] = 'rotate';
                }
            };

            $encoder = new class($token) {
                private $token;
                public function __construct(string $token)
                {
                    $this->token = $token;
                }
                public function render_svg(string $url): string
                {
                    throw new \RuntimeException('SVG failure exposed ' . $this->token);
                }
            };

            $subject = new \WEM_Admin_Invitation_QR_Orchestrator($service, $encoder);
            try {
                $subject->issue(17, 42);
                self::fail('Expected protected QR failure.');
            } catch (\RuntimeException $error) {
                self::assertSame(
                    'Invitation credential committed, but QR presentation failed.',
                    $error->getMessage(),
                    'Never leak a freshly issued bearer through a rendering exception.'
                );
            }
            self::assertSame(array('issue'), $service->operations);
        }
    }
}
