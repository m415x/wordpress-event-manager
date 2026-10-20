<?php

/**
 * WEM-73: reject unsafe administrative requests before guest discovery.
 * File-system privacy and durable storage are checked separately by the
 * journal and CSV storage at the time of use.
 */
final class WEM_Invitation_Bulk_Cli_Preflight
{
    private $is_admin;
    private $discover_guests;

    public function __construct(callable $is_admin, callable $discover_guests)
    {
        $this->is_admin = $is_admin;
        $this->discover_guests = $discover_guests;
    }

    public function prepare(
        int $actor_id,
        int $event_id,
        string $operation_id,
        string $journal_directory,
        string $csv_directory
    ): array {
        if ($actor_id <= 0 || !(($this->is_admin)($actor_id))) {
            throw new RuntimeException('Bulk invitation administration is not authorized.');
        }
        if ($event_id <= 0 || !preg_match('/^[A-Za-z0-9_-]{1,128}$/D', $operation_id)) {
            throw new RuntimeException('Invalid bulk invitation event or operation.');
        }
        if (trim($journal_directory) === '' || trim($csv_directory) === '') {
            throw new RuntimeException('Private invitation directories are required.');
        }
        $guests = ($this->discover_guests)($event_id);
        if (!is_array($guests)) {
            throw new RuntimeException('Unable to discover event guests.');
        }
        return $guests;
    }
}
