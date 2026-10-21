<?php
/**
 * WEM-73 operational orchestration. Authorization and private directory
 * verification precede any durable work.
 */
final class WEM_Invitation_Bulk_Operational_Cli
{
    public function execute(array $request): void
    {
        $runtime = new WEM_Invitation_Bulk_Cli_Runtime(
            static fn (int $actor): bool => user_can($actor, 'manage_options'),
            static function (array $input): void {
                $actor = $input['actor'];
                $event = $input['event'];
                $operation = $input['operation'];
                $directory = $input['private_dir'];

                $guests = (new WEM_Invitation_Bulk_Event_Discovery())->guests($event);
                if (!$guests) {
                    throw new RuntimeException('No eligible invitation guests.');
                }

                $manifest = new WEM_Invitation_Bulk_Manifest($directory);
                $attempts = new WEM_Invitation_Bulk_Attempt_Storage($directory);
                $journal = new WEM_Invitation_Private_Journal($directory);
                $admission = new WEM_Invitation_Bulk_Admission_Evidence($manifest, $attempts, $journal);
                $reconciler = new WEM_Invitation_Custody_Database_Reconciliation();

                $issuer = new WEM_Invitation_Bulk_Admitted_Locked_Storage(
                    new WEM_Invitation_Bulk_Guest_Lock($directory),
                    $admission,
                    $attempts,
                    new WEM_Invitation_Custody_Issuance_Bridge(
                        new WEM_Invitation_Credential_Service(),
                        $journal
                    ),
                    $actor
                );

                $publication = new WEM_Invitation_Bulk_Csv_Publication(
                    new WEM_Invitation_Bulk_Csv_Rows(
                        static fn (array $record): bool => $reconciler->is_exportable($record)
                    ),
                    new WEM_Invitation_Bulk_Private_Export(
                        new WEM_Invitation_Private_Csv(),
                        new WEM_Invitation_Private_Csv_Storage($directory),
                        static fn (array $row): bool => true
                    )
                );

                $workflow = new WEM_Invitation_Bulk_Mixed_Csv_Workflow(
                    $admission,
                    new WEM_Invitation_Bulk_Recovered_Storage($admission, $journal, $reconciler),
                    $issuer,
                    $reconciler,
                    $publication
                );
                $workflow->run($operation, $event, $guests);
            }
        );
        $runtime->execute($request);
    }
}
