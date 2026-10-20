<?php

declare(strict_types=1);

namespace WEM\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * WEM-73: an operation is bound to one immutable event and guest selection.
 * Resume may not silently replace the initial manifest.
 */
final class InvitationBulkManifestAdmissionTest extends TestCase
{
    public function testNewOperationPersistsManifestBeforeReturningEligibleGuests(): void
    {
        $events = [];
        $manifest = null;
        require_once dirname(__DIR__, 2) . '/includes/class-invitation-bulk-manifest-admission.php';
        self::assertTrue(class_exists('WEM_Invitation_Bulk_Manifest_Admission'));
        $admission = new \WEM_Invitation_Bulk_Manifest_Admission(
            static function (string $operation) use (&$manifest) { return $manifest; },
            static function (string $operation, int $event, array $ids) use (&$manifest, &$events): void {
                $events[] = 'manifest';
                $manifest = ['operation_id' => $operation, 'event_id' => $event, 'guest_ids' => $ids];
            }
        );
        $ids = $admission->admit('batch-1', 7, [101, 102]);
        self::assertSame([101, 102], $ids);
        self::assertSame(['manifest'], $events);
    }

    public function testResumeRejectsEventOrSelectionDriftWithoutRewritingManifest(): void
    {
        $writes = 0;
        $original = ['operation_id' => 'batch-1', 'event_id' => 7, 'guest_ids' => [101, 102]];
        $admission = new \WEM_Invitation_Bulk_Manifest_Admission(
            static fn (string $operation): array => $original,
            static function () use (&$writes): void { $writes++; }
        );
        self::assertSame([101, 102], $admission->admit('batch-1', 7, [101, 102]));

        foreach ([[8, [101, 102]], [7, [101, 103]]] as [$event, $ids]) {
            try {
                $admission->admit('batch-1', $event, $ids);
                self::fail('Manifest drift must be rejected.');
            } catch (RuntimeException $error) {
                self::assertNotSame('', $error->getMessage());
            }
        }
        self::assertSame(0, $writes);
    }
}
