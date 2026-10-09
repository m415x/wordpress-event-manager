<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * WEM-65: one-shot administrative presentation of newly issued credentials.
 *
 * WEM-33 remains the sole authority for credential persistence and lifecycle.
 * No bearer or QR is retained, logged, or made recoverable.
 */
final class WEM_Admin_Invitation_QR_Orchestrator
{
    private $credential_service;
    private $encoder;

    public function __construct($credential_service = null, $encoder = null)
    {
        $this->credential_service = $credential_service ?? new WEM_Invitation_Credential_Service();
        $this->encoder = $encoder ?? new WEM_Local_QR_Encoder();
    }

    /**
     * @return array{url: string, svg: string, generation: int, headers: array<string, string>}
     */
    public function issue($guest_id, $actor_user_id): array
    {
        return $this->perform('issue', $guest_id, $actor_user_id);
    }

    public function rotate($guest_id, $actor_user_id): array
    {
        return $this->perform('rotate', $guest_id, $actor_user_id);
    }

    public function reissue($guest_id, $actor_user_id): array
    {
        return $this->perform('reissue', $guest_id, $actor_user_id);
    }

    private function perform(string $operation, $guest_id, $actor_user_id): array
    {
        $actor_user_id = (int) $actor_user_id;
        if ($actor_user_id <= 0 || !user_can($actor_user_id, 'manage_options')) {
            throw new RuntimeException('Invitation QR administration is not authorized.');
        }

        // This commits the WEM-33 credential before QR rendering starts.
        // Rendering failures never revoke, rotate, or compensate the commit.
        $issued = $this->credential_service->{$operation}((int) $guest_id, $actor_user_id);
        $token = $issued['token'] ?? null;
        if (!is_string($token) || !preg_match('/^[A-Za-z0-9_-]{43}$/D', $token)) {
            throw new RuntimeException('Credential issuance did not provide a valid bearer.');
        }

        // Only the WEM-33 read-only endpoint: compatible with simple permalinks.
        $url = add_query_arg('wem_invitation', $token, home_url('/'));
        $svg = $this->encoder->render_svg($url);

        return array(
            'url' => $url,
            'svg' => $svg,
            'generation' => (int) $issued['generation'],
            // The authenticated transport must emit these on its one-shot response.
            // No credential-bearing response may be cached or indexed.
            'headers' => array(
                'Cache-Control' => 'private, no-store',
                'Referrer-Policy' => 'no-referrer',
                'X-Robots-Tag' => 'noindex, nofollow, noarchive',
            ),
        );
    }
}
