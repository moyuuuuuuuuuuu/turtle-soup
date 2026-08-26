<?php

declare(strict_types=1);

namespace App\Auth\Business;

use App\Auth\Entities\PlayerContext;
use App\Auth\Formats\PlayerFormat;
use App\Auth\Models\AnonymousSession;
use App\Auth\Models\RefreshSession;
use App\Auth\Models\User;
use App\Auth\Models\UserLoginLog;
use App\Auth\Repositories\PlayerRepository;
use App\Auth\Services\EmailCodeService;
use App\Auth\Services\PlayerTokenService;
use App\Common\Enums\ErrorCode;
use App\Common\Support\PublicId;
use support\Db;
use Throwable;

final class PlayerAuthBusiness
{
    public function __construct(private readonly PlayerRepository $repository = new PlayerRepository(), private readonly PlayerTokenService $tokens = new PlayerTokenService(), private readonly EmailCodeService $codes = new EmailCodeService())
    {
    }

    public function register(array $data, string $anonymousToken, array $device): array
    {
        $username = trim((string) ($data['username'] ?? ''));
        $normalized = mb_strtolower($username);
        $email = EmailCodeService::normalizeEmail((string) ($data['email'] ?? ''));
        $password = (string) ($data['password'] ?? '');
        $this->validateProfile($username, $email, $password);
        if (User::query()->where('username_normalized', $normalized)->exists()) {
            ErrorCode::AUTH_USERNAME_EXISTS->throw();
        }
        if (User::query()->where('email_normalized', $email)->exists()) {
            ErrorCode::AUTH_EMAIL_EXISTS->throw();
        }
        $this->codes->verify($email, 'register', (string) ($data['email_code'] ?? ''));
        return Db::transaction(function () use ($username, $normalized, $email, $password, $anonymousToken, $device) {
            $user = User::create(['public_id' => PublicId::make(), 'username' => $username, 'username_normalized' => $normalized, 'email' => $email, 'email_normalized' => $email, 'password_hash' => $this->passwordHash($password), 'status' => 'active', 'email_verified_at' => date('Y-m-d H:i:s'), 'last_login_at' => date('Y-m-d H:i:s')]);
            return $this->loginResponse($user, $anonymousToken, $device, 'register');
        });
    }

    public function passwordLogin(array $data, string $anonymousToken, array $device): array
    {
        $account = mb_strtolower(trim((string) ($data['account'] ?? '')));
        $user = $this->repository->byAccount($account);
        if (!$user instanceof User || !password_verify((string) ($data['password'] ?? ''), (string) $user->password_hash)) {
            ErrorCode::AUTH_CREDENTIALS_INVALID->throw();
        }
        return $this->loginResponse($user, $anonymousToken, $device, 'password');
    }

    public function emailCodeLogin(array $data, string $anonymousToken, array $device): array
    {
        $email = EmailCodeService::normalizeEmail((string) ($data['email'] ?? ''));
        $this->codes->verify($email, 'login', (string) ($data['email_code'] ?? ''));
        $user = $this->repository->byEmail($email);
        if (!$user instanceof User) {
            ErrorCode::AUTH_USER_NOT_FOUND->throw();
        }
        return $this->loginResponse($user, $anonymousToken, $device, 'email_code');
    }

    public function refresh(string $token): array
    {
        $result = $this->tokens->refresh($token);
        $context = $this->tokens->authenticate($result['access_token']);
        $user = $this->user($context);
        return array_merge($result, ['user' => PlayerFormat::user($user), 'merged_games' => 0]);
    }
    public function me(PlayerContext $context): array
    {
        return PlayerFormat::user($this->user($context));
    }
    public function sessions(PlayerContext $context): array
    {
        return $this->repository->sessions((int) $this->user($context)->id);
    }
    public function logout(PlayerContext $context, bool $all = false): void
    {
        $this->tokens->revoke((int) $this->user($context)->id, $context->refreshSessionId, $all);
    }
    public function revokeSession(PlayerContext $context, string $publicId): void
    {
        $session = RefreshSession::query()->where('public_id', $publicId)->where('user_id', $this->user($context)->id)->first();
        if (!$session instanceof RefreshSession) {
            ErrorCode::AUTH_TOKEN_INVALID->throw();
        } $session->update(['revoked_at' => date('Y-m-d H:i:s'), 'revoke_reason' => 'user_revoked']);
    }

    public function changeUsername(PlayerContext $context, string $username): array
    {
        $user = $this->user($context);
        $normalized = mb_strtolower(trim($username));
        $this->validateUsername($username);
        if ($user->username_changed_at && strtotime((string) $user->username_changed_at) > time() - 2592000) {
            ErrorCode::AUTH_USERNAME_CHANGE_LIMITED->throw();
        }
        if (User::query()->where('username_normalized', $normalized)->where('id', '<>', $user->id)->exists()) {
            ErrorCode::AUTH_USERNAME_EXISTS->throw();
        }
        $user->update(['username' => trim($username), 'username_normalized' => $normalized, 'username_changed_at' => date('Y-m-d H:i:s')]);
        return PlayerFormat::user($user->refresh());
    }

    public function changeEmail(PlayerContext $context, array $data): array
    {
        $user = $this->user($context);
        if (!password_verify((string) ($data['password'] ?? ''), (string) $user->password_hash)) {
            ErrorCode::AUTH_CREDENTIALS_INVALID->throw();
        }
        $email = EmailCodeService::normalizeEmail((string) ($data['email'] ?? ''));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            ErrorCode::PARAM_ERROR->throw();
        }
        if (User::query()->where('email_normalized', $email)->where('id', '<>', $user->id)->exists()) {
            ErrorCode::AUTH_EMAIL_EXISTS->throw();
        }
        $this->codes->verify($email, 'change_email', (string) ($data['email_code'] ?? ''));
        $oldEmail = (string) $user->email;
        $user->update(['email' => $email, 'email_normalized' => $email, 'email_verified_at' => date('Y-m-d H:i:s')]);
        RefreshSession::query()->where('user_id', $user->id)->where('id', '<>', $context->refreshSessionId)->whereNull('revoked_at')->update(['revoked_at' => date('Y-m-d H:i:s'), 'revoke_reason' => 'email_changed']);
        $this->codes->notify($oldEmail, '海龟汤账号邮箱已变更', '你的海龟汤账号邮箱已完成变更。如非本人操作，请立即修改密码并联系管理员。');
        return PlayerFormat::user($user->refresh());
    }

    public function changePassword(PlayerContext $context, string $current, string $next, array $device): array
    {
        $user = $this->user($context);
        if (!password_verify($current, (string) $user->password_hash)) {
            ErrorCode::AUTH_CREDENTIALS_INVALID->throw();
        } $this->validatePassword($next);
        $user->update(['password_hash' => $this->passwordHash($next)]);
        $this->tokens->revoke((int) $user->id, null, true, 'password_changed');
        return array_merge($this->tokens->issue($user, $device[0], $device[1], $device[2]), ['user' => PlayerFormat::user($user), 'merged_games' => 0]);
    }

    public function resetPassword(array $data, array $device): array
    {
        $email = EmailCodeService::normalizeEmail((string) ($data['email'] ?? ''));
        $password = (string) ($data['password'] ?? '');
        $this->validatePassword($password);
        $this->codes->verify($email, 'reset_password', (string) ($data['email_code'] ?? ''));
        $user = $this->repository->byEmail($email);
        if (!$user instanceof User) {
            ErrorCode::AUTH_USER_NOT_FOUND->throw();
        } $user->update(['password_hash' => $this->passwordHash($password)]);
        $this->tokens->revoke((int) $user->id, null, true, 'password_reset');
        return array_merge($this->tokens->issue($user, $device[0], $device[1], $device[2]), ['user' => PlayerFormat::user($user), 'merged_games' => 0]);
    }

    private function loginResponse(User $user, string $anonymousToken, array $device, string $method): array
    {
        if ($user->status !== 'active') {
            ErrorCode::AUTH_USER_DISABLED->throw();
        } $merged = 0;
        if ($anonymousToken !== '') {
            try {
                $session = (new AnonymousSessionBusiness())->authenticate($anonymousToken);
            } catch (Throwable) {
                $session = null;
            }
            if ($session instanceof \App\Auth\Models\AnonymousSession) {
                try {
                    $merged = Db::transaction(fn () => $this->repository->mergeAnonymous($user, $session));
                } catch (Throwable $exception) {
                    ErrorCode::AUTH_ANONYMOUS_MERGE_FAILED->throw(previous: $exception);
                }
            }
        }
        $user->update(['last_login_at' => date('Y-m-d H:i:s')]);
        $tokens = $this->tokens->issue($user, $device[0], $device[1], $device[2]);
        UserLoginLog::create(['user_id' => $user->id, 'method' => $method, 'result' => 'succeeded', 'identifier_masked' => self::maskEmail((string) $user->email), 'device_name' => $device[1], 'ip_hash' => hash('sha256', $device[3] ?? '')]);
        return array_merge($tokens, ['user' => PlayerFormat::user($user->refresh()), 'merged_games' => $merged]);
    }

    private function user(PlayerContext $context): User
    {
        $user = User::find($context->userId);
        if (!$user instanceof User) {
            ErrorCode::AUTH_TOKEN_INVALID->throw();
        } return $user;
    }
    private function validateProfile(string $u, string $e, string $p): void
    {
        $this->validateUsername($u);
        if (!filter_var($e, FILTER_VALIDATE_EMAIL)) {
            ErrorCode::PARAM_ERROR->throw();
        } $this->validatePassword($p);
    }
    private function validateUsername(string $u): void
    {
        if (!preg_match('/^[A-Za-z0-9_]{3,24}$/', trim($u))) {
            ErrorCode::PARAM_ERROR->throw();
        }
    }
    private function validatePassword(string $p): void
    {
        if (strlen($p) < 8 || strlen($p) > 72) {
            ErrorCode::PARAM_ERROR->throw();
        }
    }
    private function passwordHash(string $p): string
    {
        return password_hash($p, defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT);
    }
    private static function maskEmail(string $e): string
    {
        [$a, $b] = array_pad(explode('@', $e, 2), 2, '');
        return mb_substr($a, 0, 1).'***@'.$b;
    }
}
