<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Executes an explicit bounded selection, with fresh per-guest validation.
 * No retries or compensating credential operations are permitted.
 */
final class WEM_Admin_Invitation_QR_Bulk_Processor
{
    private $revalidator;
    private $orchestrator;

    public function __construct($revalidator, $orchestrator)
    {
        $this->revalidator = $revalidator;
        $this->orchestrator = $orchestrator;
    }

    public function execute($guest_ids, $operation, $actor_id): array
    {
        $ids = WEM_Admin_Invitation_QR_Bulk_Selection::validate($guest_ids, $operation);
        if (!is_int($actor_id) || $actor_id <= 0) {
            throw new InvalidArgumentException('Invalid bulk QR actor.');
        }

        $results = array();
        foreach ($ids as $guest_id) {
            try {
                if (!$this->revalidator->permits($guest_id, $operation)) {
                    $results[] = array('guest_id' => $guest_id, 'status' => 'denied');
                    continue;
                }

                $issued = $this->orchestrator->{$operation}($guest_id, $actor_id);
                $results[] = array(
                    'guest_id' => $guest_id,
                    'status' => 'issued',
                    'url' => $issued['url'],
                    'svg' => $issued['svg'],
                    'generation' => $issued['generation'],
                );
            } catch (Throwable $error) {
                // Credential writes may already have committed. Never retry,
                // rotate, compensate, or expose exceptions or bearer material.
                $results[] = array('guest_id' => $guest_id, 'status' => 'unavailable');
            }
        }

        return $results;
    }
}
