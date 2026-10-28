<?php
declare(strict_types=1);
namespace WEM\Tests\Integration;
use WP_UnitTestCase;

/** WEM-93: business errors must remain distinguishable only after bearer authorization. */
final class SeatingPublicPostAuthErrorTest extends WP_UnitTestCase
{
    public function testInvalidBearerCannotLearnIfTableExists(): void
    {
        require_once dirname(__DIR__, 2) . '/wordpress-event-manager.php';
        $route=new \WEM_Seating_Public_Http_Route();
        $missing=$route->handle_operation('reserve','invalid',['table_id'=>999999]);
        $other=$route->handle_operation('reserve',str_repeat('A',43),['table_id'=>1]);
        self::assertSame(403,$missing['status']);
        self::assertSame(['code'=>'seating_access_unavailable'],$missing['body']);
        self::assertSame($missing,$other);
    }

    public function testAuthorizedBearerGetsNonSecretBusinessDenialForUnavailableTable(): void
    {
        global $wpdb;
        require_once dirname(__DIR__, 2) . '/wordpress-event-manager.php';
        if(!taxonomy_exists('evento'))(new \WEM_Taxonomy_Manager())->register_taxonomy();
        if(!post_type_exists('invitado'))(new \WEM_CPT_Manager())->register_cpt();
        \WEM_Invitation_Credential_Schema::install();
        $admin=self::factory()->user->create(['role'=>'administrator']);
        $event=self::factory()->term->create(['taxonomy'=>'evento','name'=>'T6-'.wp_generate_uuid4()]);
        $guest=self::factory()->post->create(['post_type'=>'invitado','post_status'=>'publish']);
        wp_set_object_terms($guest,[$event],'evento',false);
        $wpdb->delete(\WEM_Invitation_Credential_Schema::table_name(),['guest_id'=>$guest]);
        $token=(new \WEM_Invitation_Credential_Service())->issue($guest,$admin)['token'];
        try {
            $response=(new \WEM_Seating_Public_Http_Route())->handle_operation('reserve',$token,['table_id'=>999999]);
            self::assertSame(409,$response['status']);
            self::assertSame(['code'=>'seating_unavailable'],$response['body']);
            self::assertStringNotContainsString($token,wp_json_encode($response));
        } finally {
            $wpdb->delete(\WEM_Invitation_Credential_Schema::table_name(),['guest_id'=>$guest]);
        }
    }
}
