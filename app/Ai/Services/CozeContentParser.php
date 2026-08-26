<?php

declare(strict_types=1);

namespace App\Ai\Services;

use App\Ai\Contracts\ContentParserInterface;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use JsonException;
use Psr\Log\LoggerInterface;
use RuntimeException;
use support\Log;
use Throwable;

final class CozeContentParser implements ContentParserInterface
{
    private readonly ClientInterface $client;
    private readonly LoggerInterface $logger;

    /** @var array{base_url:string,token:string,workflow_id:string,workflow_version:string,timeout:int,retries:int,retry_delay_ms:int} */
    private readonly array $settings;

    /** @param null|array<string, mixed> $settings */
    public function __construct(
        ?ClientInterface $client = null,
        ?array $settings = null,
        ?LoggerInterface $logger = null,
    ) {
        $configured = $settings ?? config('ai.content_parser');
        $this->settings = [
            'base_url' => rtrim((string) ($configured['base_url'] ?? ''), '/'),
            'token' => (string) ($configured['token'] ?? ''),
            'workflow_id' => (string) ($configured['workflow_id'] ?? ''),
            'workflow_version' => (string) ($configured['workflow_version'] ?? 'unknown'),
            'timeout' => max(1, (int) ($configured['timeout'] ?? 30)),
            'retries' => max(0, (int) ($configured['retries'] ?? 2)),
            'retry_delay_ms' => max(0, (int) ($configured['retry_delay_ms'] ?? 250)),
        ];
        $this->client = $client ?? new Client(['base_uri' => $this->settings['base_url']]);
        $this->logger = $logger ?? Log::channel('default');
    }

    public function parse(array $input): array
    {
        if ($this->settings['token'] === '' || $this->settings['workflow_id'] === '') {
            throw new RuntimeException('system.config_error');
        }

        $startedAt = microtime(true);
        $attempt = 0;
        do {
            ++$attempt;
            try {
                $response = $this->client->request('POST', '/v1/workflow/run', [
                    'headers' => ['Authorization' => 'Bearer ' . $this->settings['token']],
                    'json' => ['workflow_id' => $this->settings['workflow_id'], 'parameters' => $input],
                    'timeout' => $this->settings['timeout'],
                ]);
                break;
            } catch (ConnectException $exception) {
                if ($attempt > $this->settings['retries']) {
                    $this->logFailure('ai.workflow_timeout', $attempt, $startedAt, null);
                    throw new RuntimeException('ai.workflow_timeout', previous: $exception);
                }
                $this->delay($attempt);
            } catch (RequestException $exception) {
                $status = $exception->getResponse()?->getStatusCode();
                if ($status === 401 || $status === 403) {
                    $this->logFailure('ai.auth_failed', $attempt, $startedAt, $status);
                    throw new RuntimeException('ai.auth_failed', previous: $exception);
                }
                if (!$this->isRetryable($status) || $attempt > $this->settings['retries']) {
                    $code = $status === null
                        ? 'ai.workflow_timeout'
                        : 'ai.workflow_failed';
                    $this->logFailure($code, $attempt, $startedAt, $status);
                    throw new RuntimeException($code, previous: $exception);
                }
                $this->delay($attempt);
            }
        } while (true);

        try {
            $payload = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            $this->logFailure('ai.invalid_response', $attempt, $startedAt, $response->getStatusCode());
            throw new RuntimeException('ai.invalid_response', previous: $exception);
        }
        if ((int) ($payload['code'] ?? -1) !== 0) {
            $this->logFailure('ai.workflow_failed', $attempt, $startedAt, $response->getStatusCode());
            throw new RuntimeException('ai.workflow_failed');
        }

        try {
            $data = $payload['data'] ?? null;
            if (is_string($data)) {
                $data = json_decode($data, true, 512, JSON_THROW_ON_ERROR);
            }
            if (is_array($data) && isset($data['result']) && is_string($data['result'])) {
                $data = json_decode($data['result'], true, 512, JSON_THROW_ON_ERROR);
            }
        } catch (JsonException $exception) {
            $this->logFailure('ai.invalid_response', $attempt, $startedAt, $response->getStatusCode());
            throw new RuntimeException('ai.invalid_response', previous: $exception);
        }
        if (!is_array($data)) {
            $this->logFailure('ai.invalid_response', $attempt, $startedAt, $response->getStatusCode());
            throw new RuntimeException('ai.invalid_response');
        }

        return $data;
    }

    private function isRetryable(?int $status): bool
    {
        return $status === null || $status === 429 || $status >= 500;
    }

    private function delay(int $attempt): void
    {
        if ($this->settings['retry_delay_ms'] > 0) {
            usleep($this->settings['retry_delay_ms'] * $attempt * 1000);
        }
    }

    private function logFailure(string $code, int $attempt, float $startedAt, ?int $status): void
    {
        try {
            $this->logger->warning('Coze workflow request failed', [
                'code' => $code,
                'workflow_version' => $this->settings['workflow_version'],
                'attempts' => $attempt,
                'latency_ms' => (int) ((microtime(true) - $startedAt) * 1000),
                'http_status' => $status,
            ]);
        } catch (Throwable) {
            // Logging failure must not replace the original integration error.
        }
    }
}
