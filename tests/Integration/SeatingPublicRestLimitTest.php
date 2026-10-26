<?php
declare(strict_types=1);
namespace WEM\Tests\Integration;
use WP_UnitTestCase;

/** WEM-93: bounded REST attempts and missing peer remain indistinguishable. */
final class SeatingPublicRestLimitTest extends WP_UnitTestCase
{
    public function testRateLimitAndMissingPeerStayFailClosedAndNonEnumerable(): void
    {
        global $wpdb;
        require_once dirname(__DIR__, 2) . '/wordpress-event-manager.php';
        (new \WEM_Seating_Public_Http_Route())->register_hooks();
        do_action('rest_api_init');
        $server=rest_get_server();
        $previous=$_SERVER['REMOTE_ADDR'] ?? null;
        $ip='198.51.100.91';
        $bucket='seating:availability:' . $ip;
        $key='wem_i3_rate_' . hash_hmac('sha256',$bucket,wp_salt('auth'));
        try {
            $wpdb->delete($wpdb->options,['option_name'=>$key]);
            $_SERVER['REMOTE_ADDR']=$ip;
            for($n=1;$n<=32;$n++){
                $request=new \WP_REST_Request('GET','/wem/v1/seating/availability');
                $request->set_header('X-WEM-Invitation','bad-' . $n);
                $response=$server->dispatch($request);
                self::assertSame(403,$response->get_status());
                self::assertSame(['code'=>'seating_access_unavailable'],$response->get_data());
            }
            $counter=$wpdb->get_var($wpdb->prepare(
                "SELECT option_value FROM {$wpdb->options} WHERE option_name=%s",$key
            ));
            self::assertMatchesRegularExpression('/^32:[0-9]+$/',(string)$counter);
            unset($_SERVER['REMOTE_ADDR']);
            $request=new \WP_REST_Request('GET','/wem/v1/seating/availability');
            $response=$server->dispatch($request);
            self::assertSame(403,$response->get_status());
            self::assertSame(['code'=>'seating_access_unavailable'],$response->get_data());
            self::assertSame('private, no-store',$response->get_headers()['Cache-Control'] ?? null);
        } finally {
            $wpdb->delete($wpdb->options,['option_name'=>$key]);
            $unknown='wem_i3_rate_' . hash_hmac('sha256','seating:availability:',wp_salt('auth'));
            $wpdb->delete($wpdb->options,['option_name'=>$unknown]);
            if($previous===null)unset($_SERVER['REMOTE_ADDR']);
            else $_SERVER['REMOTE_ADDR']=$previous;
        }
    }
}
