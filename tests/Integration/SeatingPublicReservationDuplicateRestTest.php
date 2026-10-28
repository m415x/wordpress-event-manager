<?php
declare(strict_types=1);
namespace WEM\Tests\Integration;
use PHPUnit\Framework\TestCase;

/** WEM-93/T6: duplicate public POST must never create a second reservation. */
final class SeatingPublicReservationDuplicateRestTest extends TestCase
{
    public function testSecondPostIsConflictAndPreservesSingleReservation(): void
    {
        global $wpdb;
        require_once dirname(__DIR__,2).'/wordpress-event-manager.php';
        if (!taxonomy_exists('evento')) (new \WEM_Taxonomy_Manager())->register_taxonomy();
        if (!post_type_exists('invitado')) (new \WEM_CPT_Manager())->register_cpt();
        self::assertSame(0,(int)$wpdb->get_var('SELECT @@in_transaction'));
        $guest=$event=$table=$admin=0;
        $previous=$_SERVER['REMOTE_ADDR']??null;
        $peer='198.51.100.191';
        $key='wem_i3_rate_'.hash_hmac('sha256','seating:reservations:'.$peer,wp_salt('auth'));
        try {
            $term=wp_insert_term('T6-duplicate-'.wp_generate_uuid4(),'evento');
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
            $first=rest_get_server()->dispatch($request);
            self::assertSame(201,$first->get_status());
            $second=rest_get_server()->dispatch($request);
            self::assertSame(409,$second->get_status());
            self::assertSame(['code'=>'seating_unavailable'],$second->get_data());
            self::assertSame(1,(int)$wpdb->get_var($wpdb->prepare(
                'SELECT COUNT(*) FROM '.\WEM_Table_Reservation_Schema::table_name().
                " WHERE guest_id=%d AND status='active'",$guest
            )));
        } finally {
            $wpdb->delete($wpdb->options,['option_name'=>$key]);
            if($guest>0){
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
