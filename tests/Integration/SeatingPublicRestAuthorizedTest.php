<?php
declare(strict_types=1);
namespace WEM\Tests\Integration;
use WP_UnitTestCase;

final class SeatingPublicRestAuthorizedTest extends WP_UnitTestCase
{
    public function testRestUsesBearerOnlyAfterValidPeerAndAbuseGate(): void
    {
        global $wpdb;
        require_once dirname(__DIR__, 2) . '/wordpress-event-manager.php';
        if (!taxonomy_exists('evento')) {(new \WEM_Taxonomy_Manager())->register_taxonomy();}
        if (!post_type_exists('invitado')) {(new \WEM_CPT_Manager())->register_cpt();}
        \WEM_Invitation_Credential_Schema::install();
        \WEM_Event_Table_Schema::install();
        $admin=self::factory()->user->create(['role'=>'administrator']);
        $event=self::factory()->term->create(['taxonomy'=>'evento','name'=>'T6-' . wp_generate_uuid4()]);
        $guest=self::factory()->post->create(['post_type'=>'invitado','post_status'=>'publish']);
        wp_set_object_terms($guest,[$event],'evento',false);
        $wpdb->delete(\WEM_Invitation_Credential_Schema::table_name(),['guest_id'=>$guest]);
        $bearer=(new \WEM_Invitation_Credential_Service())->issue($guest,$admin)['token'];
        (new \WEM_Seating_Public_Http_Route())->register_hooks();
        do_action('rest_api_init');
        $prior=$_SERVER['REMOTE_ADDR']??null;
        $ip='198.51.100.101';
        $option='wem_i3_rate_' . hash_hmac('sha256','seating:availability:'.$ip,wp_salt('auth'));
        try {
            $_SERVER['REMOTE_ADDR']=$ip;
            $wpdb->delete($wpdb->options,['option_name'=>$option]);
            $request=new \WP_REST_Request('GET','/wem/v1/seating/availability');
            $request->set_header('X-WEM-Invitation',$bearer);
            $response=rest_get_server()->dispatch($request);
            self::assertSame(200,$response->get_status());
            self::assertIsArray($response->get_data());
            self::assertStringNotContainsString($bearer,wp_json_encode($response->get_data()));
            self::assertSame('private, no-store',$response->get_headers()['Cache-Control']??null);
            self::assertMatchesRegularExpression('/^1:[0-9]+$/',(string)$wpdb->get_var($wpdb->prepare(
                "SELECT option_value FROM {$wpdb->options} WHERE option_name=%s",$option
            )));
        } finally {
            $wpdb->delete($wpdb->options,['option_name'=>$option]);
            $wpdb->delete(\WEM_Invitation_Credential_Schema::table_name(),['guest_id'=>$guest]);
            if($prior===null)unset($_SERVER['REMOTE_ADDR']);else $_SERVER['REMOTE_ADDR']=$prior;
        }
    }
}
