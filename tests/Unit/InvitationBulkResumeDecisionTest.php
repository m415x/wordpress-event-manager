<?php

declare(strict_types=1);

namespace WEM\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class InvitationBulkResumeDecisionTest extends TestCase
{
    public function testAttemptedWithoutRecoverableCustodyNeverAllowsIssuance(): void
    {
        self::assertTrue(class_exists('WEM_Invitation_Bulk_Resume_Decision'));
        $decision = new \WEM_Invitation_Bulk_Resume_Decision();
        self::assertSame('indeterminate', $decision->decide(true, null, 0));
        self::assertSame('indeterminate', $decision->decide(true, null, 1));
        self::assertSame('indeterminate', $decision->decide(false, null, 1));
        self::assertSame('indeterminate', $decision->decide(false, null, null));
    }

    public function testRecoverableCustodyStillRequiresDatabaseReconciliation(): void
    {
        $decision = new \WEM_Invitation_Bulk_Resume_Decision();
        self::assertSame('reconcile', $decision->decide(true, ['guest_id' => 101], 1));
        self::assertSame('indeterminate', $decision->decide(true, ['guest_id' => 101], 0));
        self::assertSame('indeterminate', $decision->decide(false, ['guest_id' => 101], 1));
    }

    public function testAbsentAttemptAndCredentialAreNotSufficientWithoutBatchEvidence(): void
    {
        $decision = new \WEM_Invitation_Bulk_Resume_Decision();
        self::assertSame('indeterminate', $decision->decide(false, null, 0));
        self::assertSame('not_started', $decision->decide(false, null, 0, true));
    }
}
