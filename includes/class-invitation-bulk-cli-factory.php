<?php

/**
 * WEM-73: compose the CLI authorization boundary with WordPress capabilities.
 * The workflow must be supplied explicitly by a trusted composition root;
 * this factory does not enable issuance or export by itself.
 */
final class WEM_Invitation_Bulk_Cli_Factory
{
    public function command(callable $workflow): WEM_Invitation_Bulk_Cli_Command
    {
        return new WEM_Invitation_Bulk_Cli_Command(
            static function (int $actor_id): bool {
                return $actor_id > 0 && user_can($actor_id, 'manage_options');
            },
            $workflow
        );
    }
}
