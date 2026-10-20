<?php

declare(strict_types=1);

namespace WEM\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RuntimeException;

final class InvitationBulkPersistentResumeEvidenceTest extends TestCase
{
    public function testNoCustodyAndUnknownDatabaseStateIsIndeterminate(): void
    {
        require_once dirname(__DIR__, 2) . '/includes/class-invitation-bulk-persistent-resume-evidence.php';
        self::assertTrue(class_exists('WEM_Invitation_Bulk_Persistent_Resume_Evidence'));
        $resolver = new \WEM_Invitation_Bulk_Persistent_Resume_Evidence();
        self::assertSame('indeterminate', $resolver->classify(null, null));
        self::assertSame('indeterminate', $resolver->classify(null, ['credential_count' => null]));
        self::assertSame('indeterminate', $resolver->classify(null, ['credential_count' => 1]));
    }

    public function testNotStartedRequiresNoCredentialAndIndependentAttemptProof(): void
    {
        $resolver = new \WEM_Invitation_Bulk_Persistent_Resume_Evidence();
        self::assertSame('indeterminate', $resolver->classify(null, ['credential_count' => 0]));
        self::assertSame('indeterminate', $resolver->classify(null, [
            'credential_count' => 0,
            'attempt_verified' => false,
        ]));
        self::assertSame('not_started', $resolver->classify(null, [
            'credential_count' => 0,
            'attempt_verified' => true,
            'attempted' => false,
        ]));
        self::assertSame('indeterminate', $resolver->classify(null, [
            'credential_count' => 0,
            'attempt_verified' => true,
            'attempted' => true,
        ]));
    }

    public function testCustodiedIsNotAnExportAuthorization(): void
    {
        $resolver = new \WEM_Invitation_Bulk_Persistent_Resume_Evidence();
        self::assertSame('custodied', $resolver->classify(['guest_id' => 101], [
            'credential_count' => 1,
        ]));
        $this->expectException(RuntimeException::class);
        $resolver->require_not_started(['guest_id' => 101], ['credential_count' => 1]);
    }
}
