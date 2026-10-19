<?php
/**
 * WEM-68 one-off HTTP lifecycle verification, run ONLY with wp-env test 8890.
 * No token is logged, exported, retained, or printed.
 */
if (!defined('ABSPATH') || PHP_SAPI !== 'cli') { exit(2); }
global $wpdb;
$plugin = 'wordpress-event-manager-clean/wordpress-event-manager.php';
if (home_url('/') !== 'http://localhost:8890/' ||
    (string) DB_NAME !== 'wordpress' ||
    $wpdb->prefix !== 'wp_' ||
    !in_array($plugin, (array) get_option('active_plugins', []), true)) {
    fwrite(STDERR, "DENY: unexpected site, database, prefix or plugin identity\n");
    exit(2);
}
if (!class_exists('WEM_Invitation_Credential_Service')) {
    fwrite(STDERR, "DENY: WEM credential runtime unavailable\n"); exit(2);
}
$base = 'http://wordpress/';
$check = static function ($token, $expect) use ($base) {
    $url = add_query_arg('wem_invitation', $token, $base);
    $response = wp_remote_get($url, [
        'timeout' => 8, 'redirection' => 0, 'sslverify' => false,
        // Keep the canonical external Host while connecting through Docker DNS.
        'headers' => ['Host' => 'localhost:8890'],
    ]);
    if (is_wp_error($response)) {
        throw new RuntimeException('Local container HTTP request failed (no bearer details).');
    }
    $status = (int) wp_remote_retrieve_response_code($response);
    $body = (string) wp_remote_retrieve_body($response);
    $headers = wp_remote_retrieve_headers($response);
    if ($status !== $expect ||
        (string) ($headers['referrer-policy'] ?? '') !== 'no-referrer' ||
        strpos((string) ($headers['cache-control'] ?? ''), 'no-store') === false ||
        strpos((string) ($headers['x-robots-tag'] ?? ''), 'noindex') === false) {
        throw new RuntimeException("Unexpected HTTP status/security headers; expected $expect, received $status.");
    }
    if ($expect === 404 && $body !== '') {
        throw new RuntimeException('Denied token exposed non-neutral response body.');
    }
    if ($expect === 200 && strpos($body, 'WEM-68') === false) {
        throw new RuntimeException('Active token did not render expected public invitation.');
    }
};

$guest = 0;
$term = 0;
$failure_message = null;
try {
    $check('bad', 404);
    echo "HTTP PREFLIGHT 404: PASS\n";
    $admins = get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID']);
    $actor = (int) ($admins[0] ?? 0);
    if ($actor <= 0 || !user_can($actor, 'manage_options')) {
        throw new RuntimeException('No eligible existing admin for isolated test.');
    }
    $created = wp_insert_term('WEM-68-HTTP-' . wp_generate_password(10, false), 'evento');
    if (is_wp_error($created)) throw new RuntimeException('Cannot create disposable event.');
    $term = (int) $created['term_id'];
    $guest = wp_insert_post([
        'post_type' => 'invitado', 'post_status' => 'publish',
        'post_title' => 'WEM-68-HTTP-GUEST',
    ], true);
    if (is_wp_error($guest) || !$guest) throw new RuntimeException('Cannot create disposable guest.');
    wp_set_object_terms($guest, [$term], 'evento');
    $service = new WEM_Invitation_Credential_Service();
    $first = $service->issue($guest, $actor);
    $check($first['token'], 200);
    echo "ISSUE / HTTP 200: PASS\n";
    $second = $service->rotate($guest, $actor);
    $check($first['token'], 404);
    $check($second['token'], 200);
    echo "ROTATE / OLD 404 / NEW 200: PASS\n";
    $service->revoke($guest, $actor);
    $check($second['token'], 404);
    echo "REVOKE / OLD 404: PASS\n";
    $third = $service->reissue($guest, $actor);
    $check($third['token'], 200);
    $check($first['token'], 404);
    $check($second['token'], 404);
    echo "REISSUE / NEW 200 / PREVIOUS 404: PASS\n";
    echo "WEM-68 HTTP LIFECYCLE: PASS\n";
} catch (Throwable $failure) {
    $failure_message = $failure->getMessage();
} finally {
    // Only remove disposable guest-specific credentials, post and event term.
    if (is_int($guest) && $guest > 0) {
        $table = WEM_Invitation_Credential_Schema::table_name();
        $wpdb->delete($table, ['guest_id' => $guest], ['%d']);
        wp_delete_post($guest, true);
    }
    if ($term > 0) wp_delete_term($term, 'evento');
}

if ($failure_message !== null) {
    fwrite(STDERR, "WEM-68 HTTP LIFECYCLE: FAIL — " . $failure_message . "\n");
    exit(1);
}
