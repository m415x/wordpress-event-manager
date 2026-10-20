<?php

declare(strict_types=1);

namespace WEM\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * WEM-73: the composition root must not bypass authorization, guest locks,
 * persistent evidence, or reconciliation before publishing a CSV.
 */
final class InvitationBulkWorkflowCompositionTest extends TestCase
{
    public function testDeniedRequestNeverEntersIssuanceOrExport(): void
    {
        require_once dirname(__DIR__, 2) . '/includes/class-invitation-bulk-workflow.php';
        self::assertTrue(class_exists('WEM_Invitation_Bulk_Workflow'));
        $events = [];
        $workflow = new \WEM_Invitation_Bulk_Workflow(
            static function (array $request) use (&$events): array {
                $events[] = 'preflight';
                throw new RuntimeException('not authorized');
            },
            static function (int $guest, callable $callback) use (&$events) {
                $events[] = 'lock';
                return $callback();
            },
            static function (int $guest) use (&$events): array {
                $events[] = 'issue';
                return [];
            },
            static function (array $records) use (&$events): void {
                $events[] = 'export';
            },
            static fn (array $record): bool => true
        );

        try {
            $workflow->run(['actor' => 2, 'event' => 7, 'operation' => 'run-1']);
            self::fail('Unauthorized workflow must be rejected.');
        } catch (RuntimeException $error) {
            self::assertSame('not authorized', $error->getMessage());
        }
        self::assertSame(['preflight'], $events);
    }

    public function testAllGuestsMustBeReconciledBeforeExport(): void
    {
        $events = [];
        $workflow = new \WEM_Invitation_Bulk_Workflow(
            static fn (array $request): array => [101, 102],
            static function (int $guest, callable $callback) use (&$events) {
                $events[] = 'lock:' . $guest;
                return $callback();
            },
            static function (int $guest) use (&$events): array {
                $events[] = 'issue:' . $guest;
                return ['guest_id' => $guest];
            },
            static function (array $records) use (&$events): void {
                $events[] = 'export';
            },
            static function (array $record) use (&$events): bool {
                $events[] = 'reconcile:' . $record['guest_id'];
                return $record['guest_id'] === 101;
            }
        );

        $this->expectException(RuntimeException::class);
        try {
            $workflow->run(['actor' => 1, 'event' => 7, 'operation' => 'run-1']);
        } finally {
            self::assertSame([
                'lock:101', 'issue:101', 'reconcile:101',
                'lock:102', 'issue:102', 'reconcile:102',
            ], $events);
        }
    }
    public function testMissingReconciliationDependencyFailsBeforePreflight(): void
    {
        $events = [];
        try {
            new \WEM_Invitation_Bulk_Workflow(
                static function () use (&$events): array {
                    $events[] = 'preflight';
                    return [101];
                },
                static fn (int $guest, callable $callback) => $callback(),
                static function () use (&$events): array {
                    $events[] = 'issue';
                    return ['guest_id' => 101];
                },
                static function () use (&$events): void {
                    $events[] = 'export';
                }
            );
            self::fail('Production workflow must require a reconciliation dependency.');
        } catch (\Throwable $error) {
            self::assertSame([], $events);
        }
    }

}
