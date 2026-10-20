<?php

/**
 * WEM-73: authorization boundary for each prospective invitation issuance.
 * Dependencies are injected to keep policy testable independently of WordPress.
 */
final class WEM_Invitation_Bulk_Issuance_Authorizer
{
    private $is_admin;
    private $is_event_guest;

    public function __construct(callable $is_admin, callable $is_event_guest)
    {
        $this->is_admin = $is_admin;
        $this->is_event_guest = $is_event_guest;
    }

    public function run(int $actor_id, int $guest_id, int $event_id, callable $issue)
    {
        if ($actor_id <= 0 || $guest_id <= 0 || $event_id <= 0
            || !(($this->is_admin)($actor_id))) {
            throw new RuntimeException('Invitation issuance is not authorized.');
        }
        if (!(($this->is_event_guest)($guest_id, $event_id))) {
            throw new RuntimeException('Invitation guest is outside the requested event.');
        }

        // Only the existing WEM-33 issuance bridge may mutate credentials.
        return $issue();
    }
}
