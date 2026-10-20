<?php

/**
 * WEM-73: immutable batch selection manifest. Not an issuance authorization.
 * Store only in a private persistent directory outside all document roots.
 */
final class WEM_Invitation_Bulk_Manifest
{
    private string $directory;
    private array $webroots;

    public function __construct(string $directory, array $webroots = [])
    {
        $this->directory = $directory;
        $this->webroots = $webroots;
        if (defined('ABSPATH')) {
            $this->webroots[] = ABSPATH;
        }
        if (isset($_SERVER['DOCUMENT_ROOT']) && is_string($_SERVER['DOCUMENT_ROOT'])) {
            $this->webroots[] = $_SERVER['DOCUMENT_ROOT'];
        }
    }

    public function create(string $operation_id, int $event_id, array $guest_ids): void
    {
        $this->assert_private_directory();
        $path = $this->path($operation_id);
        if ($event_id <= 0 || !$guest_ids) {
            throw new RuntimeException('Invalid invitation batch manifest.');
        }
        $seen = array();
        foreach ($guest_ids as $guest_id) {
            if (!is_int($guest_id) || $guest_id <= 0 || isset($seen[$guest_id])) {
                throw new RuntimeException('Invalid or duplicate invitation batch guest.');
            }
            $seen[$guest_id] = true;
        }
        $record = array(
            'operation_id' => $operation_id,
            'event_id' => $event_id,
            'guest_ids' => array_values($guest_ids),
        );
        $payload = json_encode($record, JSON_THROW_ON_ERROR);
        if (file_exists($path)) {
            throw new RuntimeException('Invitation batch manifest already exists.');
        }

        $temporary = $this->directory . '/.pending-manifest-' . bin2hex(random_bytes(16));
        $handle = @fopen($temporary, 'x+b');
        if ($handle === false) {
            throw new RuntimeException('Unable to allocate private batch manifest.');
        }
        try {
            if (!@chmod($temporary, 0600)) {
                throw new RuntimeException('Unable to secure private batch manifest.');
            }
            $offset = 0;
            while ($offset < strlen($payload)) {
                $n = @fwrite($handle, substr($payload, $offset));
                if ($n === false || $n === 0) {
                    throw new RuntimeException('Unable to write private batch manifest.');
                }
                $offset += $n;
            }
            if (!@fflush($handle) || !function_exists('fsync') || !@fsync($handle)) {
                throw new RuntimeException('Unable to synchronize private batch manifest.');
            }
        } catch (Throwable $exception) {
            fclose($handle);
            @unlink($temporary);
            throw $exception;
        }
        fclose($handle);
        if (!@link($temporary, $path)) {
            @unlink($temporary);
            throw new RuntimeException('Unable to publish private batch manifest.');
        }
        if (!@unlink($temporary)) {
            throw new RuntimeException('Unable to finalize private batch manifest.');
        }
        $directory_handle = @fopen($this->directory, 'rb');
        if ($directory_handle === false) {
            throw new RuntimeException('Unable to synchronize manifest directory.');
        }
        try {
            if (!@fsync($directory_handle)) {
                throw new RuntimeException('Unable to synchronize manifest directory.');
            }
        } finally {
            fclose($directory_handle);
        }
        if ($this->read($operation_id) !== $record) {
            throw new RuntimeException('Private batch manifest verification failed.');
        }
    }

    public function read(string $operation_id): ?array
    {
        $this->assert_private_directory();
        $path = $this->path($operation_id);
        if (!file_exists($path)) {
            return null;
        }
        if (!is_file($path) || is_link($path)) {
            throw new RuntimeException('Invalid private batch manifest.');
        }
        $mode = @fileperms($path);
        if ($mode === false || ($mode & 0077) !== 0) {
            throw new RuntimeException('Insecure private batch manifest permissions.');
        }
        $payload = @file_get_contents($path);
        if (!is_string($payload)) {
            throw new RuntimeException('Unable to read private batch manifest.');
        }
        try {
            $record = json_decode($payload, true, 32, JSON_THROW_ON_ERROR);
        } catch (Throwable $exception) {
            throw new RuntimeException('Invalid private batch manifest content.');
        }
        if (!is_array($record) || ($record['operation_id'] ?? null) !== $operation_id
            || !isset($record['event_id'], $record['guest_ids'])
            || !is_int($record['event_id']) || $record['event_id'] <= 0
            || !is_array($record['guest_ids']) || !$record['guest_ids']
            || array_values($record['guest_ids']) !== $record['guest_ids']
            || count(array_unique($record['guest_ids'])) !== count($record['guest_ids'])) {
            throw new RuntimeException('Private batch manifest identity mismatch.');
        }
        foreach ($record['guest_ids'] as $id) {
            if (!is_int($id) || $id <= 0) {
                throw new RuntimeException('Invalid private batch manifest guest.');
            }
        }
        return $record;
    }

    private function path(string $operation_id): string
    {
        if (!preg_match('/^[A-Za-z0-9_-]{1,128}$/D', $operation_id)) {
            throw new RuntimeException('Invalid invitation batch operation.');
        }
        return $this->directory . '/' . hash('sha256', 'wem73-manifest:' . $operation_id) . '.json';
    }

    private function assert_private_directory(): void
    {
        $real = realpath($this->directory);
        if ($real === false || !is_dir($real) || is_link($this->directory)) {
            throw new RuntimeException('Private batch manifest directory unavailable.');
        }
        $mode = @fileperms($real);
        if ($mode === false || ($mode & 0077) !== 0) {
            throw new RuntimeException('Insecure private batch manifest directory.');
        }
        foreach ($this->webroots as $root) {
            $public = realpath($root);
            if ($public !== false) {
                $prefix = rtrim($public, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
                if ($real === $public || strncmp($real, $prefix, strlen($prefix)) === 0) {
                    throw new RuntimeException('Private batch manifest cannot reside in webroot.');
                }
            }
        }
    }
}
