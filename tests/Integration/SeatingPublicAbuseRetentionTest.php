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
        global $wpdb;
        $guard=new \WEM_Seating_Public_Abuse_Guard();
        $prefix='wem_i3_rate_';
        $nonce=wp_generate_uuid4();
        $expired=[]; $active=$prefix . hash('sha256', $nonce . '-active');
        try {
            foreach (range(1,3) as $i) {
                $name=$prefix . hash('sha256',$nonce . '-expired-' . $i);
                $expired[]=$name;
                self::assertSame(1,$wpdb->insert($wpdb->options,[
                    'option_name'=>$name,'option_value'=>'5:' . (time()-120),'autoload'=>'no'
                ]));
            }
            self::assertSame(1,$wpdb->insert($wpdb->options,[
                'option_name'=>$active,'option_value'=>'1:' . (time()+3600),'autoload'=>'no'
            ]));
            self::assertSame(2,$guard->purge_expired(2));
            $remaining=0;
            foreach ($expired as $name) {
                $remaining+=(int)$wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name=%s",$name
                ));
            }
            self::assertSame(1,$remaining);
            self::assertSame(1,$guard->purge_expired(2));
            self::assertSame(0,$guard->purge_expired(2));
            self::assertSame(1,(int)$wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name=%s",$active
            )));
            self::assertFalse($guard->purge_expired(0));
            self::assertFalse($guard->purge_expired(501));
        } finally {
            foreach (array_merge($expired,[$active]) as $name) {
                $wpdb->delete($wpdb->options,['option_name'=>$name]);
            }
        }
    }
}
