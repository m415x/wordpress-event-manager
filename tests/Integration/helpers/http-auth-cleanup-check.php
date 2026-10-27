<?php
// Read-only isolation check for fixtures created by http-auth-fixture.php.
if (!defined('WP_CLI') || !WP_CLI) exit(1);
global $wpdb;
$checks = [
  'guest_posts' => (int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='invitado' AND post_title LIKE 'PRIVATE-T6-%'"),
  'event_terms' => (int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->terms} WHERE name LIKE 'T6-http-%'"),
  'actors' => (int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->users} WHERE user_login LIKE 'wem-http-%'"),
  'event_tables' => (int)$wpdb->get_var("SELECT COUNT(*) FROM " . WEM_Event_Table_Schema::table_name() . " WHERE table_code LIKE 'T6-HTTP-%'"),
];
if ($wpdb->last_error !== '') { fwrite(STDERR, "FAIL: fixture query error\n"); exit(1); }
foreach ($checks as $name => $count) {
    echo ($count === 0 ? 'PASS' : 'FAIL') . " fixture {$name}: {$count}\n";
}
if (array_sum($checks) !== 0) exit(1);
echo "PASS isolated HTTP fixture cleanup\n";
