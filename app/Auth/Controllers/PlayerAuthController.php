<?php

declare(strict_types=1);

namespace App\Auth\Controllers;

use App\Auth\Business\PlayerAuthBusiness;
use App\Auth\Services\EmailCodeService;
use App\Auth\Services\PlayerPrincipalService;
use App\Common\Controllers\BaseController;
use App\Common\Enums\ErrorCode;
use support\Request;
use support\Response;
use Webman\Http\UploadFile;

final class PlayerAuthController extends BaseController
{
    public function sendEmailCode(Request $request): Response
    {
        (new EmailCodeService())->send((string) $request->post('email'), (string) $request->post('purpose'), $request->getRealIp());
        return $this->success(['sent' => true]);
    }
    public function register(Request $request): Response
    {
        return $this->success((new PlayerAuthBusiness())->register($request->post(), $this->anonymousToken($request), $this->device($request)));
    }
    public function passwordLogin(Request $request): Response
    {
        return $this->success((new PlayerAuthBusiness())->passwordLogin($request->post(), $this->anonymousToken($request), $this->device($request)));
    }
    public function emailCodeLogin(Request $request): Response
    {
        return $this->success((new PlayerAuthBusiness())->emailCodeLogin($request->post(), $this->anonymousToken($request), $this->device($request)));
    }
    public function refresh(Request $request): Response
    {
        return $this->success((new PlayerAuthBusiness())->refresh((string) $request->post('refresh_token')));
    }
    public function me(Request $request): Response
    {
        return $this->success((new PlayerAuthBusiness())->me($this->context($request)));
    }
    public function sessions(Request $request): Response
    {
        return $this->success((new PlayerAuthBusiness())->sessions($this->context($request)));
    }
    public function logout(Request $request): Response
    {
        (new PlayerAuthBusiness())->logout($this->context($request));
        return $this->success(['logged_out' => true]);
    }
    public function logoutAll(Request $request): Response
    {
        (new PlayerAuthBusiness())->logout($this->context($request), true);
        return $this->success(['logged_out' => true]);
    }
    public function revokeSession(Request $request): Response
    {
        (new PlayerAuthBusiness())->revokeSession($this->context($request), (string) $request->post('session_id'));
        return $this->success(['revoked' => true]);
    }
    public function changeUsername(Request $request): Response
    {
        return $this->success((new PlayerAuthBusiness())->changeUsername($this->context($request), (string) $request->post('username')));
    }
    public function updateProfile(Request $request): Response
    {
        return $this->success((new PlayerAuthBusiness())->updateProfile($this->context($request), $request->post()));
    }
    public function changeAvatar(Request $request): Response
    {
        $file = $request->file('avatar');
        if (!$file instanceof UploadFile) {
            ErrorCode::PARAM_MISSING->throw('请选择头像文件');
        }

        return $this->success((new PlayerAuthBusiness())->changeAvatar($this->context($request), $file));
    }
    public function changeEmail(Request $request): Response
    {
        return $this->success((new PlayerAuthBusiness())->changeEmail($this->context($request), $request->post()));
    }
    public function changePassword(Request $request): Response
    {
        return $this->success((new PlayerAuthBusiness())->changePassword($this->context($request), (string) $request->post('current_password'), (string) $request->post('password'), $this->device($request)));
    }
    public function resetPassword(Request $request): Response
    {
        return $this->success((new PlayerAuthBusiness())->resetPassword($request->post(), $this->device($request)));
    }

    private function context(Request $request): \App\Auth\Entities\PlayerContext
    {
        return (new PlayerPrincipalService())->authenticate($this->bearer($request));
    }
    private function bearer(Request $request): string
    {
        return preg_replace('/^Bearer\s+/i', '', (string) $request->header('Authorization', '')) ?? '';
    }
    private function anonymousToken(Request $request): string
    {
        return (string) $request->header('X-Anonymous-Token', '');
    }
    /** @return array{string,string,string,string} */
    private function device(Request $request): array
    {
        return [(string) $request->header('X-Device-Id', 'unknown'), (string) $request->header('X-Device-Name', '未知设备'), (string) $request->header('X-Platform', 'unknown'), $request->getRealIp()];
    }
}
