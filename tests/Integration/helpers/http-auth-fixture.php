<?php
// Isolated WEM-93 HTTP acceptance fixture. Invoked exclusively through WP-CLI.
if (!defined('WP_CLI') || !WP_CLI) { exit(1); }
global $wpdb;
$mode = $args[0] ?? '';
if ($mode === 'create') {
    if (!taxonomy_exists('evento')) (new WEM_Taxonomy_Manager())->register_taxonomy();
    if (!post_type_exists('invitado')) (new WEM_CPT_Manager())->register_cpt();
    $term = wp_insert_term('T6-http-' . wp_generate_uuid4(), 'evento');
    if (is_wp_error($term)) throw new RuntimeException('Fixture term failed');
    $event = (int)$term['term_id'];
    $guest = (int)wp_insert_post(['post_type'=>'invitado', 'post_status'=>'publish', 'post_title'=>'PRIVATE-T6-'.wp_generate_uuid4()]);
    if ($guest <= 0) throw new RuntimeException('Fixture guest failed');
    wp_set_object_terms($guest, [$event], 'evento', false);
    if ($wpdb->insert(WEM_Event_Table_Schema::table_name(), [
        'event_term_id'=>$event, 'table_code'=>'T6-HTTP-'.wp_generate_uuid4(), 'capacity'=>2,
    ]) !== 1) throw new RuntimeException('Fixture table failed');
    $table=(int)$wpdb->insert_id;
    $admin=(int)wp_create_user('wem-http-'.wp_generate_uuid4(),wp_generate_password(32),'wem-http-'.wp_generate_uuid4().'@example.invalid');
    if ($admin <= 0) throw new RuntimeException('Fixture actor failed');
    (new WP_User($admin))->set_role('administrator');
    $token=(new WEM_Invitation_Credential_Service())->issue($guest,$admin)['token'];
    // Captured privately by shell, never emitted to test logs.
    echo implode('|',[$token,$guest,$event,$table,$admin]);
} elseif ($mode === 'cleanup') {
    [$guest,$event,$table,$admin]=array_map('intval',array_slice($args,1,4));
    if ($guest > 0) {
        $wpdb->delete(WEM_Table_Reservation_Schema::table_name(), ['guest_id'=>$guest]);
        $wpdb->delete(WEM_Invitation_Credential_Schema::table_name(), ['guest_id'=>$guest]);
    }
    if ($table > 0) $wpdb->delete(WEM_Event_Table_Schema::table_name(),['id'=>$table]);
    if ($guest > 0) wp_delete_post($guest,true);
    if ($admin > 0) wp_delete_user($admin);
    if ($event > 0) wp_delete_term($event,'evento');
    echo 'CLEANED';
} else { throw new InvalidArgumentException('Mode required'); }
