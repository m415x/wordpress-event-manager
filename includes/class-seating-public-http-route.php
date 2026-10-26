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

    public function deny_authentication()
    {
        return ['status' => 403, 'body' => ['code' => 'seating_access_unavailable']];
    }
}
