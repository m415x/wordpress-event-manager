<?php
declare(strict_types=1);
namespace WEM\Tests\Integration;
use PHPUnit\Framework\TestCase;

/** WEM-93: expiry and bounded retention; no HTTP endpoint enabled. */
final class SeatingPublicAbuseRetentionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        require_once dirname(__DIR__, 2) . '/wordpress-event-manager.php';
    }

    public function testExpiredBucketAllowsNewWindowWithoutReusingOldCount(): void
    {
        global $wpdb;
        $key='wem-t6-' . wp_generate_uuid4();
        $option='wem_i3_rate_' . hash_hmac('sha256',$key,wp_salt('auth'));
        try {
            $wpdb->insert($wpdb->options,[
                'option_name'=>$option,
                'option_value'=>'999:' . (time()-5),
                'autoload'=>'no'
            ]);
            self::assertTrue((new \WEM_Seating_Public_Abuse_Guard())->record_attempt($key,1,60));
            self::assertFalse((new \WEM_Seating_Public_Abuse_Guard())->record_attempt($key,1,60));
        } finally {
            $wpdb->delete($wpdb->options,['option_name'=>$option]);
        }
    }

    public function testExpiredCounterRetentionHasBoundedCleanup(): void
    {
        $guard=new \WEM_Seating_Public_Abuse_Guard();
        self::assertTrue(method_exists($guard,'purge_expired'),
            'Missing bounded expired rate-bucket cleanup.');
    }
}
