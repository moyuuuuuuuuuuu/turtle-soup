<?php

declare(strict_types=1);

namespace App\Question\Business;

use plugin\saiadmin\app\cache\UserAuthCache;
use plugin\saiadmin\exception\ApiException;

final class QuestionAccessBusiness
{
    public function assertAnswerAccess(int $adminId): void
    {
        if ($adminId === 1) {
            return;
        }
        if (!in_array('question:answer:read', UserAuthCache::getUserAuth($adminId), true)) {
            throw new ApiException('question.answer_forbidden');
        }
    }
}
