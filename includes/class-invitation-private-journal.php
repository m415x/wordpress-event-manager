<?php

/**
 * WEM-73 private, append-only bearer custody. Never expose this directory via HTTP.
 * The operator must provision a private, persistent directory outside document roots.
 */
final class WEM_Invitation_Private_Journal
{
    private string $directory;
    private array $webroots;
    private $directory_sync;

    public function __construct(string $directory, array $webroots = [], ?callable $directory_sync = null)
    {
        $this->directory = $directory;
        $this->directory_sync = $directory_sync ?? array($this, 'sync_directory');
        $this->webroots = $webroots;
        if (defined('ABSPATH')) {
            $this->webroots[] = ABSPATH;
        }
        if (isset($_SERVER['DOCUMENT_ROOT']) && is_string($_SERVER['DOCUMENT_ROOT'])) {
            $this->webroots[] = $_SERVER['DOCUMENT_ROOT'];
        }
    }

    public function custody(array $record): void
    {
        $this->assert_private_directory();
        $this->validate_record($record);
        $path = $this->path($record['operation_id'], $record['guest_id'], $record['generation']);
        if (file_exists($path)) {
            throw new RuntimeException('Private custody record already exists.');
        }

        $serialized = json_encode($record, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $temp = $this->directory . '/.pending-' . bin2hex(random_bytes(16));
        $handle = @fopen($temp, 'x+b');
        if ($handle === false) {
            throw new RuntimeException('Unable to allocate private custody record.');
        }
        try {
            if (!@chmod($temp, 0600)) {
                throw new RuntimeException('Unable to secure private custody record.');
            }
            $written = 0;
            $length = strlen($serialized);
            while ($written < $length) {
                $n = @fwrite($handle, substr($serialized, $written));
                if ($n === false || $n === 0) {
                    throw new RuntimeException('Unable to persist private custody record.');
                }
                $written += $n;
            }
            if (!@fflush($handle) || !function_exists('fsync') || !@fsync($handle)) {
                throw new RuntimeException('Private custody storage could not be synchronized.');
            }
        } catch (Throwable $error) {
            fclose($handle);
            @unlink($temp);
            throw $error;
        }
        fclose($handle);

        // Never overwrite a prior record. Hard-link publication is atomic on
        // the same filesystem and fails when the target already exists.
        if (!@link($temp, $path)) {
            @unlink($temp);
            throw new RuntimeException('Unable to publish private custody record.');
        }
        if (!@unlink($temp)) {
            throw new RuntimeException('Unable to finalize private custody publication.');
        }
        // Publication and removal of the temporary hard link both alter the
        // directory. Both must be on stable storage before returning to issue().
        if (!(($this->directory_sync)($this->directory))) {
            // The final record may exist despite this failure. Never delete or
            // overwrite it: the operation requires later reconciliation.
            throw new RuntimeException('Private custody directory synchronization failed.');
        }
        // Read back and verify before permitting the SQL transaction to commit.
        if ($this->recover($record['operation_id'], $record['guest_id'], $record['generation']) !== $record) {
            throw new RuntimeException('Private custody verification failed.');
        }
    }

    public function recover(string $operation_id, int $guest_id, int $generation): ?array
    {
        $this->assert_private_directory();
        $path = $this->path($operation_id, $guest_id, $generation);
        if (!is_file($path) || is_link($path)) {
            return null;
        }
        $permissions = @fileperms($path);
        if ($permissions === false || (($permissions & 0077) !== 0)) {
            throw new RuntimeException('Private custody permissions are insecure.');
        }
        $raw = @file_get_contents($path);
        if (!is_string($raw)) {
            throw new RuntimeException('Unable to read private custody record.');
        }
        try {
            $record = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
            $this->validate_record($record);
        } catch (Throwable $error) {
            throw new RuntimeException('Private custody record is invalid.');
        }
        if ($record['operation_id'] !== $operation_id
            || $record['guest_id'] !== $guest_id
            || $record['generation'] !== $generation) {
            throw new RuntimeException('Private custody identity mismatch.');
        }
        return $record;
    }

    /**
     * Sync directory metadata after publishing the already-fsynced file.
     * Directory streams are supported by the Linux PHP runtime; on platforms
     * where they cannot be opened/synced, custody fails closed.
     */
    private function sync_directory(string $directory): bool
    {
        if (!function_exists('fsync')) {
            return false;
        }
        $handle = @fopen($directory, 'rb');
        if ($handle === false) {
            return false;
        }
        try {
            return @fsync($handle);
        } finally {
            fclose($handle);
        }
    }

    private function validate_record($record): void
    {
        if (!is_array($record)
            || !isset($record['operation_id'], $record['guest_id'], $record['event_id'],
                $record['generation'], $record['token'], $record['token_digest'])
            || !is_string($record['operation_id'])
            || !preg_match('/^[A-Za-z0-9_-]{1,128}$/D', $record['operation_id'])
            || !is_int($record['guest_id']) || $record['guest_id'] <= 0
            || !is_int($record['event_id']) || $record['event_id'] <= 0
            || !is_int($record['generation']) || $record['generation'] <= 0
            || !is_string($record['token'])
            || !preg_match('/^[A-Za-z0-9_-]{43}$/D', $record['token'])
            || !is_string($record['token_digest'])
            || !hash_equals(hash('sha256', $record['token']), $record['token_digest'])) {
            throw new RuntimeException('Invalid private custody record.');
        }
    }

    private function path(string $operation_id, int $guest_id, int $generation): string
    {
        if (!preg_match('/^[A-Za-z0-9_-]{1,128}$/D', $operation_id)
            || $guest_id <= 0 || $generation <= 0) {
            throw new RuntimeException('Invalid custody lookup.');
        }
        return $this->directory . '/' . hash('sha256', $operation_id . ':' . $guest_id . ':' . $generation) . '.json';
    }

    private function assert_private_directory(): void
    {
        $real = realpath($this->directory);
        if ($real === false || !is_dir($real) || is_link($this->directory)) {
            throw new RuntimeException('Private custody directory is unavailable.');
        }
        $mode = @fileperms($real);
        if ($mode === false || ($mode & 0077) !== 0) {
            throw new RuntimeException('Private custody directory permissions are insecure.');
        }
        foreach ($this->webroots as $root) {
            $public = realpath($root);
            if ($public !== false
                && ($real === $public || strncmp($real, rtrim($public, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR,
                    strlen(rtrim($public, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR)) === 0)) {
                throw new RuntimeException('Private custody cannot reside inside a public directory.');
            }
        }
    }
}
