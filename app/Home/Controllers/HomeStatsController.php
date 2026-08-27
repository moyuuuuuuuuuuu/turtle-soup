<?php

declare(strict_types=1);

namespace App\Home\Controllers;

use App\Common\Controllers\BaseController;
use App\Home\Business\HomeStatsBusiness;
use support\Request;
use support\Response;

final class HomeStatsController extends BaseController
{
    public function index(Request $request): Response
    {
        return $this->success((new HomeStatsBusiness())->stats(), (string) $request->header('X-Request-Id', ''));
    }
}
