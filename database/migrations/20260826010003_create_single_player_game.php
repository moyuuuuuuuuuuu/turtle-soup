<?php

declare(strict_types=1);

use Phinx\Db\Adapter\MysqlAdapter;
use Phinx\Migration\AbstractMigration;

final class CreateSinglePlayerGame extends AbstractMigration
{
    public function change(): void
    {
        $this->table('turtle_anonymous_sessions', ['id' => false, 'primary_key' => ['id']])
            ->addColumn('id', 'biginteger', ['identity' => true, 'signed' => false])
            ->addColumn('public_id', 'string', ['limit' => 26])
            ->addColumn('token_hash', 'char', ['limit' => 64])
            ->addColumn('device_hash', 'char', ['limit' => 64])
            ->addColumn('user_id', 'biginteger', ['signed' => false, 'null' => true])
            ->addColumn('last_active_at', 'datetime')
            ->addColumn('expires_at', 'datetime')
            ->addColumn('create_time', 'datetime')
            ->addColumn('update_time', 'datetime')
            ->addIndex(['public_id'], ['unique' => true])
            ->addIndex(['token_hash'], ['unique' => true])
            ->addIndex(['device_hash'])
            ->addIndex(['expires_at'])
            ->create();

        $this->table('turtle_games', ['id' => false, 'primary_key' => ['id']])
            ->addColumn('id', 'biginteger', ['identity' => true, 'signed' => false])
            ->addColumn('public_id', 'string', ['limit' => 26])
            ->addColumn('question_id', 'biginteger', ['signed' => false])
            ->addColumn('anonymous_session_id', 'biginteger', ['signed' => false])
            ->addColumn('status', 'string', ['limit' => 20, 'default' => 'created'])
            ->addColumn('content_locale', 'string', ['limit' => 16, 'default' => 'zh-CN'])
            ->addColumn('difficulty', 'integer', ['limit' => 1])
            ->addColumn('question_limit', 'integer')
            ->addColumn('question_count', 'integer', ['default' => 0])
            ->addColumn('hint_count', 'integer', ['default' => 0])
            ->addColumn('next_sequence', 'biginteger', ['signed' => false, 'default' => 1])
            ->addColumn('risk_confirmed', 'boolean', ['default' => false])
            ->addColumn('question_snapshot', 'text', ['limit' => MysqlAdapter::TEXT_LONG])
            ->addColumn('started_at', 'datetime', ['null' => true])
            ->addColumn('finished_at', 'datetime', ['null' => true])
            ->addColumn('create_time', 'datetime')
            ->addColumn('update_time', 'datetime')
            ->addIndex(['public_id'], ['unique' => true])
            ->addIndex(['anonymous_session_id', 'status'])
            ->addIndex(['question_id'])
            ->addForeignKey('question_id', 'turtle_questions', 'id', ['delete' => 'RESTRICT'])
            ->addForeignKey('anonymous_session_id', 'turtle_anonymous_sessions', 'id', ['delete' => 'CASCADE'])
            ->create();

        $this->table('turtle_game_messages', ['id' => false, 'primary_key' => ['id']])
            ->addColumn('id', 'biginteger', ['identity' => true, 'signed' => false])
            ->addColumn('game_id', 'biginteger', ['signed' => false])
            ->addColumn('sequence', 'biginteger', ['signed' => false])
            ->addColumn('request_id', 'string', ['limit' => 64, 'null' => true])
            ->addColumn('role', 'string', ['limit' => 20])
            ->addColumn('type', 'string', ['limit' => 30])
            ->addColumn('content', 'text')
            ->addColumn('metadata', 'text', ['null' => true])
            ->addColumn('create_time', 'datetime')
            ->addColumn('update_time', 'datetime')
            ->addIndex(['game_id', 'sequence'], ['unique' => true])
            ->addIndex(['game_id', 'request_id'], ['unique' => true])
            ->addForeignKey('game_id', 'turtle_games', 'id', ['delete' => 'CASCADE'])
            ->create();

        $this->table('turtle_game_discovered_points', ['id' => false, 'primary_key' => ['id']])
            ->addColumn('id', 'biginteger', ['identity' => true, 'signed' => false])
            ->addColumn('game_id', 'biginteger', ['signed' => false])
            ->addColumn('point_key', 'string', ['limit' => 64])
            ->addColumn('confidence', 'decimal', ['precision' => 5, 'scale' => 4, 'default' => 0])
            ->addColumn('discovered_at', 'datetime')
            ->addColumn('create_time', 'datetime')
            ->addColumn('update_time', 'datetime')
            ->addIndex(['game_id', 'point_key'], ['unique' => true])
            ->addForeignKey('game_id', 'turtle_games', 'id', ['delete' => 'CASCADE'])
            ->create();

        $this->table('turtle_game_hints', ['id' => false, 'primary_key' => ['id']])
            ->addColumn('id', 'biginteger', ['identity' => true, 'signed' => false])
            ->addColumn('game_id', 'biginteger', ['signed' => false])
            ->addColumn('level', 'integer', ['limit' => 1])
            ->addColumn('request_id', 'string', ['limit' => 64])
            ->addColumn('used_at', 'datetime')
            ->addColumn('create_time', 'datetime')
            ->addColumn('update_time', 'datetime')
            ->addIndex(['game_id', 'level'], ['unique' => true])
            ->addIndex(['game_id', 'request_id'], ['unique' => true])
            ->addForeignKey('game_id', 'turtle_games', 'id', ['delete' => 'CASCADE'])
            ->create();

        $this->table('turtle_game_guesses', ['id' => false, 'primary_key' => ['id']])
            ->addColumn('id', 'biginteger', ['identity' => true, 'signed' => false])
            ->addColumn('game_id', 'biginteger', ['signed' => false])
            ->addColumn('request_id', 'string', ['limit' => 64])
            ->addColumn('content', 'text')
            ->addColumn('is_solved', 'boolean', ['default' => false])
            ->addColumn('matched_points', 'text', ['null' => true])
            ->addColumn('summary', 'text', ['null' => true])
            ->addColumn('submitted_at', 'datetime')
            ->addColumn('create_time', 'datetime')
            ->addColumn('update_time', 'datetime')
            ->addIndex(['game_id'], ['unique' => true])
            ->addIndex(['game_id', 'request_id'], ['unique' => true])
            ->addForeignKey('game_id', 'turtle_games', 'id', ['delete' => 'CASCADE'])
            ->create();

        $this->table('turtle_game_ai_requests', ['id' => false, 'primary_key' => ['id']])
            ->addColumn('id', 'biginteger', ['identity' => true, 'signed' => false])
            ->addColumn('game_id', 'biginteger', ['signed' => false])
            ->addColumn('request_id', 'string', ['limit' => 64])
            ->addColumn('workflow', 'string', ['limit' => 64])
            ->addColumn('status', 'string', ['limit' => 20, 'default' => 'pending'])
            ->addColumn('attempts', 'integer', ['default' => 0])
            ->addColumn('latency_ms', 'integer', ['null' => true])
            ->addColumn('safe_result', 'text', ['null' => true])
            ->addColumn('error_code', 'string', ['limit' => 100, 'null' => true])
            ->addColumn('create_time', 'datetime')
            ->addColumn('update_time', 'datetime')
            ->addIndex(['game_id', 'request_id', 'workflow'], ['unique' => true])
            ->addIndex(['status', 'create_time'])
            ->addForeignKey('game_id', 'turtle_games', 'id', ['delete' => 'CASCADE'])
            ->create();
    }
}
