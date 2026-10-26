<?php
declare(strict_types=1);
namespace WEM\Tests\Integration;
use WP_UnitTestCase;

/** WEM-93 RED: genuine bearer must cross authorization before business response. */
final class SeatingPublicAuthorizedResponseTest extends WP_UnitTestCase
{
    public function testAuthorizedBearerGetsOwnAvailabilityNotGenericAuthenticationDenial(): void
    {
        require_once dirname(__DIR__, 2) . '/wordpress-event-manager.php';
        if (!taxonomy_exists('evento')) {(new \WEM_Taxonomy_Manager())->register_taxonomy();}
        if (!post_type_exists('invitado')) {(new \WEM_CPT_Manager())->register_cpt();}
        \WEM_Invitation_Credential_Schema::install();
        \WEM_Event_Table_Schema::install();
        $admin=self::factory()->user->create(['role'=>'administrator']);
        $event=self::factory()->term->create(['taxonomy'=>'evento','name'=>'T6-' . wp_generate_uuid4()]);
        $guest=self::factory()->post->create(['post_type'=>'invitado','post_status'=>'publish']);
        wp_set_object_terms($guest,[$event],'evento',false);
        $issued=(new \WEM_Invitation_Credential_Service())->issue($guest,$admin);
        $result=(new \WEM_Seating_Public_Http_Route())->handle_operation('availability',$issued['token'],[]);
        self::assertSame(200,$result['status'],'Valid bearer must not be treated as unknown.');
        self::assertIsArray($result['body']);
        self::assertSame('private, no-store',$result['headers']['Cache-Control']);
        self::assertStringNotContainsString($issued['token'],wp_json_encode($result));
    }
}
