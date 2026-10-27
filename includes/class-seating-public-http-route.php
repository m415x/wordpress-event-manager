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
        if (filter_var($peer, FILTER_VALIDATE_IP) !== false) {
            $bucket = 'seating:availability:' . $peer;
            (new WEM_Seating_Public_Abuse_Guard())->record_attempt($bucket, 30, 60);
        }
        // Missing/invalid peer: fail closed without a shared anonymous bucket.
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
        if ($operation === 'availability' && is_string($bearer)) {
            try {
                $available = (new WEM_Seating_Bearer_Availability())->list_for_bearer($bearer);
                if (is_array($available)) {
                    return [
                        'status' => 200,
                        'headers' => $this->security_headers(),
                        'body' => $available,
                    ];
                }
            } catch (Throwable $error) {
                // Internal availability failures must not expose SQL, PII or
                // credentials. Authorization failures retain the same 403.
                return [
                    'status' => 503,
                    'headers' => $this->security_headers(),
                    'body' => ['code' => 'seating_temporarily_unavailable'],
                ];
            }
        }
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
