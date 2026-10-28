<?php
declare(strict_types=1);
namespace WEM\Tests\Integration;
use PHPUnit\Framework\TestCase;

/** WEM-93: public transport must delegate real writes to the I2 transaction. */
final class SeatingPublicReservationCommitRestTest extends TestCase
{
    public function testAuthorizedPostCreatesExactlyOneDurableReservation(): void
    {
        global $wpdb;
        require_once dirname(__DIR__,2).'/wordpress-event-manager.php';
        if (!taxonomy_exists('evento')) (new \WEM_Taxonomy_Manager())->register_taxonomy();
        if (!post_type_exists('invitado')) (new \WEM_CPT_Manager())->register_cpt();
        self::assertSame(0,(int)$wpdb->get_var('SELECT @@in_transaction'));
        $guest=$event=$table=$admin=0;
        $previous=$_SERVER['REMOTE_ADDR']??null;
        $peer='198.51.100.183';
        $key='wem_i3_rate_'.hash_hmac('sha256','seating:reservations:'.$peer,wp_salt('auth'));
        try {
            $term=wp_insert_term('T6-'.wp_generate_uuid4(),'evento');
            self::assertFalse(is_wp_error($term));
            $event=(int)$term['term_id'];
            $guest=(int)wp_insert_post(['post_type'=>'invitado','post_status'=>'publish','post_title'=>'T6-'.wp_generate_uuid4()]);
            self::assertGreaterThan(0,$guest);
            wp_set_object_terms($guest,[$event],'evento',false);
            self::assertSame(1,$wpdb->insert(\WEM_Event_Table_Schema::table_name(),[
                'event_term_id'=>$event,'table_code'=>'T6-'.wp_generate_uuid4(),'capacity'=>2
            ]));
            $table=(int)$wpdb->insert_id;
            $admin=(int)wp_create_user('wem-t6-'.wp_generate_uuid4(),wp_generate_password(32),'wem-t6-'.wp_generate_uuid4().'@example.invalid');
            (new \WP_User($admin))->set_role('administrator');
            $token=(new \WEM_Invitation_Credential_Service())->issue($guest,$admin)['token'];
            $_SERVER['REMOTE_ADDR']=$peer;
            $wpdb->delete($wpdb->options,['option_name'=>$key]);
            (new \WEM_Seating_Public_Http_Route())->register_hooks();
            do_action('rest_api_init');
            $request=new \WP_REST_Request('POST','/wem/v1/seating/reservations');
            $request->set_header('X-WEM-Invitation',$token);
            $request->set_param('table_id',$table);
            $response=rest_get_server()->dispatch($request);
            self::assertSame(201,$response->get_status());
            self::assertSame(['code' => 'seating_reserved'], $response->get_data(),
                'Public 201 must exclude reservation_id, guest_id, table_id, event IDs and internal identifiers.');
            self::assertStringNotContainsString($token,wp_json_encode($response->get_data()));
            self::assertSame(1,(int)$wpdb->get_var($wpdb->prepare(
                'SELECT COUNT(*) FROM '.\WEM_Table_Reservation_Schema::table_name().
                " WHERE guest_id=%d AND table_id=%d AND status='active'",$guest,$table
            )));
        } finally {
            $wpdb->delete($wpdb->options,['option_name'=>$key]);
            if($guest>0) {
                $wpdb->delete(\WEM_Table_Reservation_Schema::table_name(),['guest_id'=>$guest]);
                $wpdb->delete(\WEM_Invitation_Credential_Schema::table_name(),['guest_id'=>$guest]);
            }
            if($table>0)$wpdb->delete(\WEM_Event_Table_Schema::table_name(),['id'=>$table]);
            if($guest>0)wp_delete_post($guest,true);
            if($admin>0)wp_delete_user($admin);
            if($event>0)wp_delete_term($event,'evento');
            if($previous===null)unset($_SERVER['REMOTE_ADDR']);else $_SERVER['REMOTE_ADDR']=$previous;
        }
    }
}
