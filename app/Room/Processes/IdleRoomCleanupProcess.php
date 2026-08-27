<?php

declare(strict_types=1);

namespace App\Room\Processes;

use App\Room\Business\RoomBusiness;
use support\Log;
use Throwable;
use Workerman\Timer;
use Workerman\Worker;

final class IdleRoomCleanupProcess
{
    public function onWorkerStart(Worker $worker): void
    {
        $interval = max(30, (int) config('room.cleanup_interval_seconds', 60));
        $idleSeconds = max(60, (int) config('room.idle_timeout_seconds', 1800));
        Timer::add($interval, static function () use ($idleSeconds): void {
            try {
                $closed = (new RoomBusiness())->closeIdleRooms($idleSeconds);
                if ($closed > 0) {
                    Log::info('Closed idle multiplayer rooms', ['count' => $closed]);
                }
            } catch (Throwable $exception) {
                Log::error('Idle room cleanup failed', ['exception' => $exception]);
            }
        });
    }
}
