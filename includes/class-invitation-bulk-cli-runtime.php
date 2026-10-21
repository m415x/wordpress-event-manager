<?php

/**
 * WEM-73: authorization and private storage gate for a trusted CLI workflow.
 * Does not instantiate an issuer or publish a CSV.
 */
final class WEM_Invitation_Bulk_Cli_Runtime
{
    private $command;

    public function __construct(callable $authorized, callable $workflow)
    {
        $this->command = new WEM_Invitation_Bulk_Cli_Command(
            $authorized,
            static function (array $request) use ($workflow): void {
                $directory = $request['private_dir'];
                $real = realpath($directory);
                if ($real === false || !is_dir($real) || is_link($directory)
                    || !is_string($directory) || $directory === ''
                    || $directory[0] !== '/') {
                    throw new RuntimeException('Private invitation directory is unavailable.');
                }
                $mode = @fileperms($real);
                if ($mode === false || ($mode & 0077) !== 0) {
                    throw new RuntimeException('Private invitation directory is insecure.');
                }
                $public_roots = array();
                if (defined('ABSPATH')) {
                    $public_roots[] = ABSPATH;
                }
                if (isset($_SERVER['DOCUMENT_ROOT']) && is_string($_SERVER['DOCUMENT_ROOT'])) {
                    $public_roots[] = $_SERVER['DOCUMENT_ROOT'];
                }
                foreach ($public_roots as $public_root) {
                    $root = realpath($public_root);
                    if ($root === false) {
                        continue;
                    }
                    $prefix = rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
                    if ($real === $root || strncmp($real, $prefix, strlen($prefix)) === 0) {
                        throw new RuntimeException('Private invitation directory cannot be in webroot.');
                    }
                }
                $workflow($request);
            }
        );
    }

    public function execute(array $request): void
    {
        $this->command->execute($request);
    }
}
