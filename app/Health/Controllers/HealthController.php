<?php

declare(strict_types=1);

namespace App\Health\Controllers;

use App\Common\Controllers\BaseController;
use App\Common\Support\RequestContext;
use support\Response;
use Webman\Http\Request;

final class HealthController extends BaseController
{
    public function index(Request $request): Response
    {
        return $this->success([
            'status' => 'ok',
            'service' => 'turtle-soup-api',
        ], RequestContext::requestId($request));
    }
}
