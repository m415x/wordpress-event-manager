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
        if ($this->command === null) {
            $this->deny_cli('Bulk invitation workflow is not configured.');
        }

        if (!is_array($args) || $args !== array() || !is_array($assoc_args)) {
            $this->deny_cli('Invalid invitation bulk command parameters.');
        }

        foreach (array('actor', 'event', 'operation', 'private-dir') as $key) {
            if (!isset($assoc_args[$key]) || !is_string($assoc_args[$key])) {
                $this->deny_cli('Invalid invitation bulk command parameters.');
            }
        }
        if (count($assoc_args) !== 4
            || !preg_match('/^[1-9][0-9]*$/D', $assoc_args['actor'])
            || !preg_match('/^[1-9][0-9]*$/D', $assoc_args['event'])
            || filter_var($assoc_args['actor'], FILTER_VALIDATE_INT) === false
            || filter_var($assoc_args['event'], FILTER_VALIDATE_INT) === false) {
            $this->deny_cli('Invalid invitation bulk command parameters.');
        }

        $request = array(
            'actor' => (int) $assoc_args['actor'],
            'event' => (int) $assoc_args['event'],
            'operation' => $assoc_args['operation'],
            'private_dir' => $assoc_args['private-dir'],
        );

        try {
            $this->dispatch($request);
        } catch (RuntimeException $error) {
            $this->deny_cli('Bulk invitation command rejected.');
        }
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
