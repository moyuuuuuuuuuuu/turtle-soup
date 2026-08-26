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
            throw new ApiException('tag.slug_invalid');
        }
        $tag = isset($payload['id']) ? Tag::find((int) $payload['id']) : new Tag();
        if (!$tag) {
            throw new ApiException('tag.not_found');
        }
        if (Tag::query()->where('slug', $slug)->when($tag->exists, fn ($query) => $query->whereKeyNot($tag->getKey()))->exists()) {
            throw new ApiException('tag.slug_invalid');
        }
        $tag->fill(['name' => $name, 'slug' => $slug])->save();

        return $tag->toArray();
    }

    public function delete(int $id): void
    {
        $tag = Tag::find($id) ?? throw new ApiException('tag.not_found');
        if ($tag->questions()->exists()) {
            throw new ApiException('tag.in_use');
        }
        $tag->delete();
    }
}
