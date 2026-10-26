<?php
if (!defined('ABSPATH')) { exit; }

/**
 * WEM-93/T6: deny-all REST transport gate. Authorized operations remain
 * disabled until authorization, abuse control and HTTP regressions pass.
 */
final class WEM_Seating_Public_Http_Route
{
    public function register_hooks()
    {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes()
    {
        register_rest_route('wem/v1', '/seating/availability', [
            'methods' => 'GET',
            'permission_callback' => '__return_true',
            'callback' => [$this, 'deny_rest_request'],
        ]);
    }

    public function deny_rest_request($request)
    {
        // Server-observed peer address only. Never include the bearer in the
        // rate bucket or trust client-forwarded IP headers.
        $peer = isset($_SERVER['REMOTE_ADDR']) && is_string($_SERVER['REMOTE_ADDR'])
            ? $_SERVER['REMOTE_ADDR'] : '';
        $bucket = 'seating:availability:' . $peer;
        (new WEM_Seating_Public_Abuse_Guard())->record_attempt($bucket, 30, 60);
        // Absent/invalid peer and quota failures remain a generic DENY.
        $result = $this->handle_operation('availability', null);
        $response = new WP_REST_Response($result['body'], $result['status']);
        foreach ($result['headers'] as $name => $value) {
            $response->header($name, $value);
        }
        return $response;
    }

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
