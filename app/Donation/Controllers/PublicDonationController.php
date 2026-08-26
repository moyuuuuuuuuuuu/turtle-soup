<?php

declare(strict_types=1);

namespace App\Donation\Controllers;

use App\Common\Controllers\BaseController;
use App\Donation\Business\DonationBusiness;
use support\Request;
use support\Response;

final class PublicDonationController extends BaseController
{
    public function index(Request $request): Response
    {
        return $this->success((new DonationBusiness())->publicPage(), (string) $request->header('X-Request-Id', ''));
    }
}
