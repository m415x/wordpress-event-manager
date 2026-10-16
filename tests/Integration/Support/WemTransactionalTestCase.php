<?php

declare(strict_types=1);

namespace WEM\Tests\Integration\Support;

use WP_UnitTestCase;

/**
 * Bounded diagnostic harness: explicitly owned rows only; no global reset.
 */
abstract class WemTransactionalTestCase extends WP_UnitTestCase
{
    private $ownedOptions = array();
    private $ownedGuests = array();
    private $ownedUsers = array();
    private $ownedEvents = array();
    private $baseline = array();

    protected function setUp(): void
    {
        parent::setUp();
        global $wpdb;
        $movementTable = \WEM_Movement_Schema::table_name();
        $credentialTable = \WEM_Invitation_Credential_Schema::table_name();
        $this->baseline = array(
            'posts' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts}"),
            'options' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->options}"),
            'users' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->users}"),
            'terms' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->terms}"),
            'movements' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$movementTable}"),
            'credentials' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$credentialTable}"),
        );
    }

    protected function registerOwnedOption(string $name): void
    {
        $this->ownedOptions[] = $name;
    }

    protected function registerOwnedGuest(int $guestId): void
    {
        $this->ownedGuests[] = $guestId;
    }

    protected function registerOwnedUser(int $userId): void
    {
        $this->ownedUsers[] = $userId;
    }

    protected function registerOwnedEvent(int $eventId): void
    {
        $this->ownedEvents[] = $eventId;
    }

    protected function tearDown(): void
    {
        global $wpdb;
        $cleanupError = null;

        try {
            // Revert uncommitted writes first; explicit product COMMIT may have
            // already persisted owned fixtures, which require manual cleanup.
            $wpdb->query('ROLLBACK');

            // Remove WEM-owned rows before WordPress entities. IDs must be
            // allocated beyond historical fixtures and registered explicitly.
            $movementTable = \WEM_Movement_Schema::table_name();
            $credentialTable = \WEM_Invitation_Credential_Schema::table_name();
            foreach ($this->ownedGuests as $guestId) {
                $wpdb->delete($movementTable, array('guest_id' => $guestId), array('%d'));
                $wpdb->delete($credentialTable, array('guest_id' => $guestId), array('%d'));
                wp_delete_post($guestId, true);
            }
            foreach ($this->ownedEvents as $eventId) {
                wp_delete_term($eventId, 'evento');
            }
            if ($this->ownedUsers !== array()) {
                require_once ABSPATH . 'wp-admin/includes/user.php';
                foreach ($this->ownedUsers as $userId) {
                    wp_delete_user($userId);
                }
            }
            foreach ($this->ownedOptions as $name) {
                $wpdb->delete($wpdb->options, array('option_name' => $name), array('%s'));
            }

            foreach ($this->baseline as $name => $count) {
                $tables = array(
                    'posts' => $wpdb->posts, 'options' => $wpdb->options,
                    'users' => $wpdb->users, 'terms' => $wpdb->terms,
                    'movements' => $movementTable, 'credentials' => $credentialTable,
                );
                $table = $tables[$name];
                $actual = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
                if ($actual !== $count) {
                    throw new \RuntimeException(
                        "WEM transactional cleanup changed {$name} baseline: {$count} -> {$actual}"
                    );
                }
            }

            // With WordPress tests' autocommit=0, persist scoped cleanup before
            // the inherited tear_down issues its own ROLLBACK.
            $wpdb->query('COMMIT');
        } catch (\Throwable $error) {
            $wpdb->query('ROLLBACK');
            $cleanupError = $error;
        } finally {
            parent::tearDown();
        }

        if ($cleanupError !== null) {
            throw $cleanupError;
        }
    }
}
