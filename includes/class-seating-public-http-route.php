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
        register_rest_route('wem/v1', '/seating/reservations', [
            'methods' => 'POST',
            'permission_callback' => '__return_true',
            'callback' => [$this, 'deny_reservation_request'],
        ]);
        register_rest_route('wem/v1', '/seating/members', [
            'methods' => 'GET',
            'permission_callback' => '__return_true',
            'callback' => [$this, 'deny_members_request'],
        ]);
    }

    public function deny_reservation_request($request)
    {
        // The HTTP endpoint remains read-only until I2 transactional gates
        // are exercised. Bearer is accepted from the header only.
        $peer = isset($_SERVER['REMOTE_ADDR']) && is_string($_SERVER['REMOTE_ADDR'])
            ? $_SERVER['REMOTE_ADDR'] : '';
        $result = $this->handle_operation('reserve', null);
        if (filter_var($peer, FILTER_VALIDATE_IP) !== false) {
            $allowed = (new WEM_Seating_Public_Abuse_Guard())->record_attempt(
                'seating:reservations:' . $peer, 30, 60
            );
            if ($allowed && $request instanceof WP_REST_Request) {
                $result = $this->handle_operation(
                    'reserve',
                    $request->get_header('X-WEM-Invitation'),
                    ['table_id' => $request->get_param('table_id')]
                );
            }
        }
        $response = new WP_REST_Response($result['body'], $result['status']);
        foreach ($result['headers'] as $name => $value) {
            $response->header($name, $value);
        }
        return $response;
    }

    public function deny_members_request($request)
    {
        $peer = isset($_SERVER['REMOTE_ADDR']) && is_string($_SERVER['REMOTE_ADDR'])
            ? $_SERVER['REMOTE_ADDR'] : '';
        $result = $this->handle_operation('members', null);
        if (filter_var($peer, FILTER_VALIDATE_IP) !== false) {
            $allowed = (new WEM_Seating_Public_Abuse_Guard())->record_attempt(
                'seating:members:' . $peer, 30, 60
            );
            if ($allowed && $request instanceof WP_REST_Request) {
                $result = $this->handle_operation('members', $request->get_header('X-WEM-Invitation'));
            }
        }
        $response = new WP_REST_Response($result['body'], $result['status']);
        foreach ($result['headers'] as $name => $value) {
            $response->header($name, $value);
        }
        return $response;
    }

    public function deny_rest_request($request)
    {
        $peer = isset($_SERVER['REMOTE_ADDR']) && is_string($_SERVER['REMOTE_ADDR'])
            ? $_SERVER['REMOTE_ADDR'] : '';
        // A missing or invalid peer cannot share an unbounded anonymous bucket.
        // Never trust X-Forwarded-For or persist the presented bearer.
        if (filter_var($peer, FILTER_VALIDATE_IP) === false) {
            $result = $this->handle_operation('availability', null);
        } else {
            $bucket = 'seating:availability:' . $peer;
            $allowed = (new WEM_Seating_Public_Abuse_Guard())->record_attempt($bucket, 30, 60);
            $bearer = $request instanceof WP_REST_Request
                ? $request->get_header('X-WEM-Invitation') : null;
            $result = $allowed
                ? $this->handle_operation('availability', $bearer)
                : $this->handle_operation('availability', null);
        }
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
        if ($operation === 'members' && is_string($bearer)) {
            try {
                $members = (new WEM_Seating_Bearer_Members())->list_for_bearer($bearer);
                if (is_array($members)) {
                    return [
                        'status' => 200,
                        'headers' => $this->security_headers(),
                        'body' => $members,
                    ];
                }
            } catch (Throwable $error) {
                return [
                    'status' => 503,
                    'headers' => $this->security_headers(),
                    'body' => ['code' => 'seating_temporarily_unavailable'],
                ];
            }
        }
        if ($operation === 'reserve') {
            // Never inspect a requested table before establishing bearer scope.
            $identity = is_string($bearer)
                ? (new WEM_Seating_Bearer_Authorization())->resolve($bearer)
                : null;
            if (is_array($identity)) {
                $tableId = $parameters['table_id'] ?? null;
                $tableId = filter_var($tableId, FILTER_VALIDATE_INT, [
                    'options' => ['min_range' => 1],
                ]);
                if ($tableId === false) {
                    return [
                        'status' => 409,
                        'headers' => $this->security_headers(),
                        'body' => ['code' => 'seating_unavailable'],
                    ];
                }

                global $wpdb;
                $tableName = WEM_Event_Table_Schema::table_name();
                $row = $wpdb->get_row($wpdb->prepare(
                    "SELECT event_term_id FROM {$tableName} WHERE id=%d",
                    $tableId
                ), ARRAY_A);
                if ($wpdb->last_error !== '') {
                    return [
                        'status' => 503,
                        'headers' => $this->security_headers(),
                        'body' => ['code' => 'seating_temporarily_unavailable'],
                    ];
                }
                if (!is_array($row) || (int)$row['event_term_id'] !== (int)$identity['event_term_id']) {
                    return [
                        'status' => 409,
                        'headers' => $this->security_headers(),
                        'body' => ['code' => 'seating_unavailable'],
                    ];
                }
                // Actual mutation remains disabled until POST and the I2
                // transactional gate are exercised outside WP_UnitTestCase.
                return [
                    'status' => 503,
                    'headers' => $this->security_headers(),
                    'body' => ['code' => 'seating_temporarily_unavailable'],
                ];
            }
        }
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
