<?php
if (!defined('ABSPATH')) { exit; }

/**
 * WEM-93/T6: fail-closed HTTP boundary. Deliberately not registered yet.
 * HTTP operation dispatch is prohibited until its authorization, headers,
 * abuse-control and response regressions are proven.
 */
final class WEM_Seating_Public_Http_Route
{
    public function security_headers()
    {
        return [
            'Cache-Control' => 'private, no-store',
            'Referrer-Policy' => 'no-referrer',
            'X-Robots-Tag' => 'noindex, nofollow, noarchive',
        ];
    }

    /**
     * Internal HTTP response contract. No public hook is registered yet.
     * Until a real abuse policy and authorized dispatch are tested,
     * every operation fails closed with an indistinguishable denial.
     */
    public function handle_operation($operation, $bearer, $parameters = [])
    {
        $denial = $this->deny_authentication();

        return [
            'status' => $denial['status'],
            'headers' => $this->security_headers(),
            'body' => $denial['body'],
        ];
    }

    public function deny_authentication()
    {
        return ['status' => 403, 'body' => ['code' => 'seating_access_unavailable']];
    }
}
