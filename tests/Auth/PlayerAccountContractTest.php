<?php

declare(strict_types=1);

namespace Tests\Auth;

use App\Auth\Entities\PlayerContext;
use App\Auth\Enums\IdentityProvider;
use App\Auth\Services\EmailCodeService;
use App\Common\Enums\ErrorCode;
use PHPUnit\Framework\TestCase;

final class PlayerAccountContractTest extends TestCase
{
    public function testPlayerContextDistinguishesRegisteredAndAnonymousPlayers(): void
    {
        self::assertTrue((new PlayerContext(userId: 10, refreshSessionId: 20))->isUser());
        self::assertFalse((new PlayerContext(anonymousSessionId: 30))->isUser());
    }

    public function testEmailNormalizationAndWechatProvidersAreStable(): void
    {
        self::assertSame('player@example.com', EmailCodeService::normalizeEmail(' Player@Example.COM '));
        self::assertSame('wechat_mini_program', IdentityProvider::WECHAT_MINI_PROGRAM->value);
        self::assertSame('wechat_official_account', IdentityProvider::WECHAT_OFFICIAL_ACCOUNT->value);
    }

    public function testPlayerAuthenticationErrorsRemainStable(): void
    {
        self::assertSame('auth.device_limit_reached', ErrorCode::AUTH_DEVICE_LIMIT_REACHED->value);
        self::assertSame(401, ErrorCode::AUTH_TOKEN_INVALID->httpStatus());
        self::assertFalse(ErrorCode::AUTH_CREDENTIALS_INVALID->isReportable());
    }

    public function testMigrationContainsNoSeederOrRawSql(): void
    {
        $migration = file_get_contents(dirname(__DIR__, 2).'/database/migrations/20260826010004_create_player_accounts.php');
        self::assertIsString($migration);
        self::assertStringContainsString("'turtle_users'", $migration);
        self::assertStringContainsString("'avatar_url'", $migration);
        self::assertStringContainsString("'avatar_object_key'", $migration);
        self::assertStringContainsString("'wechat_mini_program'", file_get_contents(dirname(__DIR__, 2).'/app/Auth/Enums/IdentityProvider.php'));
        self::assertStringNotContainsString('DemoSeeder', $migration);
        self::assertStringNotContainsString('execute("', $migration);
    }

    public function testPasswordLoginUsesEmailOnlyAndAvatarUsesBos(): void
    {
        $business = file_get_contents(dirname(__DIR__, 2).'/app/Auth/Business/PlayerAuthBusiness.php');
        $repository = file_get_contents(dirname(__DIR__, 2).'/app/Auth/Repositories/PlayerRepository.php');
        self::assertIsString($business);
        self::assertIsString($repository);
        self::assertStringContainsString("\$data['email']", $business);
        self::assertStringNotContainsString('byAccount', $repository);
        self::assertStringContainsString('BosAvatarService', $business);
    }

    public function testBosAvatarObjectKeyDoesNotExposePublicPlayerId(): void
    {
        $service = file_get_contents(dirname(__DIR__, 2).'/app/Auth/Services/BosAvatarService.php');
        self::assertIsString($service);
        self::assertStringContainsString("hash('sha256', substr(\$publicId, 0, 2).'/'.\$publicId)", $service);
        self::assertStringNotContainsString("'/'.\$publicId.'.svg'", $service);
    }

    public function testFailedLoginAuditAndEmailOnlyContractArePresent(): void
    {
        $business = file_get_contents(dirname(__DIR__, 2).'/app/Auth/Business/PlayerAuthBusiness.php');
        self::assertIsString($business);
        self::assertStringContainsString("recordLogin(\$user, 'password', 'failed'", $business);
        self::assertStringContainsString("recordLogin(\$user, 'email_code', 'failed'", $business);
        self::assertStringContainsString("\$data['email']", $business);
        self::assertStringNotContainsString("\$data['account']", $business);
    }

    public function testEmailCodeLoginCreatesPasswordlessAccountWhenEmailIsNew(): void
    {
        $business = file_get_contents(dirname(__DIR__, 2).'/app/Auth/Business/PlayerAuthBusiness.php');
        self::assertIsString($business);
        self::assertStringContainsString("'password_hash' => ''", $business);
        self::assertStringContainsString("\$this->avatars->createDefault(\$email, \$publicId)", $business);
        self::assertStringContainsString("(string) \$user->password_hash !== ''", $business);
    }

    public function testSmtpSenderConfigurationUsesCanonicalEnvironmentName(): void
    {
        $config = file_get_contents(dirname(__DIR__, 2).'/config/mail.php');
        $mailer = file_get_contents(dirname(__DIR__, 2).'/app/Auth/Services/SmtpMailer.php');
        self::assertIsString($config);
        self::assertIsString($mailer);
        self::assertStringContainsString("env('SMTP_FROM_ADDRESS'", $config);
        self::assertStringContainsString('SMTP configuration is incomplete', $mailer);
        self::assertStringContainsString("'mail_from'", $mailer);
    }

    public function testEmailCodeRateLimitsAreIsolatedByPurpose(): void
    {
        $service = file_get_contents(dirname(__DIR__, 2).'/app/Auth/Services/EmailCodeService.php');
        self::assertIsString($service);
        $sendMethod = strstr($service, 'public function send');
        self::assertIsString($sendMethod);
        $sendMethod = strstr($sendMethod, 'public function verify', true);
        self::assertIsString($sendMethod);
        self::assertSame(4, substr_count($sendMethod, "where('purpose', \$purpose)"));
    }
}
