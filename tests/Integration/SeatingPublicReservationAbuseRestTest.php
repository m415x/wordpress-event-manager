<?php
declare(strict_types=1);
namespace WEM\Tests\Integration;
use WP_UnitTestCase;

/** Even disabled public write routes must be bounded before enabling mutations. */
final class SeatingPublicReservationAbuseRestTest extends WP_UnitTestCase
{
    public function testDeniedPostsConsumeSharedServerPeerQuota(): void
    {
        global $wpdb;
        require_once dirname(__DIR__, 2) . '/wordpress-event-manager.php';
        (new \WEM_Seating_Public_Http_Route())->register_hooks();
        do_action('rest_api_init');
        $previous=$_SERVER['REMOTE_ADDR']??null;
        $peer='198.51.100.156';
        $key='wem_i3_rate_'.hash_hmac('sha256','seating:reservations:'.$peer,wp_salt('auth'));
        try {
            $_SERVER['REMOTE_ADDR']=$peer;
            $wpdb->delete($wpdb->options,['option_name'=>$key]);
            $request=new \WP_REST_Request('POST','/wem/v1/seating/reservations');
            $request->set_param('table_id',1);
            $request->set_param('bearer',str_repeat('A',43));
            $response=rest_get_server()->dispatch($request);
            self::assertSame(403,$response->get_status());
            self::assertSame(['code'=>'seating_access_unavailable'],$response->get_data());
            self::assertSame('private, no-store',$response->get_headers()['Cache-Control']??null);
            self::assertMatchesRegularExpression('/^1:[0-9]+$/',(string)$wpdb->get_var($wpdb->prepare(
                "SELECT option_value FROM {$wpdb->options} WHERE option_name=%s",$key
            )));
        } finally {
            $wpdb->delete($wpdb->options,['option_name'=>$key]);
            if($previous===null) unset($_SERVER['REMOTE_ADDR']);
            else $_SERVER['REMOTE_ADDR']=$previous;
        }
    }
}
