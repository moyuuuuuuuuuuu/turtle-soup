<?php

declare(strict_types=1);

namespace App\Question\Controllers;

use App\Common\Controllers\BaseController;
use App\Question\Business\PublicQuestionBusiness;
use support\Request;
use support\Response;

final class PublicQuestionController extends BaseController
{
    public function index(Request $request): Response
    {
        return $this->success((new PublicQuestionBusiness())->page($request->only(['difficulty', 'tag_id', 'language', 'keyword', 'featured']), max(1, (int) $request->get('page', 1)), min(50, max(1, (int) $request->get('page_size', 20)))), (string) $request->header('X-Request-Id', ''));
    }
    public function read(Request $request): Response
    {
        return $this->success((new PublicQuestionBusiness())->detail((string) $request->get('id'), (string) $request->get('language', 'zh-CN')), (string) $request->header('X-Request-Id', ''));
    }
    public function random(Request $request): Response
    {
        return $this->success((new PublicQuestionBusiness())->random($request->only(['difficulty', 'tag_id', 'language'])), (string) $request->header('X-Request-Id', ''));
    }
}
