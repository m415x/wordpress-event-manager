<?php

/**
 * WEM-73: immutable, private, synchronized attempt markers.
 * A marker proves only that issuance MAY have started, never its outcome.
 */
final class WEM_Invitation_Bulk_Attempt_Storage
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

    public function mark_started(string $operation_id, int $guest_id): void
    {
        $this->assert_private_directory();
        $path = $this->path($operation_id, $guest_id);
        if (file_exists($path)) {
            throw new RuntimeException('Invitation attempt already registered.');
        }

        $tmp = $this->directory . '/.pending-attempt-' . bin2hex(random_bytes(16));
        $handle = @fopen($tmp, 'x+b');
        if ($handle === false) {
            throw new RuntimeException('Unable to allocate private attempt marker.');
        }
        try {
            if (!@chmod($tmp, 0600)) {
                throw new RuntimeException('Unable to secure private attempt marker.');
            }
            $payload = json_encode(array(
                'operation_id' => $operation_id,
                'guest_id' => $guest_id,
                'status' => 'started',
            ), JSON_THROW_ON_ERROR);
            if (@fwrite($handle, $payload) !== strlen($payload)
                || !@fflush($handle) || !function_exists('fsync') || !@fsync($handle)) {
                throw new RuntimeException('Unable to synchronize private attempt marker.');
            }
        } catch (Throwable $exception) {
            fclose($handle);
            @unlink($tmp);
            throw $exception;
        }
        fclose($handle);

        if (!@link($tmp, $path)) {
            @unlink($tmp);
            throw new RuntimeException('Unable to publish private attempt marker.');
        }
        if (!@unlink($tmp)) {
            throw new RuntimeException('Unable to finalize private attempt marker.');
        }
        $directory_handle = @fopen($this->directory, 'rb');
        if ($directory_handle === false) {
            throw new RuntimeException('Unable to synchronize attempt directory.');
        }
        try {
            if (!@fsync($directory_handle)) {
                throw new RuntimeException('Unable to synchronize attempt directory.');
            }
        } finally {
            fclose($directory_handle);
        }
        if (!$this->has_started($operation_id, $guest_id)) {
            throw new RuntimeException('Private attempt marker verification failed.');
        }
    }

    public function has_started(string $operation_id, int $guest_id): bool
    {
        $this->assert_private_directory();
        $path = $this->path($operation_id, $guest_id);
        if (!file_exists($path)) {
            return false;
        }
        if (!is_file($path) || is_link($path)) {
            throw new RuntimeException('Invalid private attempt marker.');
        }
        $mode = @fileperms($path);
        if ($mode === false || ($mode & 0077) !== 0) {
            throw new RuntimeException('Insecure private attempt marker.');
        }
        $raw = @file_get_contents($path);
        if (!is_string($raw)) {
            throw new RuntimeException('Unreadable private attempt marker.');
        }
        try {
            $data = json_decode($raw, true, 16, JSON_THROW_ON_ERROR);
        } catch (Throwable $exception) {
            throw new RuntimeException('Corrupted private attempt marker.');
        }
        if (!is_array($data) || ($data['operation_id'] ?? null) !== $operation_id
            || ($data['guest_id'] ?? null) !== $guest_id
            || ($data['status'] ?? null) !== 'started') {
            throw new RuntimeException('Private attempt marker identity mismatch.');
        }
        return true;
    }

    private function path(string $operation_id, int $guest_id): string
    {
        if (!preg_match('/^[A-Za-z0-9_-]{1,128}$/D', $operation_id) || $guest_id <= 0) {
            throw new InvalidArgumentException('Invalid invitation attempt identity.');
        }
        return $this->directory . '/' . hash('sha256', 'wem73-attempt:' . $operation_id . ':' . $guest_id) . '.json';
    }

    private function assert_private_directory(): void
    {
        $real = realpath($this->directory);
        if ($real === false || !is_dir($real) || is_link($this->directory)) {
            throw new RuntimeException('Private attempt directory unavailable.');
        }
        $mode = @fileperms($real);
        if ($mode === false || ($mode & 0077) !== 0) {
            throw new RuntimeException('Private attempt directory permissions are insecure.');
        }
        foreach ($this->webroots as $root) {
            $public = realpath($root);
            if ($public !== false) {
                $prefix = rtrim($public, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
                if ($real === $public || strncmp($real, $prefix, strlen($prefix)) === 0) {
                    throw new RuntimeException('Private attempts cannot be stored under webroot.');
                }
            }
        }
    }
}
