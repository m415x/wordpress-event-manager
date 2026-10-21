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
    /**
     * Copy an immutable private CSV to a new administrator-controlled file.
     * Never emit bearer material on stdout or overwrite existing outputs.
     */
    public function retrieve($args, $assoc_args): void
    {
        if (!is_array($args) || $args !== array() || !is_array($assoc_args)
            || count($assoc_args) !== 4) {
            throw new RuntimeException('Invalid private retrieval parameters.');
        }
        foreach (array('actor', 'operation', 'private-dir', 'output') as $key) {
            if (!isset($assoc_args[$key]) || !is_string($assoc_args[$key])) {
                throw new RuntimeException('Invalid private retrieval parameters.');
            }
        }
        $actor = filter_var($assoc_args['actor'], FILTER_VALIDATE_INT);
        if ($actor === false || $actor <= 0 || !user_can($actor, 'manage_options')) {
            throw new RuntimeException('Private retrieval is not authorized.');
        }
        $operation = $assoc_args['operation'];
        if (!preg_match('/^[A-Za-z0-9_-]{1,128}$/D', $operation)) {
            throw new RuntimeException('Invalid private retrieval operation.');
        }
        $directory = $assoc_args['private-dir'];
        $destination = $assoc_args['output'];
        if ($destination === '' || $destination[0] !== '/' || is_link($destination)
            || file_exists($destination)) {
            throw new RuntimeException('Private retrieval destination is invalid.');
        }
        $parent = realpath(dirname($destination));
        if ($parent === false || !is_dir($parent) || is_link(dirname($destination))) {
            throw new RuntimeException('Private retrieval destination directory is unavailable.');
        }
        $mode = @fileperms($parent);
        if ($mode === false || ($mode & 0077) !== 0) {
            throw new RuntimeException('Private retrieval destination directory is insecure.');
        }
        $roots = array();
        if (defined('ABSPATH')) {
            $roots[] = ABSPATH;
        }
        if (isset($_SERVER['DOCUMENT_ROOT']) && is_string($_SERVER['DOCUMENT_ROOT'])) {
            $roots[] = $_SERVER['DOCUMENT_ROOT'];
        }
        foreach ($roots as $root) {
            $public = realpath($root);
            if ($public === false) {
                continue;
            }
            $prefix = rtrim($public, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
            if ($parent === $public || strncmp($parent, $prefix, strlen($prefix)) === 0) {
                throw new RuntimeException('Private retrieval destination must be outside webroot.');
            }
        }
        $csv = (new WEM_Invitation_Private_Csv_Storage($directory))->read($operation);
        if ($csv === null) {
            throw new RuntimeException('Private CSV is unavailable.');
        }
        $handle = @fopen($destination, 'xb');
        if ($handle === false) {
            throw new RuntimeException('Unable to create private retrieval destination.');
        }
        try {
            if (!@chmod($destination, 0600)) {
                throw new RuntimeException('Unable to secure private retrieval destination.');
            }
            $offset = 0;
            $length = strlen($csv);
            while ($offset < $length) {
                $written = @fwrite($handle, substr($csv, $offset));
                if ($written === false || $written === 0) {
                    throw new RuntimeException('Unable to write private retrieval destination.');
                }
                $offset += $written;
            }
            if (!@fflush($handle) || !function_exists('fsync') || !@fsync($handle)) {
                throw new RuntimeException('Unable to synchronize private retrieval destination.');
            }
        } catch (Throwable $error) {
            fclose($handle);
            @unlink($destination);
            throw $error;
        }
        fclose($handle);
    }

}
