<?php

declare(strict_types=1);

namespace App\Storage\Services;

use App\Common\Enums\ErrorCode;
use GuzzleHttp\Client;
use Throwable;

final class BosObjectStorage
{
    /** @return array{url:string,object_key:string} */
    public function put(string $objectKey, string $body, string $contentType): array
    {
        $settings = (array) config('bos');
        foreach (['access_key', 'secret_key', 'endpoint', 'bucket', 'public_base_url'] as $key) {
            if (($settings[$key] ?? '') === '') {
                ErrorCode::CONFIG_ERROR->throw();
            }
        }
        $endpoint = (string) $settings['endpoint'];
        $bucket = (string) $settings['bucket'];
        $uri = '/'.$bucket.'/'.$objectKey;
        $host = (string) parse_url($endpoint, PHP_URL_HOST);
        $date = gmdate('Y-m-d\TH:i:s\Z');
        $headers = ['host' => $host, 'x-bce-date' => $date];
        $authorization = $this->authorization('PUT', $uri, $headers, (string) $settings['access_key'], (string) $settings['secret_key'], $date);
        try {
            (new Client(['timeout' => 15]))->request('PUT', $endpoint.$uri, [
                'body' => $body,
                'headers' => ['Authorization' => $authorization, 'Content-Type' => $contentType, 'Host' => $host, 'x-bce-date' => $date],
            ]);
        } catch (Throwable $exception) {
            ErrorCode::STORAGE_UPLOAD_FAILED->throw(previous: $exception);
        }

        return ['url' => (string) $settings['public_base_url'].'/'.$objectKey, 'object_key' => $objectKey];
    }

    /** @param array<string,string> $headers */
    private function authorization(string $method, string $uri, array $headers, string $accessKey, string $secretKey, string $date): string
    {
        $prefix = 'bce-auth-v1/'.$accessKey.'/'.$date.'/1800';
        $signingKey = hash_hmac('sha256', $prefix, $secretKey);
        ksort($headers);
        $canonicalHeaders = implode("\n", array_map(static fn (string $key, string $value): string => rawurlencode($key).':'.rawurlencode(trim($value)), array_keys($headers), $headers));
        $signedHeaders = implode(';', array_keys($headers));
        $canonicalRequest = $method."\n".$this->canonicalUri($uri)."\n\n".$canonicalHeaders;

        return $prefix.'/'.$signedHeaders.'/'.hash_hmac('sha256', $canonicalRequest, $signingKey);
    }

    private function canonicalUri(string $uri): string
    {
        return implode('/', array_map('rawurlencode', explode('/', $uri)));
    }
}
