<?php

/**
 * WEM-73: register a CLI-only command; never creates HTTP routes.
 */
final class WEM_Invitation_Bulk_Cli_Registration
{
    private $register;
    private $handler_factory;

    public function __construct(?callable $register = null, ?callable $handler_factory = null)
    {
        $this->handler_factory = $handler_factory ?? static fn () => (new WEM_Invitation_Bulk_Wp_Cli_Composition())->handler();
        $this->register = $register ?? static function (string $name, $handler): void {
            if (!class_exists('WP_CLI')) {
                throw new RuntimeException('WP-CLI is unavailable.');
            }
            \WP_CLI::add_command($name, $handler);
        };
    }

    public function register(bool $is_cli): void
    {
        if (!$is_cli) {
            return;
        }
        $handler = ($this->handler_factory)();
        ($this->register)('wem invitations', $handler);
    }
}
