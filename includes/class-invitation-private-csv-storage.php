<?php

/**
 * WEM-73: private, immutable CSV artifacts. This class does not authorize
 * exports; callers must reconcile every credential immediately before release.
 */
final class WEM_Invitation_Private_Csv_Storage
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

    public function save(string $operation_id, string $csv): void
    {
        $this->assert_private_directory();
        $path = $this->path($operation_id);
        if (file_exists($path)) {
            throw new RuntimeException('Private CSV already exists.');
        }

        $temporary = $this->directory . '/.pending-csv-' . bin2hex(random_bytes(16));
        $handle = @fopen($temporary, 'x+b');
        if ($handle === false) {
            throw new RuntimeException('Unable to allocate private CSV.');
        }
        try {
            if (!@chmod($temporary, 0600)) {
                throw new RuntimeException('Unable to secure private CSV.');
            }
            $offset = 0;
            $length = strlen($csv);
            while ($offset < $length) {
                $count = @fwrite($handle, substr($csv, $offset));
                if ($count === false || $count === 0) {
                    throw new RuntimeException('Unable to write private CSV.');
                }
                $offset += $count;
            }
            if (!@fflush($handle) || !function_exists('fsync') || !@fsync($handle)) {
                throw new RuntimeException('Unable to synchronize private CSV.');
            }
        } catch (Throwable $error) {
            fclose($handle);
            @unlink($temporary);
            throw $error;
        }
        fclose($handle);
        if (!@link($temporary, $path)) {
            @unlink($temporary);
            throw new RuntimeException('Unable to publish private CSV.');
        }
        if (!@unlink($temporary)) {
            throw new RuntimeException('Unable to finalize private CSV.');
        }
        $directory_handle = @fopen($this->directory, 'rb');
        if ($directory_handle === false) {
            throw new RuntimeException('Unable to synchronize private CSV directory.');
        }
        try {
            if (!@fsync($directory_handle)) {
                throw new RuntimeException('Unable to synchronize private CSV directory.');
            }
        } finally {
            fclose($directory_handle);
        }
        if ($this->read($operation_id) !== $csv) {
            throw new RuntimeException('Private CSV verification failed.');
        }
    }

    public function read(string $operation_id): ?string
    {
        $this->assert_private_directory();
        $path = $this->path($operation_id);
        if (!is_file($path) || is_link($path)) {
            return null;
        }
        $mode = @fileperms($path);
        if ($mode === false || ($mode & 0077) !== 0) {
            throw new RuntimeException('Private CSV permissions are insecure.');
        }
        $data = @file_get_contents($path);
        if (!is_string($data)) {
            throw new RuntimeException('Unable to read private CSV.');
        }
        return $data;
    }

    private function path(string $operation_id): string
    {
        if (!preg_match('/^[A-Za-z0-9_-]{1,128}$/D', $operation_id)) {
            throw new InvalidArgumentException('Invalid private CSV operation identifier.');
        }
        return $this->directory . '/' . hash('sha256', 'wem73-csv:' . $operation_id) . '.csv';
    }

    private function assert_private_directory(): void
    {
        $real = realpath($this->directory);
        if ($real === false || !is_dir($real) || is_link($this->directory)) {
            throw new RuntimeException('Private CSV directory unavailable.');
        }
        $mode = @fileperms($real);
        if ($mode === false || ($mode & 0077) !== 0) {
            throw new RuntimeException('Private CSV directory permissions are insecure.');
        }
        foreach ($this->webroots as $root) {
            $public = realpath($root);
            if ($public !== false
                && ($real === $public || strncmp($real, rtrim($public, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR,
                    strlen(rtrim($public, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR)) === 0)) {
                throw new RuntimeException('Private CSV directory must be outside the webroot.');
            }
        }
    }
}
