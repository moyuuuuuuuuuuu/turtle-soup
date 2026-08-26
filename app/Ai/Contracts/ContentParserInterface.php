<?php

declare(strict_types=1);

namespace App\Ai\Contracts;

interface ContentParserInterface
{
    public function parse(array $input): array;
}
