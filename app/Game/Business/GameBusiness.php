<?php

declare(strict_types=1);

namespace App\Game\Business;

use App\Auth\Models\AnonymousSession;
use App\Common\Enums\ErrorCode;
use App\Common\Support\PublicId;
use App\Game\Contracts\GameJudgeInterface;
use App\Game\Formats\GameFormat;
use App\Game\Models\Game;
use App\Game\Repositories\GameRepository;
use App\Game\Services\GameJudgeFactory;
use App\Question\Models\Question;
use support\Db;

final class GameBusiness
{
    private readonly GameRepository $repository;
    private readonly GameJudgeInterface $judge;

    public function __construct(?GameRepository $repository = null, ?GameJudgeInterface $judge = null)
    {
        $this->repository = $repository ?? new GameRepository();
        $this->judge = $judge ?? GameJudgeFactory::make();
    }
    public function create(AnonymousSession $session, string $questionPublicId, string $language, bool $riskConfirmed): array
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
        $snapshot = ['title' => $translation->title,'surface' => $translation->surface,'bottom' => $translation->bottom,'language' => $translation->language,'risk_level' => $question->risk_level,'points' => $question->points->map(fn ($p) => ['key' => 'point_'.$p->id,'content' => $p->translations->firstWhere('language', $translation->language)?->content ?? $p->translations->firstWhere('language', 'zh-CN')?->content,'required' => (bool)$p->is_required,'weight' => (int)$p->weight])->all(),'hints' => $question->hints->mapWithKeys(fn ($h) => [(int)$h->level => $h->translations->firstWhere('language', $translation->language)?->content ?? $h->translations->firstWhere('language', 'zh-CN')?->content])->all()];
        $limit = (array)config('game.question_limits');
        $game = Game::create(['public_id' => PublicId::make(),'question_id' => $question->id,'anonymous_session_id' => $session->id,'status' => 'created','content_locale' => $translation->language,'difficulty' => $question->difficulty,'question_limit' => $limit[(int)$question->difficulty] ?? 12,'risk_confirmed' => $riskConfirmed,'question_snapshot' => $snapshot]);
        return GameFormat::snapshot($this->repository->hydrated($game));
    }
    public function snapshot(AnonymousSession $session, string $id): array
    {
        return GameFormat::snapshot($this->repository->hydrated($this->required($session, $id)));
    }
    public function history(AnonymousSession $session): array
    {
        return Game::query()->where('anonymous_session_id', $session->id)->orderByDesc('id')->get()->map(fn (Game $g) => ['id' => $g->public_id,'status' => $g->status,'title' => ((array)$g->question_snapshot)['title'] ?? '','difficulty' => (int)$g->difficulty,'question_count' => (int)$g->question_count,'create_time' => $g->create_time])->all();
    }
    public function ask(AnonymousSession $session, string $id, string $requestId, string $question): array
    {
        if (trim($question) === '' || mb_strlen($question) > 500) {
            ErrorCode::PARAM_ERROR->throw();
        }
        return Db::transaction(function () use ($session, $id, $requestId, $question) {
            $game = $this->required($session, $id, true);
            if ($duplicate = $this->repository->duplicate($game, $requestId)) {
                return GameFormat::snapshot($this->repository->hydrated($game));
            } if (!in_array($game->status, ['created','playing'], true)) {
                ErrorCode::GAME_STATUS_INVALID->throw();
            } if ($game->question_count >= $game->question_limit) {
                ErrorCode::GAME_QUESTION_LIMIT_REACHED->throw();
            } $context = (array)$game->question_snapshot;
            $result = $this->judge->judgeQuestion($context, $question);
            if (!in_array($result['answer'] ?? '', ['yes','no','irrelevant','partial'], true)) {
                ErrorCode::AI_INVALID_RESPONSE->throw();
            } $this->repository->message($game, $requestId.':q', 'player', 'question', $question);
            $this->repository->message($game, $requestId, 'host', 'answer', (string)$result['reply'], ['answer' => $result['answer']]);
            $this->repository->discover($game, (array)($result['matched_point_keys'] ?? []));
            $game->update(['status' => 'playing','question_count' => (int)$game->question_count + 1,'started_at' => $game->started_at ?: date('Y-m-d H:i:s')]);
            return GameFormat::snapshot($this->repository->hydrated($game));
        });
    }
    public function hint(AnonymousSession $session, string $id, string $requestId, int $level): array
    {
        return Db::transaction(function () use ($session, $id, $requestId, $level) {
            $game = $this->required($session, $id, true);
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
    public function guess(AnonymousSession $session, string $id, string $requestId, string $guess): array
    {
        if (trim($guess) === '' || mb_strlen($guess) > 2000) {
            ErrorCode::PARAM_ERROR->throw();
        }return Db::transaction(function () use ($session, $id, $requestId, $guess) {
            $game = $this->required($session, $id, true);
            if (!in_array($game->status, ['created','playing'], true) || $game->guess()->exists()) {
                ErrorCode::GAME_STATUS_INVALID->throw();
            }$result = $this->judge->judgeGuess((array)$game->question_snapshot, $guess);
            if (!isset($result['is_solved'])) {
                ErrorCode::AI_INVALID_RESPONSE->throw();
            }$this->repository->guess($game, $requestId, $guess, $result);
            $this->repository->discover($game, (array)($result['matched_point_keys'] ?? []));
            $this->repository->message($game, $requestId, 'player', 'guess', $guess);
            $this->repository->message($game, $requestId.':result', 'host', 'result', (string)($result['summary'] ?? ''), ['is_solved' => (bool)$result['is_solved']]);
            $game->update(['status' => $result['is_solved'] ? 'solved' : 'finished','finished_at' => date('Y-m-d H:i:s')]);
            return GameFormat::snapshot($this->repository->hydrated($game));
        });
    }
    public function abandon(AnonymousSession $session, string $id): array
    {
        $game = $this->required($session, $id);
        if (!in_array($game->status, ['created','playing'], true)) {
            ErrorCode::GAME_STATUS_INVALID->throw();
        }$game->update(['status' => 'abandoned','finished_at' => date('Y-m-d H:i:s')]);
        return GameFormat::snapshot($this->repository->hydrated($game));
    }
    private function required(AnonymousSession $session,string $id,bool $lock = false): Game
    {
        return $this->repository->find($id,(int)$session->id,$lock) ?? ErrorCode::GAME_NOT_FOUND->throw();
    }
}
