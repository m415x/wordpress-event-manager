<?php

/**
 * WEM-73 WordPress-side administrative issuance boundary.
 * All authorization is checked before delegating to the existing issuer.
 */
final class WEM_Invitation_Bulk_WordPress_Scope
{
    public function run(int $actor_id, int $guest_id, int $event_id, callable $issue)
    {
        $authorizer = new WEM_Invitation_Bulk_Issuance_Authorizer(
            static function (int $user_id): bool {
                return user_can($user_id, 'manage_options');
            },
            static function (int $guest, int $event): bool {
                if (get_post_type($guest) !== 'invitado' || get_post_status($guest) !== 'publish'
                    || !term_exists($event, 'evento')) {
                    return false;
                }
                $terms = wp_get_object_terms($guest, 'evento', array('fields' => 'ids'));
                return !is_wp_error($terms)
                    && count($terms) === 1
                    && (int) $terms[0] === $event;
            }
        );

        return $authorizer->run($actor_id, $guest_id, $event_id, $issue);
    }
}
