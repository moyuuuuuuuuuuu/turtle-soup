<?php

declare(strict_types=1);

namespace App\Ai\Services;

use App\Ai\Contracts\ContentParserInterface;
use GuzzleHttp\Client;
use RuntimeException;

final class CozeContentParser implements ContentParserInterface
{
    public function parse(array $input): array
    {
        $config = config('ai.content_parser');
        if (($config['token'] ?? '') === '' || ($config['workflow_id'] ?? '') === '') {
            throw new RuntimeException('system.config_error');
        }
        $response = (new Client(['base_uri' => rtrim($config['base_url'], '/')]))->post('/v1/workflow/run', [
            'headers' => ['Authorization' => 'Bearer ' . $config['token']],
            'json' => ['workflow_id' => $config['workflow_id'], 'parameters' => $input],
            'timeout' => $config['timeout'],
        ]);
        $payload = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        if ((int) ($payload['code'] ?? -1) !== 0) {
            throw new RuntimeException('ai.workflow_failed');
        }
        $data = $payload['data'] ?? null;
        if (is_string($data)) {
            $data = json_decode($data, true, 512, JSON_THROW_ON_ERROR);
        }
        if (is_array($data) && isset($data['result']) && is_string($data['result'])) {
            $data = json_decode($data['result'], true, 512, JSON_THROW_ON_ERROR);
        }
        if (!is_array($data)) {
            throw new RuntimeException('ai.invalid_response');
        }

        return $data;
    }
}
