<?php

declare(strict_types=1);

namespace App\Room\Controllers;

use App\Room\Business\RoomAdminBusiness;
use plugin\saiadmin\basic\BaseController;
use plugin\saiadmin\service\Permission;
use support\Request;
use support\Response;

final class RoomAdminController extends BaseController
{
    #[Permission('多人房间列表', 'room:index')]
    public function index(Request $request): Response
    {
        return $this->success((new RoomAdminBusiness())->page($request->only(['keyword', 'status']), max(1, (int) $request->get('page', 1)), min(100, max(1, (int) $request->get('pageSize', 20)))));
    }

    #[Permission('房间详情', 'room:read')]
    public function read(Request $request): Response
    {
        return $this->success((new RoomAdminBusiness())->read((int) $request->get('id')));
    }

    #[Permission('关闭房间', 'room:close')]
    public function close(Request $request): Response
    {
        (new RoomAdminBusiness())->close((int) $request->post('id'));

        return $this->success('房间已关闭');
    }
}
