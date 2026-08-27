<?php

declare(strict_types=1);

namespace App\Question\Controllers;

use App\Question\Business\QuestionAccessBusiness;
use App\Question\Business\QuestionBusiness;
use plugin\saiadmin\basic\BaseController;
use plugin\saiadmin\service\Permission;
use support\Request;
use support\Response;

final class QuestionController extends BaseController
{
    public function __construct(
        private readonly QuestionBusiness $business = new QuestionBusiness(),
        private readonly QuestionAccessBusiness $access = new QuestionAccessBusiness(),
    ) {
        parent::__construct();
    }

    #[Permission('题目列表', 'question:index')]
    public function index(Request $request): Response
    {
        $filters = $request->only(['status', 'difficulty', 'source_type', 'language', 'tag_id', 'keyword', 'is_featured']);
        $data = $this->business->page($filters, max(1, (int) $request->get('page', 1)), min(100, max(1, (int) $request->get('pageSize', 20))));

        return $this->success($data);
    }

    #[Permission('查看题目汤底', 'question:answer:read')]
    public function read(Request $request): Response
    {
        $this->access->assertAnswerAccess($this->adminId);

        return $this->success($this->business->detail((int) $request->get('id'), true));
    }

    #[Permission('新增题目', 'question:edit')]
    public function save(Request $request): Response
    {
        $this->access->assertAnswerAccess($this->adminId);

        return $this->success($this->business->create($request->post(), $this->adminId));
    }

    #[Permission('编辑题目', 'question:edit')]
    public function update(Request $request): Response
    {
        $this->access->assertAnswerAccess($this->adminId);

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
        $this->access->assertAnswerAccess($this->adminId);
        $data = $this->business->publish(
            (int) $request->post('id'),
            (int) $request->post('version'),
            filter_var($request->post('risk_confirmed', false), FILTER_VALIDATE_BOOL),
            $this->adminId,
        );

        return $this->success($data);
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
        return $this->success($this->business->detail((int) $request->get('id'), false));
    }

    #[Permission('结算预览', 'question:answer:read')]
    public function answerPreview(Request $request): Response
    {
        $this->access->assertAnswerAccess($this->adminId);

        return $this->success($this->business->detail((int) $request->get('id'), true));
    }

    #[Permission('复制题目', 'question:copy')]
    public function copy(Request $request): Response
    {
        $this->access->assertAnswerAccess($this->adminId);

        return $this->success($this->business->copy((int) $request->post('id'), $this->adminId));
    }

    #[Permission('题目版本历史', 'question:history')]
    public function history(Request $request): Response
    {
        return $this->success($this->business->history((int) $request->get('id')));
    }

    #[Permission('查看题目历史内容', 'question:answer:read')]
    public function historyRead(Request $request): Response
    {
        $this->access->assertAnswerAccess($this->adminId);

        return $this->success($this->business->historyDetail(
            (int) $request->get('id'),
            (int) $request->get('version_id'),
            true,
        ));
    }

    #[Permission('恢复题目历史版本', 'question:history')]
    public function historyRestore(Request $request): Response
    {
        $this->access->assertAnswerAccess($this->adminId);

        return $this->success($this->business->restore(
            (int) $request->post('id'),
            (int) $request->post('version_id'),
            (int) $request->post('version'),
            $this->adminId,
        ));
    }
}
