<?php

/**
 * WEM-73: per-guest, fail-closed advisory lock for one trusted local host.
 * All bulk writers must use the same private directory and filesystem.
 * The caller must hold this around evidence read, attempt marker and issue.
 */
final class WEM_Invitation_Bulk_Guest_Lock
{
    private string $directory;

    public function __construct(string $directory)
    {
        $this->directory = $directory;
    }

    public function run(int $guest_id, callable $callback)
    {
        $real = realpath($this->directory);
        if ($guest_id <= 0 || $real === false || !is_dir($real)
            || is_link($this->directory)) {
            throw new RuntimeException('Private guest lock directory is invalid.');
        }
        $mode = @fileperms($real);
        if ($mode === false || ($mode & 0077) !== 0) {
            throw new RuntimeException('Private guest lock directory is insecure.');
        }

        $path = $real . '/' . hash('sha256', 'wem73-guest-lock:' . $guest_id) . '.lock';
        if (is_link($path)) {
            throw new RuntimeException('Private guest lock path is invalid.');
        }
        $handle = @fopen($path, 'c+b');
        if ($handle === false) {
            throw new RuntimeException('Unable to open private guest lock.');
        }
        try {
            if (!@chmod($path, 0600) || !@flock($handle, LOCK_EX | LOCK_NB)) {
                throw new RuntimeException('Invitation guest is already locked.');
            }
            try {
                return $callback();
            } finally {
                flock($handle, LOCK_UN);
            }
        } finally {
            fclose($handle);
        }
    }
}
