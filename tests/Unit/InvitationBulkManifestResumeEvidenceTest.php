<?php

declare(strict_types=1);

namespace WEM\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class InvitationBulkManifestResumeEvidenceTest extends TestCase
{
    public function testAllowsNotStartedOnlyForManifestMemberWithVerifiedAbsences(): void
    {
        self::assertTrue(class_exists('WEM_Invitation_Bulk_Manifest_Resume_Evidence'));
        $reader = new \WEM_Invitation_Bulk_Manifest_Resume_Evidence();
        $manifest = [
            'operation_id' => 'run-1',
            'event_id' => 7,
            'guest_ids' => [101, 102],
        ];
        self::assertSame('not_started', $reader->classify(
            $manifest, 'run-1', 101, false, null, 0
        ));
        self::assertSame('indeterminate', $reader->classify(
            $manifest, 'run-2', 101, false, null, 0
        ));
        self::assertSame('indeterminate', $reader->classify(
            $manifest, 'run-1', 103, false, null, 0
        ));
        self::assertSame('indeterminate', $reader->classify(
            null, 'run-1', 101, false, null, 0
        ));
        self::assertSame('indeterminate', $reader->classify(
            $manifest, 'run-1', 101, null, null, 0
        ));
        self::assertSame('indeterminate', $reader->classify(
            $manifest, 'run-1', 101, false, null, null
        ));
    }

    public function testExistingAttemptNeverBecomesNotStarted(): void
    {
        $reader = new \WEM_Invitation_Bulk_Manifest_Resume_Evidence();
        $manifest = [
            'operation_id' => 'run-1',
            'event_id' => 7,
            'guest_ids' => [101],
        ];
        self::assertSame('indeterminate', $reader->classify(
            $manifest, 'run-1', 101, true, null, 0
        ));
        self::assertSame('reconcile', $reader->classify(
            $manifest, 'run-1', 101, true, ['guest_id' => 101], 1
        ));
    }
}
