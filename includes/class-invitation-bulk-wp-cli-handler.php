<?php

/**
 * WEM-73 WP-CLI adapter. No default issuance workflow is provided.
 * This class must not expose invitation bearer values in errors or logs.
 */
final class WEM_Invitation_Bulk_Wp_Cli_Handler
{
    private $command;

    public function __construct($command = null)
    {
        $this->command = $command;
    }

    public function issue($args, $assoc_args): void
    {
        // An unconfigured handler must never perform writes, even with
        // apparently well-formed arguments.
        if ($this->command === null) {
            $this->deny_cli('Bulk invitation workflow is not configured.');
        }

        // Argument parsing and actor selection must be supplied by the
        // eventual WP-CLI composition root, not inferred from web requests.
        $this->deny_cli('Bulk invitation CLI issuance is not enabled.');
    }

    private function deny_cli(string $message): void
    {
        if (class_exists('WP_CLI')) {
            \WP_CLI::error($message);
        }
        // Keep standalone unit tests independent of WP-CLI bootstrap.
        throw new RuntimeException($message);
    }

    public function dispatch(array $request): void
    {
        if ($this->command === null) {
            throw new RuntimeException('Bulk invitation workflow is not configured.');
        }
        $this->command->execute($request);
    }
}
