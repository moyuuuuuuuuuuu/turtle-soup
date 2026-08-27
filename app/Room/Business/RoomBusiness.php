<?php

declare(strict_types=1);

namespace App\Room\Business;

use App\Auth\Entities\PlayerContext;
use App\Auth\Models\User;
use App\Common\Enums\ErrorCode;
use App\Common\Support\PublicId;
use App\Game\Business\GameBusiness;
use App\Game\Models\Game;
use App\Game\Models\GamePlayer;
use App\Question\Models\Question;
use App\Room\Enums\RoomVisibility;
use App\Room\Formats\RoomFormat;
use App\Room\Models\Room;
use App\Room\Models\RoomMember;
use App\Room\Repositories\RoomRepository;
use Illuminate\Database\Eloquent\Collection;
use support\Db;

final class RoomBusiness
{
    /** @var array<int, array<int, true>> */
    private static array $mutedMembers = [];
    public function __construct(private readonly RoomRepository $repository = new RoomRepository())
    {
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function create(PlayerContext $context, array $input): array
    {
        $userId = $this->userId($context);
        $gamePublicId = trim((string) ($input['game_id'] ?? ''));
        if ($gamePublicId === '') {
            ErrorCode::PARAM_ERROR->throw();
        }
        $maxPlayers = (int) ($input['max_players'] ?? 4);
        if ($maxPlayers < 2 || $maxPlayers > 8) {
            ErrorCode::PARAM_ERROR->throw();
        }
        $user = User::query()->find($userId);
        if (!$user instanceof User) {
            ErrorCode::AUTH_TOKEN_INVALID->throw();
        }
        $name = (string) $user->username . '的房间';
        $visibility = RoomVisibility::tryFrom((string) ($input['visibility'] ?? RoomVisibility::PRIVATE->value));
        if (!$visibility instanceof RoomVisibility) {
            ErrorCode::PARAM_ERROR->throw();
        }

        return Db::transaction(function () use ($gamePublicId, $input, $userId, $maxPlayers, $name, $visibility): array {
            User::query()->whereKey($userId)->lockForUpdate()->firstOrFail();
            $game = Game::query()->where('public_id', $gamePublicId)->lockForUpdate()->first();
            if (!$game instanceof Game || (int) $game->user_id !== $userId || !in_array($game->status, ['created', 'playing'], true)) {
                ErrorCode::GAME_NOT_FOUND->throw();
            }
            if ($game->room_id) {
                $room = Room::query()->whereKey($game->room_id)->lockForUpdate()->first();
                if (!$room instanceof Room || !$this->repository->member($room, $userId)) {
                    ErrorCode::ROOM_MEMBER_REQUIRED->throw();
                }
                $this->leaveOtherRooms($userId, (int) $room->id);

                return RoomFormat::snapshot($this->repository->hydrated($room), $userId);
            }
            $this->leaveOtherRooms($userId);
            /** @var Room $room */
            $room = Room::create([
                'public_id' => PublicId::make(),
                'invite_code' => $this->inviteCode(),
                'owner_user_id' => $userId,
                'question_id' => $game->question_id,
                'name' => $name,
                'status' => 'playing',
                'visibility' => $visibility->value,
                'max_players' => $maxPlayers,
                'content_locale' => (string) ($input['language'] ?? $game->content_locale),
                'risk_confirmed' => (bool) $game->risk_confirmed,
                'started_at' => $game->started_at ?: date('Y-m-d H:i:s'),
            ]);
            $now = date('Y-m-d H:i:s');
            GamePlayer::query()->firstOrCreate(
                ['game_id' => (int) $game->id, 'user_id' => $userId],
                ['status' => 'playing', 'joined_at' => $now],
            );
            RoomMember::create(['room_id' => $room->id, 'user_id' => $userId, 'role' => 'owner', 'status' => 'active', 'is_ready' => true, 'joined_at' => $now, 'last_active_at' => $now]);
            $room->update(['game_id' => $game->id]);
            $game->update(['room_id' => $room->id]);

            return RoomFormat::snapshot($this->repository->hydrated($room), $userId);
        });
    }

    /** @return array<string, mixed> */
    public function join(PlayerContext $context, string $id = '', string $inviteCode = ''): array
    {
        $userId = $this->userId($context);

        return Db::transaction(function () use ($id, $inviteCode, $userId): array {
            User::query()->whereKey($userId)->lockForUpdate()->firstOrFail();
            $room = $inviteCode !== '' ? $this->repository->findByInvite($inviteCode, true) : $this->repository->find($id, true);
            if (!$room instanceof Room) {
                ($inviteCode !== '' ? ErrorCode::ROOM_INVITE_INVALID : ErrorCode::ROOM_NOT_FOUND)->throw();
            }
            if (!in_array($room->status, ['waiting', 'playing'], true)) {
                ErrorCode::ROOM_STATUS_INVALID->throw();
            }
            $existing = $this->repository->member($room, $userId, false);
            if ($existing?->status === 'active') {
                $this->leaveOtherRooms($userId, (int) $room->id);
                if ($room->status === 'waiting') {
                    $room->update(['status' => 'playing', 'started_at' => $room->started_at ?: date('Y-m-d H:i:s')]);
                }

                return RoomFormat::snapshot($this->repository->hydrated($room), $userId);
            }
            if (RoomMember::query()->where('room_id', $room->id)->where('status', 'active')->count() >= (int) $room->max_players) {
                ErrorCode::ROOM_FULL->throw();
            }
            $this->leaveOtherRooms($userId, (int) $room->id);
            $now = date('Y-m-d H:i:s');
            if ($existing instanceof RoomMember) {
                $existing->update(['status' => 'active', 'is_ready' => true, 'joined_at' => $now, 'left_at' => null, 'last_active_at' => $now]);
            } else {
                RoomMember::create(['room_id' => $room->id, 'user_id' => $userId, 'role' => 'member', 'status' => 'active', 'is_ready' => true, 'joined_at' => $now, 'last_active_at' => $now]);
            }
            if ($room->status === 'waiting') {
                $room->update(['status' => 'playing', 'started_at' => $room->started_at ?: $now]);
            }
            if ($room->game_id) {
                GamePlayer::query()->firstOrCreate(
                    ['game_id' => (int) $room->game_id, 'user_id' => $userId],
                    ['status' => 'playing', 'joined_at' => $now],
                );
            }

            return RoomFormat::snapshot($this->repository->hydrated($room), $userId);
        });
    }

    /** @return array<string, mixed> */
    public function snapshot(PlayerContext $context, string $id): array
    {
        $userId = $this->userId($context);
        $room = $this->required($id);
        $this->assertMember($room, $userId);

        return $this->format($room, $userId);
    }

    /** @return array<string, mixed> */
    public function ready(PlayerContext $context, string $id, bool $ready): array
    {
        $userId = $this->userId($context);
        $room = $this->required($id);
        $member = $this->assertMember($room, $userId);
        $member->update(['is_ready' => true, 'last_active_at' => date('Y-m-d H:i:s')]);
        if ($room->status === 'waiting') {
            $room->update(['status' => 'playing', 'started_at' => $room->started_at ?: date('Y-m-d H:i:s')]);
        }

        return RoomFormat::snapshot($this->repository->hydrated($room), $userId);
    }

    /** @return array<string, mixed> */
    public function start(PlayerContext $context, string $id): array
    {
        $userId = $this->userId($context);

        return Db::transaction(function () use ($id, $userId): array {
            $room = $this->required($id, true);
            $this->assertOwner($room, $userId);
            if (!in_array($room->status, ['waiting', 'playing'], true)) {
                ErrorCode::ROOM_STATUS_INVALID->throw();
            }
            if ($room->status === 'waiting') {
                $room->update(['status' => 'playing', 'started_at' => $room->started_at ?: date('Y-m-d H:i:s')]);
            }

            return RoomFormat::snapshot($this->repository->hydrated($room), $userId);
        });
    }

    /** @return array{question_id: string, status: string} */
    public function resolveQuestion(PlayerContext $context, string $inviteCode): array
    {
        $this->userId($context);
        $room = $this->repository->findByInvite(trim($inviteCode));
        if (!$room instanceof Room) {
            ErrorCode::ROOM_INVITE_INVALID->throw();
        }
        $question = Question::query()->find($room->question_id);
        if (!$question instanceof Question || $question->status !== 'published') {
            ErrorCode::QUESTION_NOT_FOUND->throw();
        }

        return [
            'question_id' => (string) $question->public_id,
            'status' => (string) $room->status,
        ];
    }

    public function next(PlayerContext $context, string $id, string $questionPublicId, bool $riskConfirmed): array
    {
        $userId = $this->userId($context);
        $question = Question::query()
            ->where('public_id', $questionPublicId)
            ->where('status', 'published')
            ->whereIn('risk_level', ['safe', 'caution'])
            ->first();
        if (!$question instanceof Question) {
            ErrorCode::QUESTION_NOT_FOUND->throw();
        }

        return Db::transaction(function () use ($context, $id, $question, $riskConfirmed, $userId): array {
            $room = $this->required($id, true);
            $this->assertOwner($room, $userId);
            if ($room->status !== 'finished') {
                ErrorCode::ROOM_STATUS_INVALID->throw();
            }
            $now = date('Y-m-d H:i:s');
            if ($room->game_id) {
                GamePlayer::query()->firstOrCreate(
                    ['game_id' => (int) $room->game_id, 'user_id' => $userId],
                    ['status' => 'playing', 'joined_at' => $now],
                );
            }
            $snapshot = (new GameBusiness())->create($context, (string) $question->public_id, (string) $room->content_locale, $riskConfirmed, (int) $room->id);
            $game = Game::query()->where('public_id', (string) $snapshot['id'])->first();
            if (!$game instanceof Game) {
                throw new \RuntimeException('game.not_found');
            }
            $room->update([
                'question_id' => $question->id,
                'game_id' => $game->id,
                'status' => 'playing',
                'risk_confirmed' => $riskConfirmed,
                'started_at' => $now,
                'finished_at' => null,
            ]);
            RoomMember::query()->where('room_id', $room->id)->where('status', 'active')->update(['is_ready' => true]);
            $memberIds = RoomMember::query()->where('room_id', $room->id)->where('status', 'active')->pluck('user_id');
            foreach ($memberIds as $memberId) {
                GamePlayer::query()->firstOrCreate(
                    ['game_id' => (int) $game->id, 'user_id' => (int) $memberId],
                    ['status' => 'playing', 'joined_at' => $now],
                );
            }

            return RoomFormat::snapshot($this->repository->hydrated($room), $userId);
        });
    }

    public function leave(PlayerContext $context, string $id): void
    {
        $userId = $this->userId($context);

        Db::transaction(function () use ($id, $userId): void {
            $room = $this->required($id, true);
            $member = $this->assertMember($room, $userId);
            $this->deactivateMembership($room, $member, $userId);
        });
    }

    public function close(PlayerContext $context, string $id): void
    {
        $userId = $this->userId($context);

        Db::transaction(function () use ($id, $userId): void {
            $room = $this->required($id, true);
            $this->assertOwner($room, $userId);
            if ($room->status === 'closed') {
                ErrorCode::ROOM_STATUS_INVALID->throw();
            }
            $now = date('Y-m-d H:i:s');
            $room->update(['status' => 'closed', 'finished_at' => $room->finished_at ?: $now]);
            RoomMember::query()
                ->where('room_id', $room->id)
                ->where('status', 'active')
                ->update(['status' => 'left', 'is_ready' => false, 'left_at' => $now]);
            unset(self::$mutedMembers[(int) $room->id]);
        });
    }

    /** @return array<string, mixed> */
    public function updateVisibility(PlayerContext $context, string $id, string $visibility): array
    {
        $userId = $this->userId($context);
        $roomVisibility = RoomVisibility::tryFrom($visibility);
        if (!$roomVisibility instanceof RoomVisibility) {
            ErrorCode::PARAM_ERROR->throw();
        }

        return Db::transaction(function () use ($id, $userId, $roomVisibility): array {
            $room = $this->required($id, true);
            $this->assertOwner($room, $userId);
            if (!in_array($room->status, ['waiting', 'playing'], true)) {
                ErrorCode::ROOM_STATUS_INVALID->throw();
            }
            if ($room->visibility !== $roomVisibility->value) {
                $room->update(['visibility' => $roomVisibility->value]);
            }

            return $this->format($room, $userId);
        });
    }

    public function touch(PlayerContext $context, string $id): void
    {
        $userId = $this->userId($context);
        $room = $this->required($id);
        $member = $this->assertMember($room, $userId);
        $member->update(['last_active_at' => date('Y-m-d H:i:s')]);
    }

    public function closeIdleRooms(int $idleSeconds): int
    {
        $idleSeconds = max(60, $idleSeconds);
        $cutoff = date('Y-m-d H:i:s', time() - $idleSeconds);
        $roomIds = Room::query()
            ->whereIn('status', ['waiting', 'playing'])
            ->whereDoesntHave('members', static function ($members) use ($cutoff): void {
                $members->where('status', 'active')->where('last_active_at', '>=', $cutoff);
            })
            ->orderBy('id')
            ->limit(100)
            ->pluck('id');

        $closed = 0;
        foreach ($roomIds as $roomId) {
            $didClose = Db::transaction(function () use ($roomId, $cutoff): bool {
                $room = Room::query()->whereKey($roomId)->lockForUpdate()->first();
                if (!$room instanceof Room || !in_array($room->status, ['waiting', 'playing'], true)) {
                    return false;
                }
                $hasRecentMember = RoomMember::query()
                    ->where('room_id', $room->id)
                    ->where('status', 'active')
                    ->where('last_active_at', '>=', $cutoff)
                    ->exists();
                if ($hasRecentMember) {
                    return false;
                }
                $now = date('Y-m-d H:i:s');
                $room->update(['status' => 'closed', 'finished_at' => $now]);
                RoomMember::query()->where('room_id', $room->id)->where('status', 'active')->update([
                    'status' => 'left',
                    'is_ready' => false,
                    'left_at' => $now,
                ]);
                if ($room->game_id) {
                    Game::query()->whereKey($room->game_id)->whereIn('status', ['created', 'playing'])->update([
                        'status' => 'abandoned',
                        'finished_at' => $now,
                    ]);
                    GamePlayer::query()->where('game_id', $room->game_id)->where('status', 'playing')->update([
                        'status' => 'abandoned',
                        'completed_at' => $now,
                    ]);
                }
                unset(self::$mutedMembers[(int) $room->id]);

                return true;
            });
            $closed += $didClose ? 1 : 0;
        }

        return $closed;
    }

    /** @return array<string, mixed> */
    public function chat(PlayerContext $context, string $id, string $requestId, string $content): array
    {
        $userId = $this->userId($context);
        $content = trim($content);
        if ($content === '' || mb_strlen($content) > 2000) {
            ErrorCode::PARAM_ERROR->throw();
        }

        return Db::transaction(function () use ($id, $requestId, $content, $userId): array {
            $room = $this->required($id, true);
            $this->assertMember($room, $userId);
            if (isset(self::$mutedMembers[(int) $room->id][$userId])) {
                throw new \RuntimeException('room.member_muted');
            }
            if (in_array($room->status, ['finished', 'closed'], true)) {
                ErrorCode::ROOM_STATUS_INVALID->throw();
            }
            $this->repository->appendMessage($room, $userId, $requestId, $content);

            return RoomFormat::snapshot($this->repository->hydrated($room), $userId);
        });
    }

    /** @return array<int, array<string, mixed>> */
    public function mine(PlayerContext $context): array
    {
        $userId = $this->userId($context);
        /** @var Collection<int, Room> $rooms */
        $rooms = Room::query()->whereHas('members', static fn ($query) => $query->where('user_id', $userId)->where('status', 'active'))->orderByDesc('id')->limit(50)->get();

        return $rooms->map(fn (Room $room): array => RoomFormat::snapshot($this->repository->hydrated($room), $userId))->all();
    }

    /** @return array<int, array<string, mixed>> */
    public function publicRooms(PlayerContext $context): array
    {
        $userId = $this->userId($context);
        /** @var Collection<int, Room> $rooms */
        $rooms = Room::query()->where('visibility', 'public')->whereIn('status', ['waiting', 'playing'])->orderByDesc('id')->limit(50)->get();

        return $rooms->map(fn (Room $room): array => RoomFormat::snapshot($this->repository->hydrated($room), $userId))->all();
    }

    private function required(string $id, bool $lock = false): Room
    {
        return $this->repository->find($id, $lock) ?? ErrorCode::ROOM_NOT_FOUND->throw();
    }

    private function assertMember(Room $room, int $userId): RoomMember
    {
        return $this->repository->member($room, $userId) ?? ErrorCode::ROOM_MEMBER_REQUIRED->throw();
    }

    private function assertOwner(Room $room, int $userId): void
    {
        if ((int) $room->owner_user_id !== $userId) {
            ErrorCode::ROOM_OWNER_REQUIRED->throw();
        }
    }

    /** @return array<string, mixed> */
    public function mute(PlayerContext $context, string $id, int $targetUserId, bool $muted): array
    {
        $userId = $this->userId($context);
        $room = $this->required($id);
        $this->assertOwner($room, $userId);
        $target = $this->assertMember($room, $targetUserId);
        if ($target->role === 'owner') {
            ErrorCode::PARAM_ERROR->throw();
        }
        if ($muted) {
            self::$mutedMembers[(int) $room->id][$targetUserId] = true;
        } else {
            unset(self::$mutedMembers[(int) $room->id][$targetUserId]);
        }

        return $this->format($room, $userId);
    }

    public function kick(PlayerContext $context, string $id, int $targetUserId): void
    {
        $userId = $this->userId($context);
        Db::transaction(function () use ($id, $userId, $targetUserId): void {
            $room = $this->required($id, true);
            $this->assertOwner($room, $userId);
            $target = $this->assertMember($room, $targetUserId);
            if ($target->role === 'owner') {
                ErrorCode::PARAM_ERROR->throw();
            }
            $this->deactivateMembership($room, $target, $targetUserId);
            unset(self::$mutedMembers[(int) $room->id][$targetUserId]);
        });
    }

    /** @return array<string, mixed> */
    private function format(Room $room, int $viewerId): array
    {
        return RoomFormat::snapshot(
            $this->repository->hydrated($room),
            $viewerId,
            array_map('intval', array_keys(self::$mutedMembers[(int) $room->id] ?? [])),
        );
    }

    private function leaveOtherRooms(int $userId, ?int $exceptRoomId = null): void
    {
        $memberships = RoomMember::query()
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->when($exceptRoomId !== null, static fn ($query) => $query->where('room_id', '!=', $exceptRoomId))
            ->orderBy('room_id')
            ->lockForUpdate()
            ->get();

        foreach ($memberships as $membership) {
            $room = Room::query()->whereKey($membership->room_id)->lockForUpdate()->first();
            if (!$room instanceof Room) {
                continue;
            }
            $this->deactivateMembership($room, $membership, $userId);
        }
    }

    private function deactivateMembership(Room $room, RoomMember $member, int $userId): void
    {
        $now = date('Y-m-d H:i:s');
        $member->update([
            'role' => 'member',
            'status' => 'left',
            'is_ready' => false,
            'left_at' => $now,
        ]);
        unset(self::$mutedMembers[(int) $room->id][$userId]);
        if ($room->game_id) {
            GamePlayer::query()->where('game_id', $room->game_id)->where('user_id', $userId)->delete();
        }
        if ((int) $room->owner_user_id !== $userId) {
            return;
        }
        $successor = RoomMember::query()
            ->where('room_id', $room->id)
            ->where('status', 'active')
            ->inRandomOrder()
            ->lockForUpdate()
            ->first();
        if (!$successor instanceof RoomMember) {
            $room->update(['status' => 'closed', 'finished_at' => $now]);
            unset(self::$mutedMembers[(int) $room->id]);

            return;
        }
        $successor->update(['role' => 'owner', 'is_ready' => true]);
        $successorName = User::query()->whereKey($successor->user_id)->value('username');
        $room->update([
            'owner_user_id' => $successor->user_id,
            'name' => is_string($successorName) && $successorName !== '' ? $successorName . '的房间' : $room->name,
        ]);
    }

    private function userId(PlayerContext $context): int
    {
        if (!$context->isUser()) {
            ErrorCode::ROOM_LOGIN_REQUIRED->throw();
        }

        return (int) $context->userId;
    }

    private function inviteCode(): string
    {
        do {
            $code = strtoupper(substr(strtr(base64_encode(random_bytes(8)), '+/', 'AZ'), 0, 8));
        } while (Room::query()->where('invite_code', $code)->exists());

        return $code;
    }
}
