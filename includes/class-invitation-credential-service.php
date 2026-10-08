<?php

if (!defined('ABSPATH')) {
    exit;
}

final class WEM_Invitation_Credential_Service
{
    public function issue($guest_id, $actor_user_id)
    {
        global $wpdb;

        $guest_id = (int) $guest_id;
        $actor_user_id = (int) $actor_user_id;

        if ($guest_id <= 0 || get_post_type($guest_id) !== 'invitado') {
            throw new RuntimeException('Invitation guest is invalid.');
        }

        if ($actor_user_id <= 0 || !user_can($actor_user_id, 'manage_options')) {
            throw new RuntimeException('Credential administration is not authorized.');
        }

        $wpdb->query('START TRANSACTION');

        try {
            $locked_guest_id = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT ID FROM {$wpdb->posts} WHERE ID = %d FOR UPDATE",
                    $guest_id
                )
            );

            if ((int) $locked_guest_id !== $guest_id) {
                throw new RuntimeException('Invitation guest could not be serialized.');
            }

            $table_name = WEM_Invitation_Credential_Schema::table_name();
            $this->assert_guest_credential_state_is_consistent($table_name, $guest_id);

            $existing_count = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*)
                    FROM {$table_name}
                    WHERE guest_id = %d",
                    $guest_id
                )
            );

            if ($existing_count > 0) {
                throw new RuntimeException('Invitation credential has already been issued.');
            }

            $active_sequence = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT sequence
                    FROM {$table_name}
                    WHERE guest_id = %d
                      AND status = %s
                      AND active_slot = %d
                    LIMIT 1",
                    $guest_id,
                    'active',
                    1
                )
            );

            if ($active_sequence !== null) {
                throw new RuntimeException('Invitation already has an active credential.');
            }

            $generation = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COALESCE(MAX(generation), 0) + 1
                    FROM {$table_name}
                    WHERE guest_id = %d",
                    $guest_id
                )
            );

            $token = $this->generate_token();
            $digest = hash('sha256', $token);
            $issued_at = current_time('Y-m-d H:i:s');

            $inserted = $wpdb->insert(
                $table_name,
                array(
                    'guest_id' => $guest_id,
                    'generation' => $generation,
                    'token_digest' => $digest,
                    'status' => 'active',
                    'active_slot' => 1,
                    'issued_at' => $issued_at,
                    'issued_by_user_id' => $actor_user_id,
                    'invalidated_at' => null,
                    'invalidated_by_user_id' => null,
                ),
                array('%d', '%d', '%s', '%s', '%d', '%s', '%d', '%s', '%d')
            );

            if ($inserted === false) {
                throw new RuntimeException('Unable to issue invitation credential.');
            }

            $wpdb->query('COMMIT');

            return array(
                'token' => $token,
                'generation' => $generation,
            );
        } catch (Throwable $exception) {
            $wpdb->query('ROLLBACK');

            throw $exception;
        }
    }

    public function rotate($guest_id, $actor_user_id)
    {
        global $wpdb;

        $guest_id = (int) $guest_id;
        $actor_user_id = (int) $actor_user_id;

        if ($guest_id <= 0 || get_post_type($guest_id) !== 'invitado') {
            throw new RuntimeException('Invitation guest is invalid.');
        }

        if ($actor_user_id <= 0 || !user_can($actor_user_id, 'manage_options')) {
            throw new RuntimeException('Credential administration is not authorized.');
        }

        $wpdb->query('START TRANSACTION');

        try {
            $locked_guest_id = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT ID FROM {$wpdb->posts} WHERE ID = %d FOR UPDATE",
                    $guest_id
                )
            );

            if ((int) $locked_guest_id !== $guest_id) {
                throw new RuntimeException('Invitation guest could not be serialized.');
            }

            $table_name = WEM_Invitation_Credential_Schema::table_name();
            $this->assert_guest_credential_state_is_consistent($table_name, $guest_id);
            $active = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT sequence, generation, status, active_slot
                    FROM {$table_name}
                    WHERE guest_id = %d
                      AND status = %s
                      AND active_slot = %d
                    LIMIT 1
                    FOR UPDATE",
                    $guest_id,
                    'active',
                    1
                ),
                ARRAY_A
            );

            if (!is_array($active)) {
                throw new RuntimeException('Invitation has no active credential to rotate.');
            }

            if ($active['status'] !== 'active' || (int) $active['active_slot'] !== 1) {
                throw new RuntimeException('Invitation credential state is inconsistent.');
            }

            $invalidated_at = current_time('Y-m-d H:i:s');

            $updated = $wpdb->update(
                $table_name,
                array(
                    'status' => 'rotated',
                    'active_slot' => null,
                    'invalidated_at' => $invalidated_at,
                    'invalidated_by_user_id' => $actor_user_id,
                ),
                array(
                    'sequence' => (int) $active['sequence'],
                    'status' => 'active',
                    'active_slot' => 1,
                ),
                array('%s', '%d', '%s', '%d'),
                array('%d', '%s', '%d')
            );

            if ($updated !== 1) {
                throw new RuntimeException('Unable to invalidate active invitation credential.');
            }

            $generation = ((int) $active['generation']) + 1;
            $token = $this->generate_token();
            $digest = hash('sha256', $token);

            $inserted = $wpdb->insert(
                $table_name,
                array(
                    'guest_id' => $guest_id,
                    'generation' => $generation,
                    'token_digest' => $digest,
                    'status' => 'active',
                    'active_slot' => 1,
                    'issued_at' => $invalidated_at,
                    'issued_by_user_id' => $actor_user_id,
                    'invalidated_at' => null,
                    'invalidated_by_user_id' => null,
                ),
                array('%d', '%d', '%s', '%s', '%d', '%s', '%d', '%s', '%d')
            );

            if ($inserted === false) {
                throw new RuntimeException('Unable to issue rotated invitation credential.');
            }

            $wpdb->query('COMMIT');

            return array(
                'token' => $token,
                'generation' => $generation,
            );
        } catch (Throwable $exception) {
            $wpdb->query('ROLLBACK');

            throw $exception;
        }
    }

    public function revoke($guest_id, $actor_user_id)
    {
        global $wpdb;

        $guest_id = (int) $guest_id;
        $actor_user_id = (int) $actor_user_id;

        if ($guest_id <= 0 || get_post_type($guest_id) !== 'invitado') {
            throw new RuntimeException('Invitation guest is invalid.');
        }

        if ($actor_user_id <= 0 || !user_can($actor_user_id, 'manage_options')) {
            throw new RuntimeException('Credential administration is not authorized.');
        }

        $wpdb->query('START TRANSACTION');

        try {
            $locked_guest_id = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT ID FROM {$wpdb->posts} WHERE ID = %d FOR UPDATE",
                    $guest_id
                )
            );

            if ((int) $locked_guest_id !== $guest_id) {
                throw new RuntimeException('Invitation guest could not be serialized.');
            }

            $table_name = WEM_Invitation_Credential_Schema::table_name();
            $this->assert_guest_credential_state_is_consistent($table_name, $guest_id);
            $active = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT sequence, status, active_slot
                    FROM {$table_name}
                    WHERE guest_id = %d
                      AND status = %s
                      AND active_slot = %d
                    LIMIT 1
                    FOR UPDATE",
                    $guest_id,
                    'active',
                    1
                ),
                ARRAY_A
            );

            if (!is_array($active)) {
                throw new RuntimeException('Invitation has no active credential to revoke.');
            }

            if ($active['status'] !== 'active' || (int) $active['active_slot'] !== 1) {
                throw new RuntimeException('Invitation credential state is inconsistent.');
            }

            $invalidated_at = current_time('Y-m-d H:i:s');

            $updated = $wpdb->update(
                $table_name,
                array(
                    'status' => 'revoked',
                    'active_slot' => null,
                    'invalidated_at' => $invalidated_at,
                    'invalidated_by_user_id' => $actor_user_id,
                ),
                array(
                    'sequence' => (int) $active['sequence'],
                    'status' => 'active',
                    'active_slot' => 1,
                ),
                array('%s', '%d', '%s', '%d'),
                array('%d', '%s', '%d')
            );

            if ($updated !== 1) {
                throw new RuntimeException('Unable to revoke active invitation credential.');
            }

            $wpdb->query('COMMIT');
        } catch (Throwable $exception) {
            $wpdb->query('ROLLBACK');

            throw $exception;
        }
    }

    public function reissue($guest_id, $actor_user_id)
    {
        global $wpdb;

        $guest_id = (int) $guest_id;
        $actor_user_id = (int) $actor_user_id;

        if ($guest_id <= 0 || get_post_type($guest_id) !== 'invitado') {
            throw new RuntimeException('Invitation guest is invalid.');
        }

        if ($actor_user_id <= 0 || !user_can($actor_user_id, 'manage_options')) {
            throw new RuntimeException('Credential administration is not authorized.');
        }

        $wpdb->query('START TRANSACTION');

        try {
            $locked_guest_id = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT ID FROM {$wpdb->posts} WHERE ID = %d FOR UPDATE",
                    $guest_id
                )
            );

            if ((int) $locked_guest_id !== $guest_id) {
                throw new RuntimeException('Invitation guest could not be serialized.');
            }

            $table_name = WEM_Invitation_Credential_Schema::table_name();
            $this->assert_guest_credential_state_is_consistent($table_name, $guest_id);

            $active_sequence = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT sequence
                    FROM {$table_name}
                    WHERE guest_id = %d
                      AND status = %s
                      AND active_slot = %d
                    LIMIT 1
                    FOR UPDATE",
                    $guest_id,
                    'active',
                    1
                )
            );

            if ($active_sequence !== null) {
                throw new RuntimeException('Invitation already has an active credential.');
            }

            $generation = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COALESCE(MAX(generation), 0) + 1
                    FROM {$table_name}
                    WHERE guest_id = %d",
                    $guest_id
                )
            );

            $token = $this->generate_token();
            $digest = hash('sha256', $token);
            $issued_at = current_time('Y-m-d H:i:s');

            $inserted = $wpdb->insert(
                $table_name,
                array(
                    'guest_id' => $guest_id,
                    'generation' => $generation,
                    'token_digest' => $digest,
                    'status' => 'active',
                    'active_slot' => 1,
                    'issued_at' => $issued_at,
                    'issued_by_user_id' => $actor_user_id,
                    'invalidated_at' => null,
                    'invalidated_by_user_id' => null,
                ),
                array('%d', '%d', '%s', '%s', '%d', '%s', '%d', '%s', '%d')
            );

            if ($inserted === false) {
                throw new RuntimeException('Unable to reissue invitation credential.');
            }

            $wpdb->query('COMMIT');

            return array(
                'token' => $token,
                'generation' => $generation,
            );
        } catch (Throwable $exception) {
            $wpdb->query('ROLLBACK');

            throw $exception;
        }
    }

    private function assert_guest_credential_state_is_consistent($table_name, $guest_id)
    {
        global $wpdb;

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT status, active_slot
                FROM {$table_name}
                WHERE guest_id = %d",
                $guest_id
            ),
            ARRAY_A
        );

        foreach ($rows as $row) {
            $status = (string) $row['status'];
            $active_slot = $row['active_slot'];

            if ($status === 'active') {
                if ((int) $active_slot !== 1) {
                    throw new RuntimeException('Invitation credential state is inconsistent.');
                }

                continue;
            }

            if (in_array($status, array('rotated', 'revoked'), true)) {
                if ($active_slot !== null) {
                    throw new RuntimeException('Invitation credential state is inconsistent.');
                }

                continue;
            }

            throw new RuntimeException('Invitation credential state is inconsistent.');
        }
    }

    private function generate_token()
    {
        $bytes = random_bytes(32);

        return rtrim(
            strtr(base64_encode($bytes), '+/', '-_'),
            '='
        );
    }
}
