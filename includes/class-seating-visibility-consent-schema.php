<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Canonical, event-scoped guest preference for public member visibility.
 *
 * No row and visibility_opt_in=0 both mean private. A guest's preference
 * is independent from invitation credential generations and reservations.
 */
final class WEM_Seating_Visibility_Consent_Schema
{
    public const TABLE_SUFFIX = 'wem_seating_visibility_consent';

    public static function table_name()
    {
        global $wpdb;

        return $wpdb->prefix . self::TABLE_SUFFIX;
    }

    public static function install()
    {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $table_name = self::table_name();
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table_name} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            guest_id BIGINT UNSIGNED NOT NULL,
            event_term_id BIGINT UNSIGNED NOT NULL,
            visibility_opt_in TINYINT UNSIGNED NOT NULL DEFAULT 0,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY guest_event (guest_id, event_term_id),
            KEY event_term_id (event_term_id)
        ) {$charset_collate};";

        dbDelta($sql);

        // dbDelta cannot parse CHECK; SHOW CREATE TABLE sees temporary tables.
        $constraint = 'wem_seating_consent_binary';
        $definition = self::table_definition($table_name);
        $name_pattern = '/CONSTRAINT\\s+[`"]?' . $constraint . '[`"]?\\s+CHECK\\s*\\(/i';
        if (!preg_match($name_pattern, $definition)) {
            $added = $wpdb->query("ALTER TABLE {$table_name} ADD CONSTRAINT {$constraint} CHECK (visibility_opt_in IN (0, 1))");
            if ($added === false) {
                throw new RuntimeException('Could not install binary visibility consent constraint.');
            }
            $definition = self::table_definition($table_name);
        }
        $expected = '/CONSTRAINT\\s+[`"]?' . $constraint
            . '[`"]?\\s+CHECK\\s*\\(\\s*\\(*\\s*[`"]?visibility_opt_in[`"]?\\s+IN\\s*\\(\\s*0\\s*,\\s*1\\s*\\)\\s*\\)*\\s*\\)/i';
        if (!preg_match($expected, $definition)) {
            throw new RuntimeException('Binary visibility consent constraint is absent or inconsistent.');
        }
    }
    private static function table_definition($table_name)
    {
        global $wpdb;
        $row = $wpdb->get_row("SHOW CREATE TABLE {$table_name}", ARRAY_N);
        if (!is_array($row) || !isset($row[1])) {
            throw new RuntimeException('Could not inspect visibility consent schema.');
        }
        return (string) $row[1];
    }
}
