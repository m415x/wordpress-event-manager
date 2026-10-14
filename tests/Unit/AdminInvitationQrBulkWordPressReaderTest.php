<?php

declare(strict_types=1);

namespace {
    if (!defined('ABSPATH')) {
        define('ABSPATH', '/tmp/wem66-bulk-wp-reader/');
    }
    if (!function_exists('get_post_type')) {
        function get_post_type($id) {
            return $GLOBALS['wem66_bulk_reader_guest_types'][$id] ?? false;
        }
    }
}

namespace WEM\Tests\Unit {
    use PHPUnit\Framework\TestCase;

    /** A fresh, read-only projection from WordPress, without bearer material. */
    final class AdminInvitationQrBulkWordPressReaderTest extends TestCase
    {
        protected function setUp(): void
        {
            require_once dirname(__DIR__, 2) . '/includes/class-invitation-credential-schema.php';
            require_once dirname(__DIR__, 2) . '/includes/class-admin-invitation-qr-bulk-wordpress-reader.php';
            $GLOBALS['wem66_bulk_reader_guest_types'] = array(11 => 'invitado', 12 => 'post');
        }

        public function testValidGuestReadsOnlyCredentialStatusAndActiveSlot(): void
        {
            $wpdb = new class {
                public $prefix = 'wp_';
                public $queries = array();
                public function prepare($sql, $id) {
                    $this->queries[] = array($sql, $id);
                    return 'prepared query';
                }
                public function get_results($query, $output) {
                    return array(array('status' => 'active', 'active_slot' => 1));
                }
            };
            $reader = new \WEM_Admin_Invitation_QR_Bulk_WordPress_Reader($wpdb);

            self::assertSame(
                array('valid_guest' => true, 'credentials' => array(array('status' => 'active', 'active_slot' => 1))),
                $reader->read(11)
            );
            self::assertCount(1, $wpdb->queries);
            self::assertSame(11, $wpdb->queries[0][1]);
            self::assertStringContainsString('wp_wem_invitation_credentials', $wpdb->queries[0][0]);
            self::assertStringContainsString('status, active_slot', $wpdb->queries[0][0]);
            self::assertStringNotContainsString('token_digest', $wpdb->queries[0][0]);
        }

        public function testInvalidGuestIsRejectedWithoutCredentialQuery(): void
        {
            $wpdb = new class {
                public $prefix = 'wp_';
                public $calls = 0;
                public function prepare($sql, $id) { ++$this->calls; return $sql; }
                public function get_results($sql, $output) { ++$this->calls; return array(); }
            };
            $reader = new \WEM_Admin_Invitation_QR_Bulk_WordPress_Reader($wpdb);
            self::assertSame(array('valid_guest' => false, 'credentials' => array()), $reader->read(12));
            self::assertSame(0, $wpdb->calls);
        }

        public function testDatabaseReadFailureFailsClosed(): void
        {
            $wpdb = new class {
                public $prefix = 'wp_';
                public function prepare($sql, $id) { return $sql; }
                public function get_results($sql, $output) { return null; }
            };
            $reader = new \WEM_Admin_Invitation_QR_Bulk_WordPress_Reader($wpdb);
            $this->expectException(\RuntimeException::class);
            $reader->read(11);
        }
    }
}
