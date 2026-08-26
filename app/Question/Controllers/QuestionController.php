<?php

declare(strict_types=1);

namespace App\Question\Controllers;

use App\Question\Business\QuestionBusiness;
use plugin\saiadmin\basic\BaseController;
use plugin\saiadmin\service\Permission;
use support\Request;
use support\Response;

final class QuestionController extends BaseController
{
    public function __construct(private readonly QuestionBusiness $business = new QuestionBusiness())
    {
        parent::__construct();
    }

    #[Permission('题目列表', 'question:index')]
    public function index(Request $request): Response
    {
        $filters = $request->only(['status', 'difficulty', 'source_type', 'language', 'tag_id', 'keyword']);
        $data = $this->business->page($filters, max(1, (int) $request->get('page', 1)), min(100, max(1, (int) $request->get('pageSize', 20))));

        return $this->success($data);
    }

    #[Permission('查看题目', 'question:read')]
    public function read(Request $request): Response
    {
        return $this->success($this->business->detail((int) $request->get('id'), true));
    }

    #[Permission('新增题目', 'question:edit')]
    public function save(Request $request): Response
    {
        return $this->success($this->business->create($request->post(), $this->adminId));
    }

    #[Permission('编辑题目', 'question:edit')]
    public function update(Request $request): Response
    {
        return $this->success($this->business->update((int) $request->post('id'), $request->post(), $this->adminId));
    }

    #[Permission('删除题目草稿', 'question:edit')]
    public function destroy(Request $request): Response
    {
        $this->business->deleteDraft((int) $request->post('id'));

        return $this->success('删除成功');
    }

    #[Permission('发布题目', 'question:publish')]
    public function publish(Request $request): Response
    {
        $this->business->publish((int) $request->post('id'));

        return $this->success('发布成功');
    }

    #[Permission('下架题目', 'question:publish')]
    public function offline(Request $request): Response
    {
        $this->business->offline((int) $request->post('id'));

        return $this->success('下架成功');
    }

    #[Permission('预览题目', 'question:read')]
    public function preview(Request $request): Response
    {
        return $this->success($this->business->detail((int) $request->get('id'), (bool) $request->get('finished', false)));
    }
}
