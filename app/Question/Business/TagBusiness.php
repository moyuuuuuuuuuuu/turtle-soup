<?php

declare(strict_types=1);

namespace App\Question\Business;

use App\Question\Models\Tag;
use plugin\saiadmin\exception\ApiException;

final class TagBusiness
{
    public function all(): array
    {
        return Tag::query()->orderBy('name')->get()->toArray();
    }

    public function save(array $payload): array
    {
        $name = trim((string) ($payload['name'] ?? ''));
        $slug = strtolower(trim((string) ($payload['slug'] ?? '')));
        if ($name === '' || preg_match('/^[a-z0-9_-]{1,64}$/', $slug) !== 1) {
            throw new ApiException('标签名称或标识不合法');
        }
        $tag = isset($payload['id']) ? Tag::find((int) $payload['id']) : new Tag();
        if (!$tag) {
            throw new ApiException('标签不存在');
        }
        $tag->fill(['name' => $name, 'slug' => $slug])->save();

        return $tag->toArray();
    }

    public function delete(int $id): void
    {
        $tag = Tag::find($id) ?? throw new ApiException('标签不存在');
        if ($tag->questions()->exists()) {
            throw new ApiException('标签正在被题目使用');
        }
        $tag->delete();
    }
}
