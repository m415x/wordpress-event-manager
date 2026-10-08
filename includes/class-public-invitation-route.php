<?php

if (!defined('ABSPATH')) {
    exit;
}

final class WEM_Public_Invitation_Route
{
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
