<?php

/**
 * WEM-73 command boundary. All requests must be validated before the
 * workflow is invoked; WP-CLI registration and durable orchestration are
 * separate responsibilities.
 */
final class WEM_Invitation_Bulk_Cli_Command
{
    private $authorized;
    private $workflow;

    public function __construct(callable $authorized, callable $workflow)
    {
        $this->authorized = $authorized;
        $this->workflow = $workflow;
    }

    public function execute(array $request): void
    {
        $actor = $request['actor'] ?? null;
        $event = $request['event'] ?? null;
        $operation = $request['operation'] ?? null;
        $private_dir = $request['private_dir'] ?? null;

        if (!is_int($actor) || $actor <= 0 || !(($this->authorized)($actor))) {
            throw new RuntimeException('Invitation bulk command is not authorized.');
        }
        if (!is_int($event) || $event <= 0
            || !is_string($operation)
            || !preg_match('/^[A-Za-z0-9_-]{1,128}$/D', $operation)
            || !is_string($private_dir) || trim($private_dir) === '') {
            throw new RuntimeException('Invalid invitation bulk command parameters.');
        }

        ($this->workflow)($request);
    }
}
