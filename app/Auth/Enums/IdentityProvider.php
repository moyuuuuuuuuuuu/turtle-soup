<?php

declare(strict_types=1);

namespace App\Auth\Enums;

enum IdentityProvider: string
{
    case WECHAT_MINI_PROGRAM = 'wechat_mini_program';
    case WECHAT_OFFICIAL_ACCOUNT = 'wechat_official_account';
}
