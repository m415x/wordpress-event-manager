<?php

declare(strict_types=1);

namespace WEM\Tests\Unit;

use PHPUnit\Framework\TestCase;

/** WEM-66: each explicitly selected guest is rechecked before QR issuance. */
final class AdminInvitationQrBulkProcessorTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        if (!defined('ABSPATH')) {
            define('ABSPATH', '/tmp/wem66-bulk-processor/');
        }
        require_once dirname(__DIR__, 2) . '/includes/class-admin-invitation-qr-bulk-selection.php';
        require_once dirname(__DIR__, 2) . '/includes/class-admin-invitation-qr-bulk-processor.php';
    }

    public function testEachSelectedGuestIsCheckedImmediatelyBeforeDelegating(): void
    {
        $events = array();
        $revalidator = new class($events) {
            private $events;
            public function __construct(&$events) { $this->events = &$events; }
            public function permits($id, $operation) {
                $this->events[] = 'check:' . $id . ':' . $operation;
                return $id !== 12;
            }
        };
        $orchestrator = new class($events) {
            private $events;
            public function __construct(&$events) { $this->events = &$events; }
            public function issue($id, $actor_id) {
                $this->events[] = 'issue:' . $id . ':' . $actor_id;
                return array('url' => 'https://example.test/?wem_invitation=' . str_repeat('A', 43), 'svg' => '<svg/>', 'generation' => 1);
            }
        };
        $processor = new \WEM_Admin_Invitation_QR_Bulk_Processor($revalidator, $orchestrator);
        $results = $processor->execute(array(11, 12, 13), 'issue', 42);

        self::assertSame(
            array('check:11:issue', 'issue:11:42', 'check:12:issue', 'check:13:issue', 'issue:13:42'),
            $events
        );
        self::assertSame(array('issued', 'denied', 'issued'), array_column($results, 'status'));
        self::assertArrayNotHasKey('url', $results[1]);
        self::assertArrayNotHasKey('svg', $results[1]);
    }

    public function testInvalidSelectionMustNotReadOrIssue(): void
    {
        $calls = 0;
        $validator = new class($calls) {
            private $calls;
            public function __construct(&$calls) { $this->calls = &$calls; }
            public function permits($id, $operation) { ++$this->calls; return true; }
        };
        $writer = new class($calls) {
            private $calls;
            public function __construct(&$calls) { $this->calls = &$calls; }
            public function issue($id, $actor) { ++$this->calls; return array(); }
        };
        $processor = new \WEM_Admin_Invitation_QR_Bulk_Processor($validator, $writer);
        $this->expectException(\InvalidArgumentException::class);
        try {
            $processor->execute(array(11, 11), 'issue', 42);
        } finally {
            self::assertSame(0, $calls);
        }
    }

    public function testPresentationFailureDoesNotRetryOrRotate(): void
    {
        $calls = 0;
        $validator = new class {
            public function permits($id, $operation) { return true; }
        };
        $writer = new class($calls) {
            private $calls;
            public function __construct(&$calls) { $this->calls = &$calls; }
            public function reissue($id, $actor) {
                ++$this->calls;
                throw new \RuntimeException('sensitive bearer from presentation error');
            }
        };
        $results = (new \WEM_Admin_Invitation_QR_Bulk_Processor($validator, $writer))
            ->execute(array(15), 'reissue', 42);
        self::assertSame(1, $calls);
        self::assertSame('unavailable', $results[0]['status']);
        self::assertArrayNotHasKey('url', $results[0]);
        self::assertArrayNotHasKey('svg', $results[0]);
    }
}
