<?php

declare(strict_types=1);

use Phinx\Db\Adapter\MysqlAdapter;
use Phinx\Migration\AbstractMigration;

final class CreatePlayerAccounts extends AbstractMigration
{
    public function change(): void
    {
        $this->table('turtle_users', ['id' => false, 'primary_key' => ['id']])
            ->addColumn('id', 'biginteger', ['identity' => true, 'signed' => false])
            ->addColumn('public_id', 'string', ['limit' => 26])
            ->addColumn('username', 'string', ['limit' => 24])
            ->addColumn('username_normalized', 'string', ['limit' => 24])
            ->addColumn('email', 'string', ['limit' => 254])
            ->addColumn('email_normalized', 'string', ['limit' => 254])
            ->addColumn('password_hash', 'string', ['limit' => 255])
            ->addColumn('status', 'string', ['limit' => 20, 'default' => 'active'])
            ->addColumn('email_verified_at', 'datetime')
            ->addColumn('username_changed_at', 'datetime', ['null' => true])
            ->addColumn('last_login_at', 'datetime', ['null' => true])
            ->addColumn('create_time', 'datetime')->addColumn('update_time', 'datetime')
            ->addIndex(['public_id'], ['unique' => true])
            ->addIndex(['username_normalized'], ['unique' => true])
            ->addIndex(['email_normalized'], ['unique' => true])
            ->addIndex(['status'])->create();

        $this->table('turtle_user_identities', ['id' => false, 'primary_key' => ['id']])
            ->addColumn('id', 'biginteger', ['identity' => true, 'signed' => false])
            ->addColumn('user_id', 'biginteger', ['signed' => false])
            ->addColumn('provider', 'string', ['limit' => 40])
            ->addColumn('provider_subject', 'string', ['limit' => 191])
            ->addColumn('union_subject', 'string', ['limit' => 191, 'null' => true])
            ->addColumn('metadata', 'text', ['limit' => MysqlAdapter::TEXT_MEDIUM, 'null' => true])
            ->addColumn('create_time', 'datetime')->addColumn('update_time', 'datetime')
            ->addIndex(['provider', 'provider_subject'], ['unique' => true])
            ->addIndex(['union_subject'])->addForeignKey('user_id', 'turtle_users', 'id', ['delete' => 'CASCADE'])->create();

        $this->table('turtle_refresh_sessions', ['id' => false, 'primary_key' => ['id']])
            ->addColumn('id', 'biginteger', ['identity' => true, 'signed' => false])
            ->addColumn('public_id', 'string', ['limit' => 26])
            ->addColumn('user_id', 'biginteger', ['signed' => false])
            ->addColumn('family_id', 'string', ['limit' => 26])
            ->addColumn('token_hash', 'char', ['limit' => 64])
            ->addColumn('previous_token_hash', 'char', ['limit' => 64, 'null' => true])
            ->addColumn('device_hash', 'char', ['limit' => 64])
            ->addColumn('device_name', 'string', ['limit' => 100, 'default' => '未知设备'])
            ->addColumn('platform', 'string', ['limit' => 30, 'default' => 'unknown'])
            ->addColumn('last_used_at', 'datetime')->addColumn('expires_at', 'datetime')
            ->addColumn('revoked_at', 'datetime', ['null' => true])
            ->addColumn('revoke_reason', 'string', ['limit' => 60, 'null' => true])
            ->addColumn('create_time', 'datetime')->addColumn('update_time', 'datetime')
            ->addIndex(['public_id'], ['unique' => true])->addIndex(['token_hash'], ['unique' => true])
            ->addIndex(['previous_token_hash'])->addIndex(['user_id', 'revoked_at', 'expires_at'])
            ->addForeignKey('user_id', 'turtle_users', 'id', ['delete' => 'CASCADE'])->create();

        $this->table('turtle_email_codes', ['id' => false, 'primary_key' => ['id']])
            ->addColumn('id', 'biginteger', ['identity' => true, 'signed' => false])
            ->addColumn('email_normalized', 'string', ['limit' => 254])
            ->addColumn('purpose', 'string', ['limit' => 30])->addColumn('code_hash', 'char', ['limit' => 64])
            ->addColumn('request_ip_hash', 'char', ['limit' => 64])->addColumn('attempts', 'integer', ['default' => 0])
            ->addColumn('expires_at', 'datetime')->addColumn('consumed_at', 'datetime', ['null' => true])
            ->addColumn('create_time', 'datetime')->addColumn('update_time', 'datetime')
            ->addIndex(['email_normalized', 'purpose', 'create_time'])->addIndex(['request_ip_hash', 'create_time'])->create();

        $this->table('turtle_user_login_logs', ['id' => false, 'primary_key' => ['id']])
            ->addColumn('id', 'biginteger', ['identity' => true, 'signed' => false])
            ->addColumn('user_id', 'biginteger', ['signed' => false, 'null' => true])
            ->addColumn('method', 'string', ['limit' => 30])->addColumn('result', 'string', ['limit' => 20])
            ->addColumn('identifier_masked', 'string', ['limit' => 254])->addColumn('device_name', 'string', ['limit' => 100])
            ->addColumn('ip_hash', 'char', ['limit' => 64])->addColumn('error_code', 'string', ['limit' => 100, 'null' => true])
            ->addColumn('create_time', 'datetime')->addColumn('update_time', 'datetime')
            ->addIndex(['user_id', 'create_time'])->addIndex(['result', 'create_time'])->create();

        $this->table('turtle_anonymous_merge_logs', ['id' => false, 'primary_key' => ['id']])
            ->addColumn('id', 'biginteger', ['identity' => true, 'signed' => false])
            ->addColumn('user_id', 'biginteger', ['signed' => false])->addColumn('anonymous_session_id', 'biginteger', ['signed' => false])
            ->addColumn('merged_games', 'integer', ['default' => 0])->addColumn('result', 'string', ['limit' => 20])
            ->addColumn('create_time', 'datetime')->addColumn('update_time', 'datetime')
            ->addIndex(['user_id', 'anonymous_session_id'], ['unique' => true])
            ->addForeignKey('user_id', 'turtle_users', 'id', ['delete' => 'CASCADE'])
            ->addForeignKey('anonymous_session_id', 'turtle_anonymous_sessions', 'id', ['delete' => 'RESTRICT'])->create();

        $this->table('turtle_anonymous_sessions')->addColumn('bound_at', 'datetime', ['null' => true])->addColumn('revoked_at', 'datetime', ['null' => true])
            ->addIndex(['user_id'])->addForeignKey('user_id', 'turtle_users', 'id', ['delete' => 'SET_NULL'])->update();
        $this->table('turtle_games')->dropForeignKey('anonymous_session_id')->update();
        $this->table('turtle_games')->changeColumn('anonymous_session_id', 'biginteger', ['signed' => false, 'null' => true])
            ->addColumn('user_id', 'biginteger', ['signed' => false, 'null' => true, 'after' => 'anonymous_session_id'])
            ->addIndex(['user_id', 'status'])->addForeignKey('anonymous_session_id', 'turtle_anonymous_sessions', 'id', ['delete' => 'CASCADE'])
            ->addForeignKey('user_id', 'turtle_users', 'id', ['delete' => 'RESTRICT'])->update();
    }
}
