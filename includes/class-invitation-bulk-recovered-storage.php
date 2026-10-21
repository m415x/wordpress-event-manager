<?php
/**
 * Recovery adapter for an existing, privately custodied invitation.
 * The committed database is the authority; this adapter never issues.
 */
final class WEM_Invitation_Bulk_Recovered_Storage
{
    private $admission;
    private $journal;
    private $reconciliation;

    public function __construct(
        WEM_Invitation_Bulk_Admission_Evidence $admission,
        WEM_Invitation_Private_Journal $journal,
        WEM_Invitation_Custody_Database_Reconciliation $reconciliation
    ) {
        $this->admission = $admission;
        $this->journal = $journal;
        $this->reconciliation = $reconciliation;
    }

    public function recover(string $operation_id, int $event_id, array $guest_ids, int $guest_id): array
    {
        $admission = $this->admission;
        $journal = $this->journal;
        $reconciliation = $this->reconciliation;
        $service = new WEM_Invitation_Bulk_Recovered_Credential(
            static fn (string $operation, int $guest): string =>
                $admission->admit_and_classify($operation, $event_id, $guest_ids, $guest),
            static function (string $operation, int $guest) use ($journal): ?array {
                global $wpdb;
                $table = WEM_Invitation_Credential_Schema::table_name();
                $wpdb->last_error = '';
                $rows = $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT generation FROM {$table} WHERE guest_id = %d",
                        $guest
                    ),
                    ARRAY_A
                );
                if (!is_array($rows) || $wpdb->last_error !== '' || count($rows) !== 1) {
                    throw new RuntimeException('Invitation recovery history is not unique.');
                }
                $generation = (int) $rows[0]['generation'];
                if ($generation <= 0) {
                    throw new RuntimeException('Invalid invitation recovery generation.');
                }
                return $journal->recover($operation, $guest, $generation);
            },
            static fn (array $record): bool => $reconciliation->is_exportable($record)
        );
        return $service->recover($operation_id, $event_id, $guest_id);
    }
}
