<?php

declare(strict_types=1);

namespace App\Room\Business;

use App\Auth\Entities\PlayerContext;
use App\Common\Enums\ErrorCode;
use App\Common\Support\PublicId;
use App\Game\Business\GameBusiness;
use App\Game\Models\Game;
use App\Question\Models\Question;
use App\Room\Formats\RoomFormat;
use App\Room\Models\Room;
use App\Room\Models\RoomMember;
use App\Room\Repositories\RoomRepository;
use Illuminate\Database\Eloquent\Collection;
use support\Db;

final class RoomBusiness
{
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
        $question = Question::query()
            ->where('public_id', (string) ($input['question_id'] ?? ''))
            ->where('status', 'published')
            ->whereIn('risk_level', ['safe', 'caution'])
            ->first();
        if (!$question instanceof Question) {
            ErrorCode::QUESTION_NOT_FOUND->throw();
        }
        $maxPlayers = (int) ($input['max_players'] ?? 4);
        if ($maxPlayers < 2 || $maxPlayers > 8) {
            ErrorCode::PARAM_ERROR->throw();
        }
        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '' || mb_strlen($name) > 80) {
            ErrorCode::PARAM_ERROR->throw();
        }
        $visibility = (string) ($input['visibility'] ?? 'private');
        if (!in_array($visibility, ['private', 'public'], true)) {
            ErrorCode::PARAM_ERROR->throw();
        }

        return Db::transaction(function () use ($context, $input, $question, $userId, $maxPlayers, $name, $visibility): array {
            /** @var Room $room */
            $room = Room::create([
                'public_id' => PublicId::make(),
                'invite_code' => $this->inviteCode(),
                'owner_user_id' => $userId,
                'question_id' => $question->id,
                'name' => $name,
                'status' => 'waiting',
                'visibility' => $visibility,
                'max_players' => $maxPlayers,
                'content_locale' => (string) ($input['language'] ?? 'zh-CN'),
                'risk_confirmed' => (bool) ($input['risk_confirmed'] ?? false),
            ]);
            $now = date('Y-m-d H:i:s');
            RoomMember::create(['room_id' => $room->id, 'user_id' => $userId, 'role' => 'owner', 'status' => 'active', 'is_ready' => true, 'joined_at' => $now, 'last_active_at' => $now]);
            $snapshot = (new GameBusiness())->create($context, (string) $question->public_id, (string) $room->content_locale, (bool) $room->risk_confirmed);
            $game = Game::query()->where('public_id', (string) $snapshot['id'])->first();
            if (!$game instanceof Game) {
                throw new \RuntimeException('game.not_found');
            }
            $game->update(['room_id' => $room->id]);
            $room->update(['game_id' => $game->id]);

            return RoomFormat::snapshot($this->repository->hydrated($room), $userId);
        });
    }

    /** @return array<string, mixed> */
    public function join(PlayerContext $context, string $id = '', string $inviteCode = ''): array
    {
        $userId = $this->userId($context);

        return Db::transaction(function () use ($id, $inviteCode, $userId): array {
            $room = $inviteCode !== '' ? $this->repository->findByInvite($inviteCode, true) : $this->repository->find($id, true);
            if (!$room instanceof Room) {
                ($inviteCode !== '' ? ErrorCode::ROOM_INVITE_INVALID : ErrorCode::ROOM_NOT_FOUND)->throw();
            }
            if ($room->status !== 'waiting') {
                ErrorCode::ROOM_STATUS_INVALID->throw();
            }
            $existing = $this->repository->member($room, $userId, false);
            if ($existing?->status === 'active') {
                return RoomFormat::snapshot($this->repository->hydrated($room), $userId);
            }
            if (RoomMember::query()->where('room_id', $room->id)->where('status', 'active')->count() >= (int) $room->max_players) {
                ErrorCode::ROOM_FULL->throw();
            }
            $now = date('Y-m-d H:i:s');
            if ($existing instanceof RoomMember) {
                $existing->update(['status' => 'active', 'is_ready' => false, 'joined_at' => $now, 'left_at' => null, 'last_active_at' => $now]);
            } else {
                RoomMember::create(['room_id' => $room->id, 'user_id' => $userId, 'role' => 'member', 'status' => 'active', 'is_ready' => false, 'joined_at' => $now, 'last_active_at' => $now]);
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

        return RoomFormat::snapshot($this->repository->hydrated($room), $userId);
    }

    /** @return array<string, mixed> */
    public function ready(PlayerContext $context, string $id, bool $ready): array
    {
        $userId = $this->userId($context);
        $room = $this->required($id);
        if ($room->status !== 'waiting') {
            ErrorCode::ROOM_STATUS_INVALID->throw();
        }
        $member = $this->assertMember($room, $userId);
        $member->update(['is_ready' => $member->role === 'owner' ? true : $ready, 'last_active_at' => date('Y-m-d H:i:s')]);

        return RoomFormat::snapshot($this->repository->hydrated($room), $userId);
    }

    /** @return array<string, mixed> */
    public function start(PlayerContext $context, string $id): array
    {
        $userId = $this->userId($context);

        return Db::transaction(function () use ($id, $userId): array {
            $room = $this->required($id, true);
            $this->assertOwner($room, $userId);
            if ($room->status !== 'waiting') {
                ErrorCode::ROOM_STATUS_INVALID->throw();
            }
            $members = RoomMember::query()->where('room_id', $room->id)->where('status', 'active')->get();
            if ($members->count() < 2 || $members->contains(static fn (RoomMember $member): bool => !$member->is_ready)) {
                ErrorCode::ROOM_MEMBERS_NOT_READY->throw();
            }
            $room->update(['status' => 'playing', 'started_at' => date('Y-m-d H:i:s')]);

            return RoomFormat::snapshot($this->repository->hydrated($room), $userId);
        });
    }

    public function leave(PlayerContext $context, string $id): void
    {
        $userId = $this->userId($context);

        Db::transaction(function () use ($id, $userId): void {
            $room = $this->required($id, true);
            if ($room->status !== 'waiting') {
                ErrorCode::ROOM_STATUS_INVALID->throw();
            }
            $member = $this->assertMember($room, $userId);
            $member->update(['status' => 'left', 'is_ready' => false, 'left_at' => date('Y-m-d H:i:s')]);
            if ((int) $room->owner_user_id !== $userId) {
                return;
            }
            $successor = RoomMember::query()
                ->where('room_id', $room->id)
                ->where('status', 'active')
                ->orderBy('joined_at')
                ->lockForUpdate()
                ->first();
            if (!$successor instanceof RoomMember) {
                $room->update(['status' => 'closed', 'finished_at' => date('Y-m-d H:i:s')]);

                return;
            }
            $successor->update(['role' => 'owner', 'is_ready' => true]);
            $room->update(['owner_user_id' => $successor->user_id]);
        });
    }

    public function close(PlayerContext $context, string $id): void
    {
        $userId = $this->userId($context);
        $room = $this->required($id);
        $this->assertOwner($room, $userId);
        if (in_array($room->status, ['finished', 'closed'], true)) {
            ErrorCode::ROOM_STATUS_INVALID->throw();
        }
        $room->update(['status' => 'closed', 'finished_at' => date('Y-m-d H:i:s')]);
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
        $rooms = Room::query()->where('visibility', 'public')->where('status', 'waiting')->orderByDesc('id')->limit(50)->get();

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
