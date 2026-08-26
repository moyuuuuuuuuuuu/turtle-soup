<?php

declare(strict_types=1);

namespace App\Auth\Services;

use App\Common\Enums\ErrorCode;
use GuzzleHttp\Client;
use Throwable;

final class BosAvatarService
{
    /** @return array{url:string,object_key:string} */
    public function createDefault(string $email, string $publicId): array
    {
        $settings = (array) config('bos');
        foreach (['access_key', 'secret_key', 'endpoint', 'bucket', 'public_base_url'] as $key) {
            if (($settings[$key] ?? '') === '') {
                ErrorCode::CONFIG_ERROR->throw();
            }
        }
        $objectKey = 'avatars/default/'.hash('sha256', substr($publicId, 0, 2).'/'.$publicId).'.svg';
        $body = $this->svg($email);
        $endpoint = (string) $settings['endpoint'];
        $bucket = (string) $settings['bucket'];
        $uri = '/'.$bucket.'/'.$objectKey;
        $host = (string) parse_url($endpoint, PHP_URL_HOST);
        $date = gmdate('Y-m-d\TH:i:s\Z');
        $headers = ['host' => $host, 'x-bce-date' => $date];
        $authorization = $this->authorization('PUT', $uri, $headers, (string) $settings['access_key'], (string) $settings['secret_key'], $date);
        try {
            (new Client(['timeout' => 10]))->request('PUT', $endpoint.$uri, ['body' => $body, 'headers' => ['Authorization' => $authorization, 'Content-Type' => 'image/svg+xml', 'Host' => $host, 'x-bce-date' => $date]]);
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
        $canonicalHeaders = implode("\n", array_map(fn (string $key, string $value) => rawurlencode($key).':'.rawurlencode(trim($value)), array_keys($headers), $headers));
        $signedHeaders = implode(';', array_keys($headers));
        $canonicalRequest = $method."\n".$this->canonicalUri($uri)."\n\n".$canonicalHeaders;
        return $prefix.'/'.$signedHeaders.'/'.hash_hmac('sha256', $canonicalRequest, $signingKey);
    }

    private function canonicalUri(string $uri): string
    {
        return implode('/', array_map('rawurlencode', explode('/', $uri)));
    }

    private function svg(string $email): string
    {
        $letter = strtoupper(substr(EmailCodeService::normalizeEmail($email), 0, 1));
        $colors = ['#345995', '#0B6E4F', '#9B2C2C', '#6B46C1', '#B45309', '#0369A1'];
        $color = $colors[hexdec(substr(hash('sha256', $email), 0, 2)) % count($colors)];
        return '<svg xmlns="http://www.w3.org/2000/svg" width="256" height="256" viewBox="0 0 256 256"><rect width="256" height="256" rx="128" fill="'.$color.'"/><text x="128" y="145" text-anchor="middle" font-family="Arial,sans-serif" font-size="112" font-weight="700" fill="#fff">'.htmlspecialchars($letter, ENT_XML1).'</text></svg>';
    }
}
