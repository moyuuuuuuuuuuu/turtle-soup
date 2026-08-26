<?php

declare(strict_types=1);

namespace App\Room\Controllers;

use App\Auth\Services\PlayerPrincipalService;
use App\Common\Controllers\BaseController;
use App\Room\Business\RoomBusiness;
use support\Request;
use support\Response;

final class RoomController extends BaseController
{
    public function create(Request $request): Response
    {
        return $this->success((new RoomBusiness())->create($this->context($request), $request->post()), $this->requestId($request));
    }

    public function join(Request $request): Response
    {
        return $this->success((new RoomBusiness())->join($this->context($request), (string) $request->post('id', ''), (string) $request->post('invite_code', '')), $this->requestId($request));
    }

    public function read(Request $request): Response
    {
        return $this->success((new RoomBusiness())->snapshot($this->context($request), (string) $request->get('id')), $this->requestId($request));
    }

    public function mine(Request $request): Response
    {
        return $this->success((new RoomBusiness())->mine($this->context($request)), $this->requestId($request));
    }

    public function index(Request $request): Response
    {
        return $this->success((new RoomBusiness())->publicRooms($this->context($request)), $this->requestId($request));
    }

    public function ready(Request $request): Response
    {
        return $this->success((new RoomBusiness())->ready($this->context($request), (string) $request->post('id'), filter_var($request->post('ready', true), FILTER_VALIDATE_BOOL)), $this->requestId($request));
    }

    public function start(Request $request): Response
    {
        return $this->success((new RoomBusiness())->start($this->context($request), (string) $request->post('id')), $this->requestId($request));
    }

    public function leave(Request $request): Response
    {
        (new RoomBusiness())->leave($this->context($request), (string) $request->post('id'));

        return $this->success([], $this->requestId($request));
    }

    public function close(Request $request): Response
    {
        (new RoomBusiness())->close($this->context($request), (string) $request->post('id'));

        return $this->success([], $this->requestId($request));
    }

    private function context(Request $request): \App\Auth\Entities\PlayerContext
    {
        $authorization = (string) $request->header('authorization', '');
        $token = preg_replace('/^Bearer\s+/i', '', $authorization) ?: '';

        return (new PlayerPrincipalService())->authenticate($token);
    }

    private function requestId(Request $request): string
    {
        return (string) ($request->header('X-Request-Id', '') ?: $request->post('request_id', ''));
    }
}
