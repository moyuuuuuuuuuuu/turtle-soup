<?php

declare(strict_types=1);

namespace Tests\Home;

use PHPUnit\Framework\TestCase;

final class HomeStatsContractTest extends TestCase
{
    public function testPublicHomeStatsUsePersistedGameFacts(): void
    {
        $business = file_get_contents(dirname(__DIR__, 2) . '/app/Home/Business/HomeStatsBusiness.php');
        $routes = file_get_contents(dirname(__DIR__, 2) . '/config/route.php');

        self::assertIsString($business);
        self::assertIsString($routes);
        self::assertStringContainsString("->count('user_id')", $business);
        self::assertStringContainsString("->count('anonymous_session_id')", $business);
        self::assertStringContainsString("where('status', 'solved')->count()", $business);
        self::assertStringContainsString('TIMESTAMPDIFF(SECOND, started_at, finished_at)', $business);
        self::assertStringContainsString("'/api/v1/home/stats'", $routes);
    }
}
