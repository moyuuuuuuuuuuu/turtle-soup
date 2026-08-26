# Turtle Soup Development TODO

This file is the canonical cross-branch implementation plan. Update it when scope, ordering, or completion status changes.

## Non-negotiable constraints

- [ ] Keep environment configuration complete and update `.env.example` whenever a feature adds settings.
- [ ] Never commit credentials, tokens, production identifiers, or real user data.
- [ ] Do not access or mutate a database without explicit user permission for that operation.
- [ ] Express every schema change through migration files.
- [ ] Do not run migration or seed commands merely because their files were created.
- [ ] Keep turtle soup gameplay as the primary product; language learning remains optional assistance.
- [ ] Anonymous users may play single-player games but may not create or join multiplayer rooms.
- [ ] Keep AI advisory; PHP remains authoritative for game state, permissions, progress, and completion.

## Milestone 0 — Development foundation (`v0.1.0`)

### `system-manage`

- [x] Prepare an available PHP and Composer runtime.
- [x] Install Composer dependencies and complete the SaiAdmin installation flow.
- [x] Configure Eloquent ORM for new project modules.
- [x] Add safe MySQL and Redis configuration templates without connecting until authorized.
- [x] Define user API routing under `/api/v1/*` while preserving current SaiAdmin management APIs.
- [x] Integrate the common error enums, `BaseException`, `BusinessException`, `BaseController`, and user API response format with the global handler.
- [x] Add request ID middleware and structured logging.
- [x] Add Redis-backed queue configuration.
- [x] Add PHPUnit, static analysis, and code-style checks.
- [x] Add a health endpoint that does not leak configuration or force database access.

### `ui`

- [ ] Remove Wot Starter demo pages and assets that are not needed by the product.
- [ ] Establish development, staging, and production configuration.
- [ ] Implement the typed user API client and stable error-code handling.
- [ ] Implement access-token storage and refresh orchestration.
- [ ] Implement the WebSocket client foundation and reconnect state machine.
- [ ] Establish product theme, layout, and Chinese locale resources.

### `system-manage-ui`

- [ ] Complete SaiAdmin frontend/backend local integration.
- [ ] Configure development and production API endpoints.
- [ ] Remove unrelated demonstration modules without disturbing upstream infrastructure.
- [ ] Retain permissions, menus, dictionaries, logs, attachments, and system configuration facilities.

### Acceptance

- [ ] All three branches install and start in a documented environment.
- [ ] Management login works.
- [ ] The user client can call `/api/v1/health`.
- [ ] CI performs baseline checks.

## Milestone 1 — Anonymous and registered identity

- [ ] Create migration files for users, user identities, guest sessions, refresh tokens, email verification codes, and login logs.
- [ ] Review migrations without running them until explicitly authorized.
- [x] Implement anonymous session issuance and renewal.
- [ ] Implement username/password registration and login.
- [ ] Implement email/password registration and login.
- [ ] Implement email verification-code login.
- [ ] Hash passwords with a current password API.
- [ ] Hash email codes; enforce expiry, single use, rate limits, and attempt limits.
- [ ] Implement short-lived access tokens and revocable refresh tokens.
- [ ] Implement logout, password change, and password recovery.
- [ ] Migrate eligible anonymous history when an account is registered or upgraded.
- [ ] Model identities so future WeChat Mini Program and Official Account identities can be bound.
- [ ] Enforce registered-user-only multiplayer access on the backend.

### Acceptance

- [ ] Anonymous users can access allowed single-player operations.
- [ ] Anonymous multiplayer access returns `room.login_required`.
- [ ] All three initial login methods work.
- [ ] Token refresh and revocation work.

## Milestone 2 — Question bank and management

- [x] Create and execute reviewed migrations for questions, translations, reasoning points, point translations, hints, hint translations, tags, relations, and publish versions.
- [x] Implement Eloquent models and repositories.
- [x] Implement Business use cases for draft, publish, unpublish, copy, and publish-version history/restore.
- [x] Implement management pages for question content, answer, difficulty, tags, player range, points, required points, weights, and three-level hints.
- [x] Add Chinese and English content support through translation records.
- [x] Add content-risk metadata, confirmation, and authorization around protected answers.
- [ ] Prepare ten reviewed seed/test questions: three easy, four medium, and three hard.

### Acceptance

- [x] Authorized administrators can create, review, preview, publish, and unpublish questions.
- [ ] Draft questions never appear in public APIs.
- [x] Protected answers are not exposed to unauthorized users in list, game preview, history detail, or write endpoints.

## Milestone 3 — Coze workflows

- [x] Obtain a minimal exported Coze workflow ZIP from the target workspace as a compatibility template.
- [x] Build `turtle_question_judge_v1` import package.
- [x] Build `turtle_guess_judge_v1` import package.
- [ ] Build `turtle_hint_generator_v1` import package.
- [x] Build and verify the `turtle_content_parser_v1` import package.
- [ ] Define JSON Schemas, examples, prompts, test cases, and version metadata for every workflow.
- [ ] Add anti-answer-leak, contradiction, irrelevant-question, malformed-output, and prompt-injection tests.
- [x] Implement Coze configuration and service-token credentials through environment/config files.
- [x] Implement AI contracts, Coze Service adapters, response validation, timeout, retry, and safe logging for single-player judging.
- [x] Persist workflow version, latency, attempts, and safe execution status without protected content.

### Acceptance

- [x] Workflow ZIP files import into the target Coze workspace.
- [x] Webman can execute the published content-parser workflow and reject invalid outputs.
- [ ] AI failure cannot corrupt game state or expose the answer.

## Milestone 4 — Single-player MVP (`v0.3.0`)

- [x] Create, review, execute, and verify migrations for anonymous sessions, games, messages, discovered points, guesses, hints, and AI requests.
- [x] Implement game states: `CREATED`, `PLAYING`, `SOLVED`, `FINISHED`, and `ABANDONED`.
- [x] Implement create game, game detail, snapshot, history, and finish HTTP APIs.
- [x] Implement versioned WebSocket authentication, join, snapshot, question, hint, guess, solved, finished, and error events.
- [x] Enforce request ID idempotency for question, hint, and final-guess commands.
- [x] Persist message ordering and validate candidate reasoning points in PHP.
- [x] Implement disconnect/reconnect snapshot recovery.
- [x] Implement user pages for home, question list/detail, game, final guess, result, and history.
- [x] Ensure clients cannot receive the answer before the game finishes.

### Acceptance

- [ ] An anonymous user can complete a full single-player game over WebSocket.
- [ ] Reconnection restores authoritative state.
- [ ] Repeated requests do not duplicate AI calls or state changes.

## Milestone 5 — Multiplayer cooperation (`v0.4.0`)

- [ ] Create reviewed migrations for rooms, room players, invitations, and room events.
- [ ] Implement room creation, code/link join, readiness, owner start, chat, leave, owner transfer, and timeout cleanup.
- [ ] Serialize or safely queue concurrent room questions.
- [ ] Broadcast persisted/ordered game results to all room members.
- [ ] Implement multiplayer reconnect and snapshots.
- [ ] Implement room and multiplayer result pages in `ui`.

### Acceptance

- [ ] Anonymous users cannot create or join rooms.
- [ ] Two to eight registered users can finish a game with consistent ordering.
- [ ] Individual disconnects do not break the room.

## Milestone 6 — Optional English assistance (`v0.5.0`)

- [ ] Build `language_tutor_v1`, `game_review_v1`, and `content_localizer_v1` workflows.
- [ ] Implement Chinese, English assistance, and English challenge modes.
- [ ] Keep `interface_locale`, `content_locale`, and `learning_locale` separate.
- [ ] Add CEFR level settings.
- [ ] Add concise non-blocking correction and expression suggestions.
- [ ] Add “help me say this” and gradual assistance.
- [ ] Add an optional end-of-game language review.
- [ ] Ensure tutor failure never blocks the game-host response.

## Milestone 7 — Learning records (`v0.6.0`)

- [ ] Create reviewed migrations for language profiles, vocabulary, mistakes, reports, and reviews.
- [ ] Add vocabulary collection, common mistakes, useful expressions, and history.
- [ ] Add simple 1/3/7/15/30-day review intervals.
- [ ] Keep tables and classes language-neutral for future target languages.

## Milestone 8 — Observability and notifications

- [ ] Integrate HTTP, WebSocket, and background-job exception reporting.
- [ ] Publish exception-report events from the global handler.
- [ ] Aggregate fingerprints and thresholds in Redis.
- [ ] Implement asynchronous email notification jobs.
- [ ] Configure severity, reportability, policy, thresholds, cooldowns, and recipients through environment/configuration.
- [ ] Disable email alerts outside production by default.
- [ ] Redact tokens, credentials, personal data, conversation history, and protected answers.
- [ ] Track safe AI latency/failure, connection, room, game, queue, and notification metrics.

### Acceptance

- [ ] `room.full` never sends email.
- [ ] A single AI timeout does not send immediate email.
- [ ] Repeated AI failures produce one aggregated alert after threshold.
- [ ] Critical infrastructure failures alert immediately with deduplication.
- [ ] Email delivery failure cannot recursively trigger email.

## Milestone 9 — WebRTC voice experiment (`v0.7.0`)

- [ ] Start only after text single-player and multiplayer are stable.
- [ ] Add microphone permission and realtime connection lifecycle.
- [ ] Add speech-to-text and feed transcripts into the existing Game Business flow.
- [ ] Add TTS/realtime voice output, interruption, and subtitles.
- [ ] Fall back safely to text when voice connectivity fails.
- [ ] Keep voice as an input/output channel rather than a separate game implementation.

## Release readiness

- [ ] Complete threat and answer-leak review.
- [ ] Complete migration review and rollback strategy; run only after explicit authorization.
- [ ] Verify `.env.example` and deployment configuration for every service.
- [ ] Verify backups, logs, alert routing, rate limits, and queue recovery.
- [ ] Test H5 and WeChat Mini Program builds.
- [ ] Document deployment, rollback, Coze workflow import, and operational recovery.
