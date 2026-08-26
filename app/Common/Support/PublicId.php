<?php

declare(strict_types=1);

namespace App\Common\Support;

final class PublicId
{
    public static function make(): string
    {
        $alphabet = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';
        $value = '';
        for ($index = 0; $index < 26; $index++) {
            $value .= $alphabet[random_int(0, 31)];
        }

        return $value;
    }
}
