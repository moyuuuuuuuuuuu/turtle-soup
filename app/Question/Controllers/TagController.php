<?php

declare(strict_types=1);

namespace App\Question\Controllers;

use App\Question\Business\TagBusiness;
use plugin\saiadmin\basic\BaseController;
use plugin\saiadmin\service\Permission;
use support\Request;
use support\Response;

final class TagController extends BaseController
{
    public function __construct(private readonly TagBusiness $business = new TagBusiness())
    {
        parent::__construct();
    }

    #[Permission('标签列表', 'question:tag:index')]
    public function index(): Response
    {
        return $this->success($this->business->all());
    }

    #[Permission('编辑标签', 'question:tag:edit')]
    public function save(Request $request): Response
    {
        return $this->success($this->business->save($request->post()));
    }

    #[Permission('编辑标签', 'question:tag:edit')]
    public function update(Request $request): Response
    {
        return $this->success($this->business->save($request->post()));
    }

    #[Permission('删除标签', 'question:tag:edit')]
    public function destroy(Request $request): Response
    {
        $this->business->delete((int) $request->post('id'));

        return $this->success('删除成功');
    }
}
