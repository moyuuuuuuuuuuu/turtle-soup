<?php

declare(strict_types=1);

namespace App\Auth\Controllers;

use App\Auth\Business\AnonymousSessionBusiness;
use App\Common\Controllers\BaseController;
use support\Request;
use support\Response;

final class AnonymousSessionController extends BaseController
{
    public function issue(Request $request): Response
    {
        return $this->success((new AnonymousSessionBusiness())->issue((string) $request->post('device_id')), (string) $request->header('X-Request-Id', ''));
    }

    public function renew(Request $request): Response
    {
        return $this->success((new AnonymousSessionBusiness())->renew($this->token($request)), (string) $request->header('X-Request-Id', ''));
    }

    private function token(Request $request): string
    {
        return preg_replace('/^Bearer\s+/i', '', (string) $request->header('Authorization', '')) ?? '';
    }
}
