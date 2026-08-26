<?php

declare(strict_types=1);

namespace App\Game\Controllers;

use App\Auth\Business\AnonymousSessionBusiness;
use App\Auth\Models\AnonymousSession;
use App\Common\Controllers\BaseController;
use App\Game\Business\GameBusiness;
use support\Request;
use support\Response;

final class GameController extends BaseController
{
    private function session(Request $request): AnonymousSession
    {
        $token = preg_replace('/^Bearer\s+/i', '', (string)$request->header('Authorization', '')) ?? '';
        return (new AnonymousSessionBusiness())->authenticate($token);
    }
    private function requestId(Request $request): string
    {
        return (string)($request->header('X-Request-Id', '') ?: $request->post('request_id', ''));
    }
    public function create(Request $request): Response
    {
        return $this->success((new GameBusiness())->create($this->session($request), (string)$request->post('question_id'), (string)$request->post('language', 'zh-CN'), filter_var($request->post('risk_confirmed', false), FILTER_VALIDATE_BOOL)), $this->requestId($request));
    }
    public function read(Request $request): Response
    {
        return $this->success((new GameBusiness())->snapshot($this->session($request), (string)$request->get('id')), $this->requestId($request));
    }
    public function history(Request $request): Response
    {
        return $this->success((new GameBusiness())->history($this->session($request)), $this->requestId($request));
    }
    public function ask(Request $request): Response
    {
        return $this->success((new GameBusiness())->ask($this->session($request), (string)$request->post('id'), $this->requestId($request), (string)$request->post('question')), $this->requestId($request));
    }
    public function hint(Request $request): Response
    {
        return $this->success((new GameBusiness())->hint($this->session($request), (string)$request->post('id'), $this->requestId($request), (int)$request->post('level')), $this->requestId($request));
    }
    public function guess(Request $request): Response
    {
        return $this->success((new GameBusiness())->guess($this->session($request), (string)$request->post('id'), $this->requestId($request), (string)$request->post('guess')), $this->requestId($request));
    }
    public function abandon(Request $request): Response
    {
        return $this->success((new GameBusiness())->abandon($this->session($request), (string)$request->post('id')), $this->requestId($request));
    }
}
