<?php

declare(strict_types=1);

namespace App\Common\Enums;

enum ErrorModule: string
{
    case SYSTEM = 'system';
    case REQUEST = 'request';
    case AUTH = 'auth';
    case USER = 'user';
    case QUESTION = 'question';
    case GAME = 'game';
    case ROOM = 'room';
    case DONATION = 'donation';
    case AI = 'ai';
    case COZE = 'coze';
    case WECHAT = 'wechat';
    case STORAGE = 'storage';
    case WEBSOCKET = 'websocket';
    case DATABASE = 'database';
}
