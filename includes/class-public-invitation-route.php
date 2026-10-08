<?php

if (!defined('ABSPATH')) {
    exit;
}

final class WEM_Public_Invitation_Route
{
    public function register_hooks()
    {
        add_filter('query_vars', array($this, 'register_query_var'));
        add_action('template_redirect', array($this, 'handle_request'));
    }

    public function register_query_var($query_vars)
    {
        $query_vars[] = 'wem_invitation';

        return $query_vars;
    }

    public function handle_request()
    {
        if (!isset($_GET['wem_invitation'])) {
            return null;
        }

        $request = array();

        foreach ($_GET as $key => $value) {
            if (!is_string($key) || !is_scalar($value)) {
                return null;
            }

            $request[$key] = wp_unslash((string) $value);
        }

        return $this->resolve_request($request);
    }

    public function resolve_request(array $request)
    {
        $allowed_keys = array('wem_invitation');
        $keys = array_keys($request);

        if ($keys !== $allowed_keys) {
            return null;
        }

        $token = $request['wem_invitation'] ?? null;

        if (!is_string($token)) {
            return null;
        }

        $resolver = new WEM_Public_Invitation_Resolver();

        return $resolver->resolve($token);
    }
}
