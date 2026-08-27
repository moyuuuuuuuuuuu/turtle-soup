<?php

declare(strict_types=1);

namespace App\Game\Business;

use App\Auth\Entities\PlayerContext;
use App\Common\Enums\ErrorCode;
use App\Common\Support\PublicId;
use App\Game\Contracts\GameJudgeInterface;
use App\Game\Formats\GameFormat;
use App\Game\Models\Game;
use App\Game\Models\GamePlayer;
use App\Game\Repositories\GameRepository;
use App\Game\Services\GameJudgeFactory;
use App\Question\Models\Question;
use Illuminate\Database\Eloquent\Collection;
use support\Db;
use Throwable;

final class GameBusiness
{
    private readonly GameRepository $repository;
    private readonly GameJudgeInterface $judge;

    public function __construct(?GameRepository $repository = null, ?GameJudgeInterface $judge = null)
    {
        $this->repository = $repository ?? new GameRepository();
        $this->judge = $judge ?? GameJudgeFactory::make();
    }
    public function create(PlayerContext $context, string $questionPublicId, string $language, bool $riskConfirmed, ?int $roomId = null): array
    {
        $question = Question::with(['translations','points.translations','hints.translations','tags'])->where('public_id', $questionPublicId)->where('status', 'published')->whereIn('risk_level', ['safe','caution'])->first();
        if (!$question instanceof Question) {
            ErrorCode::QUESTION_NOT_FOUND->throw();
        }
        if ($question->risk_level === 'caution' && !$riskConfirmed) {
            ErrorCode::QUESTION_RISK_CONFIRMATION_REQUIRED->throw();
        }
        $translation = $question->translations->firstWhere('language', $language) ?? $question->translations->firstWhere('language', 'zh-CN');
        if (!$translation) {
            ErrorCode::QUESTION_TRANSLATION_INCOMPLETE->throw();
        }
        if ($roomId === null) {
            $existing = Game::query()
                ->where('question_id', $question->id)
                ->whereNull('room_id')
                ->whereIn('status', ['created', 'playing'])
                ->when(
                    $context->isUser(),
                    static fn ($query) => $query->where('user_id', $context->userId),
                    static fn ($query) => $query->whereNull('user_id')->where('anonymous_session_id', $context->anonymousSessionId),
                )
                ->orderByDesc('id')
                ->first();
            if ($existing instanceof Game) {
                return GameFormat::snapshot($this->repository->hydrated($existing));
            }
        }
        $snapshot = ['title' => $translation->title,'surface' => $translation->surface,'bottom' => $translation->bottom,'language' => $translation->language,'risk_level' => $question->risk_level,'risk_types' => array_values((array) $question->risk_types),'risk_note' => $question->risk_note,'tags' => $question->tags->map(static fn ($tag): array => ['id' => (int) $tag->id, 'name' => (string) $tag->name])->values()->all(),'points' => $question->points->map(fn ($p) => ['key' => 'point_'.$p->id,'content' => $p->translations->firstWhere('language', $translation->language)?->content ?? $p->translations->firstWhere('language', 'zh-CN')?->content,'required' => (bool)$p->is_required,'weight' => (int)$p->weight])->all(),'hints' => $question->hints->mapWithKeys(fn ($h) => [(int)$h->level => $h->translations->firstWhere('language', $translation->language)?->content ?? $h->translations->firstWhere('language', 'zh-CN')?->content])->all()];
        $limit = (array)config('game.question_limits');
        $game = new Game();
        $game->fill(['public_id' => PublicId::make(),'question_id' => $question->id,'anonymous_session_id' => $context->anonymousSessionId,'user_id' => $context->userId,'room_id' => $roomId,'status' => 'created','content_locale' => $translation->language,'difficulty' => $question->difficulty,'question_limit' => $limit[(int)$question->difficulty] ?? 12,'risk_confirmed' => $riskConfirmed,'question_snapshot' => $snapshot]);
        $game->save();
        if ($context->isUser()) {
            GamePlayer::query()->create(['game_id' => $game->id, 'user_id' => $context->userId, 'status' => 'playing', 'joined_at' => date('Y-m-d H:i:s')]);
        }
        return GameFormat::snapshot($this->repository->hydrated($game));
    }
    public function snapshot(PlayerContext $context, string $id): array
    {
        return GameFormat::snapshot($this->repository->hydrated($this->required($context, $id)));
    }
    public function history(PlayerContext $context): array
    {
        $query = Game::query();
        if ($context->isUser()) {
            $query->where(static function ($games) use ($context): void {
                $games
                    ->where('user_id', $context->userId)
                    ->orWhereHas('players', static fn ($players) => $players->where('user_id', $context->userId));
            });
        } else {
            $query->whereNull('room_id')->where('anonymous_session_id', $context->anonymousSessionId);
        }
        /** @var Collection<int, Game> $games */
        $games = $query->orderByDesc('id')->get();

        $history = [];
        foreach ($games as $game) {
            $history[] = [
                'id' => (string) $game->public_id,
                'status' => (string) $game->status,
                'title' => (string) (((array) $game->question_snapshot)['title'] ?? ''),
                'difficulty' => (int) $game->difficulty,
                'question_count' => (int) $game->question_count,
                'create_time' => (string) $game->create_time,
            ];
        }

        return $history;
    }
    public function ask(PlayerContext $context, string $id, string $requestId, string $question): array
    {
        if (trim($question) === '' || mb_strlen($question) > 500) {
            ErrorCode::PARAM_ERROR->throw();
        }
        $game = $this->required($context, $id);
        if ($this->repository->duplicate($game, $requestId)) {
            return GameFormat::snapshot($this->repository->hydrated($game));
        }
        $this->assertCanAsk($game);
        $questionSnapshot = (array)$game->question_snapshot;
        $result = $this->runJudge($game, $requestId, 'turtle_question_judge_v1', fn () => $this->judge->judgeQuestion($questionSnapshot, $question));
        $result['matched_point_keys'] = $this->validPointKeys($questionSnapshot, (array)$result['matched_point_keys']);

        return Db::transaction(function () use ($context, $id, $requestId, $question, $result) {
            $game = $this->required($context, $id, true);
            if ($this->repository->duplicate($game, $requestId)) {
                return GameFormat::snapshot($this->repository->hydrated($game));
            }
            $this->assertCanAsk($game);
            if (!in_array($result['answer'] ?? '', ['yes','no','irrelevant','partial'], true)) {
                ErrorCode::AI_INVALID_RESPONSE->throw();
            }
            $this->repository->message($game, $requestId.':q', 'player', 'question', $question, [], $context->userId);
            $this->repository->message($game, $requestId, 'host', 'answer', (string)$result['reply'], ['answer' => $result['answer']]);
            $this->repository->discover($game, (array)$result['matched_point_keys']);
            $game->update(['status' => 'playing','question_count' => (int)$game->question_count + 1,'started_at' => $game->started_at ?: date('Y-m-d H:i:s')]);
            return GameFormat::snapshot($this->repository->hydrated($game));
        });
    }
    public function hint(PlayerContext $context, string $id, string $requestId, int $level): array
    {
        return Db::transaction(function () use ($context, $id, $requestId, $level) {
            $game = $this->required($context, $id, true);
            if ($this->repository->duplicateHint($game, $requestId)) {
                return GameFormat::snapshot($this->repository->hydrated($game));
            }
            if (!in_array($game->status, ['created','playing'], true)) {
                ErrorCode::GAME_STATUS_INVALID->throw();
            }if ($level < 1 || $level > 3 || $game->hints()->where('level', $level)->exists()) {
                ErrorCode::GAME_HINT_UNAVAILABLE->throw();
            }$content = (string)(((array)$game->question_snapshot)['hints'][$level] ?? '');
            if ($content === '') {
                ErrorCode::GAME_HINT_UNAVAILABLE->throw();
            }$this->repository->hint($game, $level, $requestId);
            $this->repository->message($game, $requestId, 'host', 'hint', $content, ['level' => $level]);
            $game->update(['status' => 'playing','hint_count' => (int)$game->hint_count + 1,'started_at' => $game->started_at ?: date('Y-m-d H:i:s')]);
            return GameFormat::snapshot($this->repository->hydrated($game));
        });
    }
    public function guess(PlayerContext $context, string $id, string $requestId, string $guess): array
    {
        if (trim($guess) === '' || mb_strlen($guess) > 2000) {
            ErrorCode::PARAM_ERROR->throw();
        }
        $game = $this->required($context, $id);
        if ($this->repository->duplicateGuess($game, $requestId)) {
            return GameFormat::snapshot($this->repository->hydrated($game));
        }
        if (!in_array($game->status, ['created','playing'], true) || $game->guess()->exists()) {
            ErrorCode::GAME_STATUS_INVALID->throw();
        }
        $questionSnapshot = (array)$game->question_snapshot;
        $result = $this->runJudge($game, $requestId, 'turtle_guess_judge_v1', fn () => $this->judge->judgeGuess($questionSnapshot, $guess));
        $result['matched_point_keys'] = $this->validPointKeys($questionSnapshot, (array)$result['matched_point_keys']);

        return Db::transaction(function () use ($context, $id, $requestId, $guess, $result) {
            $game = $this->required($context, $id, true);
            if ($this->repository->duplicateGuess($game, $requestId)) {
                return GameFormat::snapshot($this->repository->hydrated($game));
            }
            if (!in_array($game->status, ['created','playing'], true) || $game->guess()->exists()) {
                ErrorCode::GAME_STATUS_INVALID->throw();
            }
            if (!isset($result['is_solved'])) {
                ErrorCode::AI_INVALID_RESPONSE->throw();
            }
            $this->repository->guess($game, $requestId, $guess, $result);
            $this->repository->discover($game, (array)$result['matched_point_keys']);
            $this->repository->message($game, $requestId, 'player', 'guess', $guess, [], $context->userId);
            $this->repository->message($game, $requestId.':result', 'host', 'result', (string)($result['summary'] ?? ''), ['is_solved' => (bool)$result['is_solved']]);
            $game->update(['status' => $result['is_solved'] ? 'solved' : 'finished','finished_at' => date('Y-m-d H:i:s')]);
            GamePlayer::query()->where('game_id', $game->id)->update([
                'status' => $result['is_solved'] ? 'solved' : 'finished',
                'completed_at' => date('Y-m-d H:i:s'),
            ]);
            if ($game->room_id) {
                $game->room()->update(['status' => 'finished', 'finished_at' => date('Y-m-d H:i:s')]);
            }
            return GameFormat::snapshot($this->repository->hydrated($game));
        });
    }
    public function abandon(PlayerContext $context, string $id): array
    {
        $game = $this->required($context, $id);
        if ($game->room_id && (int) $game->room?->owner_user_id !== (int) $context->userId) {
            ErrorCode::ROOM_OWNER_REQUIRED->throw();
        }
        if (!in_array($game->getAttribute('status'), ['created','playing'], true)) {
            ErrorCode::GAME_STATUS_INVALID->throw();
        }$game->update(['status' => 'abandoned','finished_at' => date('Y-m-d H:i:s')]);
        GamePlayer::query()->where('game_id', $game->id)->update(['status' => 'abandoned', 'completed_at' => date('Y-m-d H:i:s')]);
        if ($game->room_id) {
            $game->room()->update(['status' => 'finished', 'finished_at' => date('Y-m-d H:i:s')]);
        }
        return GameFormat::snapshot($this->repository->hydrated($game));
    }
    private function required(PlayerContext $context, string $id, bool $lock = false): Game
    {
        return $this->repository->find($id, $context, $lock) ?? ErrorCode::GAME_NOT_FOUND->throw();
    }
    private function assertCanAsk(Game $game): void
    {
        if (!in_array($game->status, ['created','playing'], true)) {
            ErrorCode::GAME_STATUS_INVALID->throw();
        }
        if ($game->question_count >= $game->question_limit) {
            ErrorCode::GAME_QUESTION_LIMIT_REACHED->throw();
        }
    }
    /** @return array<string, mixed> */
    private function runJudge(Game $game, string $requestId, string $workflow, callable $callback): array
    {
        $audit = $this->repository->startAiRequest($game, $requestId, $workflow);
        $startedAt = microtime(true);
        try {
            $result = $callback();
            $latency = (int)((microtime(true) - $startedAt) * 1000);
            $safe = $workflow === 'turtle_question_judge_v1'
                ? ['answer' => $result['answer'] ?? null,'matched_point_keys' => $result['matched_point_keys'] ?? [],'safety_note' => $result['safety_note'] ?? '']
                : ['is_solved' => $result['is_solved'] ?? null,'matched_point_keys' => $result['matched_point_keys'] ?? [],'safety_note' => $result['safety_note'] ?? ''];
            $this->repository->finishAiRequest($audit, $latency, $safe);
            return $result;
        } catch (Throwable $exception) {
            $code = str_starts_with($exception->getMessage(), 'ai.') ? $exception->getMessage() : 'ai.workflow_failed';
            $this->repository->failAiRequest($audit, (int)((microtime(true) - $startedAt) * 1000), $code);
            $errorCode = ErrorCode::tryFrom($code) ?? ErrorCode::AI_WORKFLOW_FAILED;
            $errorCode->throw(previous: $exception);
        }
    }
    /**
     * @param array<string, mixed> $context
     * @param list<mixed> $candidateKeys
     * @return list<string>
     */
    private function validPointKeys(array $context, array $candidateKeys): array
    {
        $allowed = array_map('strval', array_column((array)($context['points'] ?? []), 'key'));
        return array_values(array_unique(array_intersect(array_map('strval', $candidateKeys), $allowed)));
    }
}
