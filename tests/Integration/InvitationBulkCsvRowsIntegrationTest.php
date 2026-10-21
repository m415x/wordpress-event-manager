<?php

declare(strict_types=1);

namespace WEM\Tests\Integration;

use WP_UnitTestCase;

final class InvitationBulkCsvRowsIntegrationTest extends WP_UnitTestCase
{
    public function testBuildsEventScopedCsvRowsFromReconciledCustodyWithoutExposingBearerInLogs(): void
    {
        require_once dirname(__DIR__, 2) . '/wordpress-event-manager.php';
        (new \WEM_Taxonomy_Manager())->register_taxonomy();
        $event = self::factory()->term->create(['taxonomy' => 'evento', 'name' => 'WEM73 CSV Rows']);
        $guest = self::factory()->post->create([
            'post_type' => 'invitado',
            'post_status' => 'publish',
            'post_title' => 'Invitado de prueba',
        ]);
        wp_set_object_terms($guest, [$event], 'evento');

        self::assertTrue(class_exists('WEM_Invitation_Bulk_Csv_Rows'));
        $token = str_repeat('A', 43);
        $record = [
            'guest_id' => $guest,
            'event_id' => $event,
            'operation_id' => 'batch-1',
            'generation' => 1,
            'token' => $token,
        ];
        $builder = new \WEM_Invitation_Bulk_Csv_Rows(
            static fn (array $custody): bool => true
        );
        $rows = $builder->build([$record], $event);
        self::assertCount(1, $rows);
        self::assertSame($guest, $rows[0]['guest_id']);
        self::assertSame('Invitado de prueba', $rows[0]['guest_name']);
        self::assertSame($event, $rows[0]['event_id']);
        self::assertSame(add_query_arg('wem_invitation', $token, home_url('/')), $rows[0]['invitation_url']);
    }

    public function testRejectsDifferentEventBeforeConstructingUrls(): void
    {
        $builder = new \WEM_Invitation_Bulk_Csv_Rows(
            static fn (array $custody): bool => true
        );
        $this->expectException(\RuntimeException::class);
        $builder->build([
            ['guest_id' => 101, 'event_id' => 9, 'generation' => 1, 'token' => str_repeat('A', 43)],
        ], 7);
    }
}
