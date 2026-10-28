<?php
declare(strict_types=1);
namespace WEM\Tests\Integration;
use WP_UnitTestCase;

/** Post-auth 409 must be distinguishable, without making a reservation. */
final class SeatingPublicReservationAuthorizedRestTest extends WP_UnitTestCase
{
    public function testAuthenticatedPostReportsUnavailableTableWithoutMutation(): void
    {
        global $wpdb;
        require_once dirname(__DIR__, 2) . '/wordpress-event-manager.php';
        if (!taxonomy_exists('evento')) (new \WEM_Taxonomy_Manager())->register_taxonomy();
        if (!post_type_exists('invitado')) (new \WEM_CPT_Manager())->register_cpt();
        \WEM_Invitation_Credential_Schema::install();
        $admin=self::factory()->user->create(['role'=>'administrator']);
        $event=self::factory()->term->create(['taxonomy'=>'evento','name'=>'T6-'.wp_generate_uuid4()]);
        $guest=self::factory()->post->create(['post_type'=>'invitado','post_status'=>'publish']);
        wp_set_object_terms($guest,[$event],'evento',false);
        $wpdb->delete(\WEM_Invitation_Credential_Schema::table_name(),['guest_id'=>$guest]);
        $token=(new \WEM_Invitation_Credential_Service())->issue($guest,$admin)['token'];
        (new \WEM_Seating_Public_Http_Route())->register_hooks();
        do_action('rest_api_init');
        $previous=$_SERVER['REMOTE_ADDR']??null;
        $peer='198.51.100.167';
        $key='wem_i3_rate_'.hash_hmac('sha256','seating:reservations:'.$peer,wp_salt('auth'));
        try {
            $_SERVER['REMOTE_ADDR']=$peer;
            $wpdb->delete($wpdb->options,['option_name'=>$key]);
            $request=new \WP_REST_Request('POST','/wem/v1/seating/reservations');
            $request->set_header('X-WEM-Invitation',$token);
            $request->set_param('table_id',999999);
            $response=rest_get_server()->dispatch($request);
            self::assertSame(409,$response->get_status());
            self::assertSame(['code'=>'seating_unavailable'],$response->get_data());
            self::assertStringNotContainsString($token,wp_json_encode($response->get_data()));
        } finally {
            $wpdb->delete($wpdb->options,['option_name'=>$key]);
            $wpdb->delete(\WEM_Invitation_Credential_Schema::table_name(),['guest_id'=>$guest]);
            if($previous===null)unset($_SERVER['REMOTE_ADDR']);else $_SERVER['REMOTE_ADDR']=$previous;
        }
    }
}
