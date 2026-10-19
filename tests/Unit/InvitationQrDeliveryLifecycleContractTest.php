<?php
declare(strict_types=1);

namespace {
    if (!defined('ABSPATH')) define('ABSPATH', '/wordpress/');
    if (!function_exists('user_can')) {
        function user_can($id, $cap) { return (int)$id === 71 && $cap === 'manage_options'; }
    }
    if (!function_exists('home_url')) {
        function home_url($path = '/') { return 'https://example.test' . $path; }
    }
    if (!function_exists('add_query_arg')) {
        function add_query_arg($key, $value, $url) {
            return $url . '?' . rawurlencode($key) . '=' . rawurlencode($value);
        }
    }
}

namespace WEM\Tests\Unit {
    use PHPUnit\Framework\TestCase;
    require_once dirname(__DIR__, 2) . '/includes/class-admin-invitation-qr-orchestrator.php';

    final class W67CredentialFake {
        public $calls = [];
        public $token;
        public function __construct() { $this->token = str_repeat('Q', 43); }
        public function issue($guest, $actor) { return $this->run('issue', $guest, $actor); }
        public function rotate($guest, $actor) { return $this->run('rotate', $guest, $actor); }
        public function reissue($guest, $actor) { return $this->run('reissue', $guest, $actor); }
        private function run($operation, $guest, $actor) {
            $this->calls[] = [$operation, $guest, $actor];
            return ['token' => $this->token, 'generation' => $operation === 'issue' ? 1 : 2];
        }
    }
    final class W67EncoderFake {
        public $urls = [];
        public $fail = false;
        public function render_svg(string $url): string {
            $this->urls[] = $url;
            if ($this->fail) throw new \RuntimeException('sensitive diagnostic');
            return '<svg xmlns="http://www.w3.org/2000/svg"></svg>';
        }
    }
    final class InvitationQrDeliveryLifecycleContractTest extends TestCase {
        public function testIssueRotateReissueEncodeOnlyFreshBearer(): void {
            foreach (['issue', 'rotate', 'reissue'] as $operation) {
                $service = new W67CredentialFake();
                $encoder = new W67EncoderFake();
                $orchestrator = new \WEM_Admin_Invitation_QR_Orchestrator($service, $encoder);
                $result = $orchestrator->{$operation}(42, 71);
                self::assertSame([[$operation, 42, 71]], $service->calls);
                self::assertSame('https://example.test/?wem_invitation=' . str_repeat('Q', 43), $result['url']);
                self::assertSame([$result['url']], $encoder->urls);
                self::assertStringContainsString('<svg', $result['svg']);
                self::assertSame($operation === 'issue' ? 1 : 2, $result['generation']);
                self::assertSame('private, no-store', $result['headers']['Cache-Control']);
                self::assertSame('no-referrer', $result['headers']['Referrer-Policy']);
            }
        }
        public function testUnauthorizedActorDoesNotCallServices(): void {
            $service = new W67CredentialFake();
            $encoder = new W67EncoderFake();
            $orchestrator = new \WEM_Admin_Invitation_QR_Orchestrator($service, $encoder);
            try {
                $orchestrator->issue(42, 72);
                self::fail('Expected denial');
            } catch (\RuntimeException $error) {
                self::assertSame('Invitation QR administration is not authorized.', $error->getMessage());
            }
            self::assertSame([], $service->calls);
            self::assertSame([], $encoder->urls);
        }
        public function testPostCommitSvgErrorDoesNotCompensateOrLeak(): void {
            $service = new W67CredentialFake();
            $encoder = new W67EncoderFake();
            $encoder->fail = true;
            $orchestrator = new \WEM_Admin_Invitation_QR_Orchestrator($service, $encoder);
            try {
                $orchestrator->rotate(42, 71);
                self::fail('Expected render error');
            } catch (\RuntimeException $error) {
                self::assertSame('Invitation credential committed, but QR presentation failed.', $error->getMessage());
                self::assertStringNotContainsString($service->token, $error->getMessage());
                self::assertStringNotContainsString('sensitive diagnostic', $error->getMessage());
            }
            self::assertSame([['rotate', 42, 71]], $service->calls);
            self::assertCount(1, $encoder->urls);
        }
        public function testInvalidTokenCannotBeRenderedOrRetried(): void {
            $service = new W67CredentialFake();
            $service->token = 'bad';
            $encoder = new W67EncoderFake();
            $orchestrator = new \WEM_Admin_Invitation_QR_Orchestrator($service, $encoder);
            try {
                $orchestrator->issue(42, 71);
                self::fail('Expected invalid token error');
            } catch (\RuntimeException $error) {
                self::assertSame('Credential issuance did not provide a valid bearer.', $error->getMessage());
            }
            self::assertCount(1, $service->calls);
            self::assertSame([], $encoder->urls);
        }
    }
}
