<?php

/**
 * WEM-73 composition root for the administrative WP-CLI handler.
 * Delegates exclusively to the authorized operational boundary.
 */
final class WEM_Invitation_Bulk_Wp_Cli_Composition
{
    public function handler(): WEM_Invitation_Bulk_Wp_Cli_Handler
    {
        $operation = new WEM_Invitation_Bulk_Operational_Cli();
        $command = (new WEM_Invitation_Bulk_Cli_Factory())->command(
            static function (array $request) use ($operation): void {
                $operation->execute($request);
            }
        );
        return new WEM_Invitation_Bulk_Wp_Cli_Handler($command);
    }
}
