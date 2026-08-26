<?php

declare(strict_types=1);

namespace App\Auth\Controllers;

use App\Auth\Business\PlayerAdminBusiness;
use plugin\saiadmin\basic\BaseController;
use plugin\saiadmin\service\Permission;
use support\Request;
use support\Response;

final class PlayerAdminController extends BaseController
{
    #[Permission('玩家列表', 'player:index')]
    public function index(Request $request): Response
    {
        return $this->success((new PlayerAdminBusiness())->page($request->only(['keyword', 'status']), max(1, (int) $request->get('page', 1)), min(100, max(1, (int) $request->get('pageSize', 20)))));
    }
    #[Permission('玩家详情', 'player:read')]
    public function read(Request $request): Response
    {
        return $this->success((new PlayerAdminBusiness())->read((int) $request->get('id')));
    }
    #[Permission('启用禁用玩家', 'player:status')]
    public function status(Request $request): Response
    {
        (new PlayerAdminBusiness())->status((int) $request->post('id'), (string) $request->post('status'));
        return $this->success('操作成功');
    }
    #[Permission('撤销玩家会话', 'player:session:revoke')]
    public function revoke(Request $request): Response
    {
        (new PlayerAdminBusiness())->revoke((int) $request->post('id'));
        return $this->success('会话已撤销');
    }
    #[Permission('玩家登录日志', 'player:log')]
    public function loginLogs(Request $request): Response
    {
        return $this->success((new PlayerAdminBusiness())->loginLogs((int) $request->get('id')));
    }
    #[Permission('匿名合并日志', 'player:log')]
    public function mergeLogs(Request $request): Response
    {
        return $this->success((new PlayerAdminBusiness())->mergeLogs((int) $request->get('id')));
    }
}
