<?php

declare(strict_types=1);

namespace App\Common\Support;

final class ProductionConfiguration
{
    /**
     * @param array<string, string> $values
     * @return list<string>
     */
    public static function violations(array $values): array
    {
        if (($values['APP_ENV'] ?? '') !== 'production') {
            return [];
        }

        $violations = [];
        if (filter_var($values['APP_DEBUG'] ?? 'false', FILTER_VALIDATE_BOOL)) {
            $violations[] = 'APP_DEBUG must be false in production';
        }
        if (filter_var($values['APP_URL'] ?? '', FILTER_VALIDATE_URL) === false
            || !str_starts_with($values['APP_URL'] ?? '', 'https://')) {
            $violations[] = 'APP_URL must use https in production';
        }
        $origins = array_filter(array_map('trim', explode(',', $values['CORS_ALLOWED_ORIGINS'] ?? '')));
        if ($origins === [] || array_filter(
            $origins,
            static fn (string $origin): bool => filter_var($origin, FILTER_VALIDATE_URL) === false
                || !str_starts_with($origin, 'https://')
                || str_contains($origin, '*'),
        ) !== []) {
            $violations[] = 'CORS_ALLOWED_ORIGINS must contain explicit origins';
        }

        foreach (['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASSWORD', 'REDIS_HOST'] as $name) {
            if (trim($values[$name] ?? '') === '') {
                $violations[] = $name . ' is required';
            }
        }
        $secretNames = ['ANONYMOUS_TOKEN_SECRET', 'PLAYER_JWT_SECRET', 'PLAYER_TOKEN_HASH_SECRET', 'PLAYER_EMAIL_CODE_SECRET'];
        foreach ($secretNames as $name) {
            if (strlen($values[$name] ?? '') < 32 || str_contains(strtolower($values[$name] ?? ''), 'change-me')) {
                $violations[] = $name . ' must be an independent secret of at least 32 characters';
            }
        }
        $secrets = array_map(static fn (string $name): string => $values[$name] ?? '', $secretNames);
        if (count(array_unique($secrets)) !== count($secrets)) {
            $violations[] = 'Signing secrets must be unique';
        }
        if (($values['COZE_GAME_DRIVER'] ?? '') !== 'coze') {
            $violations[] = 'COZE_GAME_DRIVER must be coze in production';
        }
        foreach (['COZE_API_TOKEN', 'COZE_QUESTION_JUDGE_WORKFLOW_ID', 'COZE_GUESS_JUDGE_WORKFLOW_ID'] as $name) {
            if (trim($values[$name] ?? '') === '') {
                $violations[] = $name . ' is required';
            }
        }
        if (!filter_var($values['REDIS_QUEUE_ENABLED'] ?? 'false', FILTER_VALIDATE_BOOL)) {
            $violations[] = 'REDIS_QUEUE_ENABLED must be true in production';
        }

        return $violations;
    }
}
