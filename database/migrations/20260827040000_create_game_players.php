<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateGamePlayers extends AbstractMigration
{
    public function up(): void
    {
        $this->table('turtle_game_players', ['id' => false, 'primary_key' => ['id']])
            ->addColumn('id', 'biginteger', ['identity' => true, 'signed' => false])
            ->addColumn('game_id', 'biginteger', ['signed' => false])
            ->addColumn('user_id', 'biginteger', ['signed' => false])
            ->addColumn('status', 'string', ['limit' => 20, 'default' => 'playing'])
            ->addColumn('joined_at', 'datetime')
            ->addColumn('completed_at', 'datetime', ['null' => true])
            ->addColumn('create_time', 'datetime')
            ->addColumn('update_time', 'datetime')
            ->addIndex(['game_id', 'user_id'], ['unique' => true])
            ->addIndex(['user_id', 'status'])
            ->addForeignKey('game_id', 'turtle_games', 'id', ['delete' => 'CASCADE'])
            ->addForeignKey('user_id', 'turtle_users', 'id', ['delete' => 'CASCADE'])
            ->create();

        $this->execute(<<<'SQL'
            INSERT INTO turtle_game_players
                (game_id, user_id, status, joined_at, completed_at, create_time, update_time)
            SELECT
                games.id,
                games.user_id,
                CASE
                    WHEN games.status = 'solved' THEN 'solved'
                    WHEN games.status IN ('finished', 'abandoned') THEN games.status
                    ELSE 'playing'
                END,
                games.create_time,
                games.finished_at,
                NOW(),
                NOW()
            FROM turtle_games AS games
            WHERE games.user_id IS NOT NULL
              AND games.room_id IS NULL
              AND (
                  games.status IN ('solved', 'finished', 'abandoned')
                  OR games.id = (
                      SELECT MAX(active_game.id)
                      FROM turtle_games AS active_game
                      WHERE active_game.user_id = games.user_id
                        AND active_game.question_id = games.question_id
                        AND active_game.room_id IS NULL
                        AND active_game.status IN ('created', 'playing')
                  )
              )
            ON DUPLICATE KEY UPDATE
                status = VALUES(status),
                completed_at = VALUES(completed_at),
                update_time = NOW()
        SQL);

        $this->execute(<<<'SQL'
            INSERT INTO turtle_game_players
                (game_id, user_id, status, joined_at, completed_at, create_time, update_time)
            SELECT
                games.id,
                members.user_id,
                CASE
                    WHEN games.status = 'solved' THEN 'solved'
                    WHEN games.status IN ('finished', 'abandoned') THEN games.status
                    ELSE 'playing'
                END,
                members.joined_at,
                games.finished_at,
                NOW(),
                NOW()
            FROM turtle_games AS games
            INNER JOIN turtle_room_members AS members
                ON members.room_id = games.room_id
            ON DUPLICATE KEY UPDATE
                status = VALUES(status),
                completed_at = VALUES(completed_at),
                update_time = NOW()
        SQL);
    }

    public function down(): void
    {
        $this->table('turtle_game_players')->drop()->save();
    }
}
