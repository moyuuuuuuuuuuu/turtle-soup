<?php

declare(strict_types=1);

namespace App\Auth\Services;

use App\Common\Enums\ErrorCode;
use App\Storage\Services\BosObjectStorage;
use Webman\Http\UploadFile;

final class BosAvatarService
{
    /** @return array{url:string,object_key:string} */
    public function createDefault(string $email, string $publicId): array
    {
        $objectKey = 'avatars/default/'.hash('sha256', substr($publicId, 0, 2).'/'.$publicId).'.svg';
        $body = $this->svg($email);

        return (new BosObjectStorage())->put($objectKey, $body, 'image/svg+xml');
    }

    /** @return array{url:string,object_key:string} */
    public function upload(UploadFile $file, string $publicId): array
    {
        if (!$file->isValid() || $file->getSize() <= 0 || $file->getSize() > 5 * 1024 * 1024) {
            ErrorCode::PARAM_ERROR->throw('头像文件无效或超过 5MB');
        }
        $mime = (string) $file->getUploadMimeType();
        $extensions = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'];
        if (!isset($extensions[$mime])) {
            ErrorCode::PARAM_ERROR->throw('头像仅支持 PNG、JPEG 或 WebP');
        }
        $body = file_get_contents($file->getPathname());
        if ($body === false) {
            ErrorCode::STORAGE_UPLOAD_FAILED->throw();
        }
        $owner = hash('sha256', substr($publicId, 0, 2).'/'.$publicId);
        $objectKey = 'avatars/custom/'.$owner.'/'.hash('sha256', $body).'.'.$extensions[$mime];

        return (new BosObjectStorage())->put($objectKey, $body, $mime);
    }

    private function svg(string $email): string
    {
        $letter = strtoupper(substr(EmailCodeService::normalizeEmail($email), 0, 1));
        $colors = ['#345995', '#0B6E4F', '#9B2C2C', '#6B46C1', '#B45309', '#0369A1'];
        $color = $colors[hexdec(substr(hash('sha256', $email), 0, 2)) % count($colors)];
        return '<svg xmlns="http://www.w3.org/2000/svg" width="256" height="256" viewBox="0 0 256 256"><rect width="256" height="256" rx="128" fill="'.$color.'"/><text x="128" y="145" text-anchor="middle" font-family="Arial,sans-serif" font-size="112" font-weight="700" fill="#fff">'.htmlspecialchars($letter, ENT_XML1).'</text></svg>';
    }
}
