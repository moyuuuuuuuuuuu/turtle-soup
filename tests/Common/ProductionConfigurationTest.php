<?php

declare(strict_types=1);

namespace Tests\Common;

use App\Common\Support\ProductionConfiguration;
use PHPUnit\Framework\TestCase;

final class ProductionConfigurationTest extends TestCase
{
    public function testLocalEnvironmentIsNotRejected(): void
    {
        self::assertSame([], ProductionConfiguration::violations(['APP_ENV' => 'local']));
    }

    public function testProductionRejectsUnsafeDefaults(): void
    {
        $violations = ProductionConfiguration::violations([
            'APP_ENV' => 'production',
            'APP_DEBUG' => 'true',
            'APP_URL' => 'http://example.test',
            'CORS_ALLOWED_ORIGINS' => '*',
            'ANONYMOUS_TOKEN_SECRET' => 'change-me',
            'PLAYER_JWT_SECRET' => '',
            'PLAYER_TOKEN_HASH_SECRET' => '',
            'PLAYER_EMAIL_CODE_SECRET' => '',
            'COZE_GAME_DRIVER' => 'mock',
            'REDIS_QUEUE_ENABLED' => 'false',
        ]);

        self::assertContains('APP_DEBUG must be false in production', $violations);
        self::assertContains('APP_URL must use https in production', $violations);
        self::assertContains('COZE_GAME_DRIVER must be coze in production', $violations);
        self::assertContains('REDIS_QUEUE_ENABLED must be true in production', $violations);
    }

    public function testProductionRejectsInsecureCorsAndReusedSecrets(): void
    {
        $secret = str_repeat('a', 32);
        $violations = ProductionConfiguration::violations([
            'APP_ENV' => 'production', 'APP_DEBUG' => 'false', 'APP_URL' => 'https://hgt.example.com',
            'CORS_ALLOWED_ORIGINS' => 'http://admin.example.com', 'DB_HOST' => 'mysql', 'DB_NAME' => 'hgt',
            'DB_USER' => 'hgt', 'DB_PASSWORD' => 'database-secret', 'REDIS_HOST' => 'redis',
            'REDIS_QUEUE_ENABLED' => 'true', 'ANONYMOUS_TOKEN_SECRET' => $secret,
            'PLAYER_JWT_SECRET' => $secret, 'PLAYER_TOKEN_HASH_SECRET' => str_repeat('c', 32),
            'PLAYER_EMAIL_CODE_SECRET' => str_repeat('d', 32), 'COZE_GAME_DRIVER' => 'coze',
            'COZE_API_TOKEN' => 'token', 'COZE_QUESTION_JUDGE_WORKFLOW_ID' => 'question',
            'COZE_GUESS_JUDGE_WORKFLOW_ID' => 'guess',
        ]);

        self::assertContains('CORS_ALLOWED_ORIGINS must contain explicit origins', $violations);
        self::assertContains('Signing secrets must be unique', $violations);
    }

    public function testValidProductionConfigurationPasses(): void
    {
        $secret = str_repeat('a', 32);
        self::assertSame([], ProductionConfiguration::violations([
            'APP_ENV' => 'production', 'APP_DEBUG' => 'false', 'APP_URL' => 'https://hgt.example.com',
            'CORS_ALLOWED_ORIGINS' => 'https://hgt.example.com', 'DB_HOST' => 'mysql', 'DB_NAME' => 'hgt',
            'DB_USER' => 'hgt', 'DB_PASSWORD' => 'database-secret', 'REDIS_HOST' => 'redis',
            'REDIS_QUEUE_ENABLED' => 'true', 'ANONYMOUS_TOKEN_SECRET' => $secret,
            'PLAYER_JWT_SECRET' => str_repeat('b', 32), 'PLAYER_TOKEN_HASH_SECRET' => str_repeat('c', 32),
            'PLAYER_EMAIL_CODE_SECRET' => str_repeat('d', 32), 'COZE_GAME_DRIVER' => 'coze',
            'COZE_API_TOKEN' => 'token', 'COZE_QUESTION_JUDGE_WORKFLOW_ID' => 'question',
            'COZE_GUESS_JUDGE_WORKFLOW_ID' => 'guess',
        ]));
    }
}
