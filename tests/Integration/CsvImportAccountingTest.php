<?php

declare(strict_types=1);

namespace WEM\Tests\Integration;

use ReflectionMethod;
use WP_UnitTestCase;

final class CsvImportAccountingTest extends WP_UnitTestCase
{
    private \WEM_Import_Export $importer;

    protected function setUp(): void
    {
        parent::setUp();

        require_once dirname(__DIR__, 2) . '/wordpress-event-manager.php';
        $this->importer = new \WEM_Import_Export();

        if (!taxonomy_exists('evento')) {
            register_taxonomy('evento', 'invitado');
        }

        if (!post_type_exists('invitado')) {
            register_post_type('invitado', ['public' => false]);
        }
    }

    public function testCountsCreatedUpdatedAndInvalidRowsAcrossEvents(): void
    {
        $this->ensureEvent('WEM CSV Event A');
        $this->ensureEvent('WEM CSV Event B');
        $existingId = $this->createGuest('Existing Ticket', 'WEM CSV Event A');
        update_post_meta($existingId, 'wem_nombre', 'Before update');

        $rows = [
            ['New Ticket', 'New name', '', '', 'WEM CSV Event A', '', '0'],
            ['Existing Ticket', 'Updated name', '', '', 'WEM CSV Event A', '', '0'],
            ['Existing Ticket', 'Different event', '', '', 'WEM CSV Event B', '', '0'],
            ['', 'No title', '', '', 'WEM CSV Event A', '', '0'],
        ];

        $counts = $this->importRows($rows, true);

        self::assertSame(
            ['count' => 2, 'skipped' => 1, 'updated' => 1],
            $counts,
            'An existing guest updated in place must count as updated, not created'
        );
        self::assertSame('Updated name', get_post_meta($existingId, 'wem_nombre', true));
        self::assertSame(
            $existingId,
            $this->guestId('Existing Ticket', 'WEM CSV Event A'),
            'Updating a guest must retain its original ID'
        );
        self::assertNotSame(
            $existingId,
            $this->guestId('Existing Ticket', 'WEM CSV Event B'),
            'Identical titles from different events must remain independent'
        );
        self::assertGreaterThan(0, $this->guestId('New Ticket', 'WEM CSV Event A'));
    }

    public function testExistingGuestIsSkippedWithoutUpdatePermission(): void
    {
        $this->ensureEvent('WEM CSV Event C');
        $existingId = $this->createGuest('Keep Ticket', 'WEM CSV Event C');
        update_post_meta($existingId, 'wem_nombre', 'Keep original');

        $counts = $this->importRows([
            ['Keep Ticket', 'Do not overwrite', '', '', 'WEM CSV Event C', '', '0'],
        ], false);

        self::assertSame(['count' => 0, 'skipped' => 1, 'updated' => 0], $counts);
        self::assertSame('Keep original', get_post_meta($existingId, 'wem_nombre', true));
    }

    public function testMismatchedCsvColumnCountIsSkipped(): void
    {
        $counts = $this->importRows([['Only a title']], true);

        self::assertSame(['count' => 0, 'skipped' => 1, 'updated' => 0], $counts);
    }

    private function importRows(array $rows, bool $updateExisting): array
    {
        $header = ['titulo', 'nombre', 'organizacion', 'mesa', 'evento', 'observaciones', 'checkin'];
        $handle = fopen('php://temp', 'w+');
        self::assertIsResource($handle);

        try {
            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }

            rewind($handle);
            $method = new ReflectionMethod(\WEM_Import_Export::class, 'import_csv_data');

            return $method->invoke($this->importer, $handle, $header, ',', $updateExisting);
        } finally {
            fclose($handle);
        }
    }

    private function ensureEvent(string $name): void
    {
        if (!term_exists($name, 'evento')) {
            $result = wp_insert_term($name, 'evento');
            self::assertNotWPError($result);
        }
    }

    private function createGuest(string $title, string $event): int
    {
        $id = self::factory()->post->create([
            'post_type' => 'invitado',
            'post_title' => $title,
            'post_status' => 'publish',
        ]);
        self::assertGreaterThan(0, $id);
        $term = term_exists($event, 'evento');
        self::assertNotFalse($term);
        $termId = is_array($term) ? (int) $term['term_id'] : (int) $term;
        wp_set_object_terms($id, $termId, 'evento');

        return $id;
    }

    private function guestId(string $title, string $event): int
    {
        $term = term_exists($event, 'evento');
        if (!$term) {
            return 0;
        }

        $termId = is_array($term) ? (int) $term['term_id'] : (int) $term;
        $guests = get_posts([
            'post_type' => 'invitado',
            'title' => $title,
            'tax_query' => [[
                'taxonomy' => 'evento',
                'field' => 'term_id',
                'terms' => $termId,
            ]],
            'numberposts' => 1,
            'post_status' => 'any',
        ]);

        return $guests ? (int) $guests[0]->ID : 0;
    }
}
