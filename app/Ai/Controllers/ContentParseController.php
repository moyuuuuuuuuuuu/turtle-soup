<?php

declare(strict_types=1);

namespace App\Ai\Controllers;

use App\Ai\Business\ContentParseBusiness;
use plugin\saiadmin\basic\BaseController;
use plugin\saiadmin\service\Permission;
use support\Request;
use support\Response;

final class ContentParseController extends BaseController
{
    public function __construct(private readonly ContentParseBusiness $business = new ContentParseBusiness())
    {
        parent::__construct();
    }

    #[Permission('创建 AI 解析任务', 'question:ai:create')]
    public function create(Request $request): Response
    {
        return $this->success($this->business->create($request->post(), $this->adminId));
    }

    #[Permission('查看 AI 解析任务', 'question:ai:index')]
    public function read(Request $request): Response
    {
        return $this->success($this->business->get((string) $request->get('id')));
    }

    #[Permission('重试 AI 解析任务', 'question:ai:create')]
    public function retry(Request $request): Response
    {
        return $this->success($this->business->retry((string) $request->post('id')));
    }

    #[Permission('采纳 AI 解析结果', 'question:ai:adopt')]
    public function adopt(Request $request): Response
    {
        return $this->success($this->business->adopt((string) $request->post('id'), $this->adminId));
    }
}
