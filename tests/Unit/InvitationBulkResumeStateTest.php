<?php

declare(strict_types=1);

namespace WEM\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RuntimeException;

final class InvitationBulkResumeStateTest extends TestCase
{
    public function testMissingCustodyIsNotProofOfNeverStarted(): void
    {
        self::assertTrue(class_exists('WEM_Invitation_Bulk_Resume_State'));
        $resolver = new \WEM_Invitation_Bulk_Resume_State();

        self::assertSame('indeterminate', $resolver->classify(null, null));
        self::assertSame('indeterminate', $resolver->classify(null, ['attempted' => true]));
        self::assertSame('not_started', $resolver->classify(null, ['attempted' => false, 'verified' => true]));
        self::assertSame('custodied', $resolver->classify(
            ['guest_id' => 101, 'token' => str_repeat('A', 43)],
            ['attempted' => true]
        ));
    }

    public function testUnverifiedAbsenceMustNotAuthorizeIssuance(): void
    {
        $resolver = new \WEM_Invitation_Bulk_Resume_State();
        $this->expectException(RuntimeException::class);
        $resolver->require_not_started(null, null);
    }
}
