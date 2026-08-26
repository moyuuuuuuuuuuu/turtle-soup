# Turtle Soup Backend Engineering Instructions

## Scope and product rules

This branch contains the Webman backend derived from the `server` directory of SaiAdmin 6.1.1. It owns the existing SaiAdmin management API, the user HTTP API, WebSocket handling, application business rules, persistence, queues, and third-party integrations.

The product is a turtle soup puzzle game first. Language learning is an optional enhancement. Anonymous users may play single-player games, but only registered users may create or join multiplayer rooms. The first registered-user sign-in methods are username/password, email/password, and email verification code.

## Branch boundaries

- `ui` contains the user-facing uni-app/Wot UI client and its TypeScript clients.
- `system-manage-ui` contains the SaiAdmin management frontend.
- `system-manage` contains this backend.
- User HTTP endpoints live under `/api/v1/*`.
- Existing SaiAdmin management routes, response formats, authentication, and authorization conventions remain unchanged unless a task explicitly requires a compatible change.

## Upstream policy

- This branch is initialized from SaiAdmin tag `6.1.1` and uses Webman.
- Use Eloquent ORM for new project data access.
- Preserve the SaiAdmin MIT license and upstream copyright notices.
- Prefer extensions and project modules over edits to SaiAdmin core.
- Do not perform unrelated bulk formatting or structural rewrites of upstream files.
- Evaluate upstream upgrades explicitly; never silently follow upstream `main`.

## PHP conventions

- Use PHP strict types in every new PHP file.
- Follow PSR-12 and use PascalCase namespaces under `App\`.
- Directory names and namespaces must match in case.
- Prefer constructor injection and explicit contracts over service locators and global state.
- Avoid untyped associative arrays across architectural boundaries; use Commands, Queries, DTOs, and result objects.
- Use PHP backed enums for stable states and error codes. Do not scatter magic strings through business code.

## Architecture and dependency direction

The standard HTTP flow is:

`Request -> Controller -> Business -> Repository/Contract -> Model/Service -> DTO -> Format -> Response`

The standard realtime flow is:

`WebSocket Handler -> Message Validator -> Business -> WebSocket Format -> Push`

HTTP and WebSocket entry points must reuse the same Business classes. Dependencies point inward toward business use cases and contracts; business code must not depend on HTTP or WebSocket response objects.

### Controller

- Controllers only adapt the transport: receive input, invoke Request validation, resolve the current identity, call one Business use case, and pass the result to a Format.
- Controllers must not contain Eloquent queries, transactions, game rules, Coze calls, or third-party orchestration.
- `BaseController` may expose request identity, request IDs, and response adapters only. It must not become a generic CRUD or business helper.

### Request

- Request classes validate type, presence, length, syntax, and enum membership.
- Convert validated input to a Command or Query before calling Business.
- Business authorization and state rules do not belong in Request validators.

### Business

- Business contains concrete internal use cases such as `CreateGameBusiness`, `AskQuestionBusiness`, `SubmitGuessBusiness`, `CreateRoomBusiness`, and `UpgradeGuestBusiness`.
- One class should represent one cohesive use case; avoid large catch-all managers.
- Business owns transactions, authorization decisions, state transitions, idempotency, and orchestration of repositories and external-service contracts.
- Business must not return framework Response objects.

### Service

- Service contains adapters for external systems or infrastructure, such as Coze, WeChat, email, storage, speech recognition, text-to-speech, and future realtime voice providers.
- Service must not decide internal rules such as whether a player may join a room.
- Never expose raw third-party responses outside a Service; map them to internal DTOs.

### Entity, Command, Query, and DTO

- Entity represents an immutable query entity or domain input and must not execute database queries or depend on HTTP requests.
- Prefer explicit `Command`, `Query`, and `DTO` subdirectories when their meaning is more precise than the general Entity name.
- Eloquent persistence objects belong in Model, not Entity.

### Repository and Model

- Repository owns database reads and writes, complex scopes, locking, and persistence-specific mapping.
- Business should not assemble complex Eloquent queries directly.
- Model contains Eloquent configuration, casts, relationships, scopes, and small model-local behavior only.
- Model must not call Coze, return HTTP responses, resolve the current user, or orchestrate cross-module transactions.

### Format

- Format is the only layer that shapes application results for public HTTP or WebSocket output.
- Format may select fields, localize messages, format enums and dates, construct pagination, and redact sensitive data.
- Format must not query databases, mutate state, or call Business or Service.
- Never expose a turtle soup answer before the server has finished the game.

### Contract

- External or replaceable capabilities require contracts, including turtle judging, language tutoring, speech recognition, text-to-speech, and token issuance.
- Coze is an implementation of AI contracts, not a dependency embedded in Business.

## Common foundation

Shared primitives belong under `App\Common`, including `BaseController`, `BaseException`, error-code contracts, common response formats, middleware, and transport-independent support utilities.

Only code that remains meaningful after removing the turtle soup domain belongs in Common. Domain states such as `GameStatus`, `RoomStatus`, `AiAnswer`, `MessageType`, and `LanguageMode` belong to their domain/module enum directories.

## Error-code conventions

- Back error codes with stable English strings in `module.specific_error` form, for example `auth.login_required`, `room.full`, and `ai.workflow_timeout`.
- Use lowercase ASCII letters, digits, underscores, and the module separator dot.
- Never rename or reuse a published error-code value for a different meaning.
- Error-code enums implement `ErrorCodeInterface` and provide message, HTTP status, module, severity, reportability, notification policy, `throw()`, and response conversion behavior.
- Business code throws enum-defined exceptions rather than constructing arbitrary codes in controllers.
- The English code is never localized. The human message may initially be Chinese and must support a future locale/message-key layer.
- HTTP status and business code are separate concepts; do not return HTTP 200 for every failure.

## Exception severity and notification

Error module, severity, reportability, and notification policy are separate metadata.

Severity levels are `debug`, `info`, `warning`, `error`, and `critical`. Notification policies are `never`, `threshold`, and `immediate`.

- Expected business outcomes such as room full, login required, not found, invalid parameters, and state conflicts default to `info` and `never`.
- Recoverable external timeouts default to `warning` and `threshold`.
- Persistent invalid AI responses and failed background work default to `error` and `threshold`.
- Database unavailability, credential invalidation, data-integrity risk, and core service outage default to `critical` and `immediate`.
- Severity determines logging level. Notification policy independently determines whether and when email is sent.

Exceptions must never send email directly. The global handler records safe context, publishes an exception-report event, and returns a formatted response. An asynchronous listener aggregates fingerprints in Redis and dispatches an email job according to policy. Apply thresholds, cooldowns, and deduplication to prevent alert storms. A failure in email delivery must never recursively trigger another email alert.

Exception fingerprints should include stable code, environment, and exception class. Notification content may include request ID, safe route/event name, redacted identity, occurrence count, application revision, and a concise stack trace. Never include credentials, cookies, access/refresh tokens, email verification codes, full personal data, unrestricted conversation history, or complete turtle soup answers.

Production may enable email notifications. Development and test environments log only by default. SMTP credentials, recipients, thresholds, and cooldowns come from environment or configuration and are never committed.

## Public user API

The standard user API success envelope is:

```json
{
  "code": "success",
  "message": "success",
  "data": {},
  "request_id": "...",
  "timestamp": 1780000000
}
```

Errors use the stable English enum value in `code`. Paginated results contain `items` and a `pagination` object. Formats must keep envelopes consistent. Existing SaiAdmin management endpoints continue to follow SaiAdmin's current conventions.

State-changing APIs and WebSocket commands use idempotent request IDs. The server is authoritative for identities, permissions, room membership, game status, discovered reasoning points, scores, and completion.

## Identity and authentication

- Anonymous sessions receive restricted, expiring credentials and may only use permitted single-player features.
- Registered sessions use short-lived access tokens and revocable refresh tokens.
- Store password hashes using a current password-hashing API; never encrypt or log raw passwords.
- Username/password and email/password authenticate the same user/password record.
- Email codes are single-use, expiring, rate-limited, attempt-limited, and stored as hashes rather than plaintext.
- Registration or account upgrade can safely bind eligible anonymous history to the registered user.
- Design identity records so future WeChat Mini Program and Official Account identities can bind without adding platform fields directly to the users table.
- Multiplayer authorization is enforced by the backend on every protected operation, not only at login or in the client.

## Persistence and infrastructure

- MySQL is the durable source of truth.
- Do not connect to, create, drop, migrate, seed, truncate, or otherwise mutate any database unless the user has explicitly authorized that database operation in the current task.
- All schema changes must be represented by reviewed migration files. Never apply ad-hoc DDL directly to a database.
- Creating or editing migration files does not authorize running them. Generate and inspect migrations first, then wait for explicit permission before execution.
- Prefer read-only inspection when diagnosing database issues, and still confirm that the intended environment and connection are safe before accessing a non-local database.
- Use unsigned BIGINT primary keys internally and separate ULIDs for externally exposed identifiers unless an existing SaiAdmin table requires its convention.
- Do not expose predictable internal IDs through public user APIs when a public identifier exists.
- Redis may hold connection mappings, ephemeral room state, locks, idempotency records, rate limits, queues, and exception aggregation, but final game outcomes must be persisted to MySQL.
- Email, AI post-processing, reports, and other noninteractive work run through Redis-backed background jobs where appropriate.

## Environment configuration

- Environment configuration is a required part of every feature that introduces deploy-time settings.
- Keep a complete, safe `.env.example` with every required variable, purpose, and non-secret development default where appropriate.
- Never place real secrets or production values in `.env.example`, documentation, tests, logs, or committed source.
- Access environment values through configuration files or typed configuration objects; do not scatter direct environment reads throughout Business code.
- Validate required configuration during application startup and fail clearly for missing critical values.
- Separate development, test, staging, and production behavior explicitly. Email exception alerts are disabled outside production by default.
- Database, Redis, mail, public URL, token signing, Coze, storage, WebSocket, and future WebRTC settings must all be represented in the environment template when introduced.

## Game and AI rules

- AI interprets and proposes; PHP owns final state transitions and authorization.
- AI may return candidate point IDs and confidence, but Business validates existence, thresholds, duplication, and progress before persistence.
- Do not send complete conversation history to AI by default; send the surface, protected answer, points, discovered points, compact context, recent relevant messages, and language settings.
- The language tutor is independent from the turtle judge. Tutor failure must never prevent the host answer.
- Keep `interface_locale`, `content_locale`, and `learning_locale` separate.
- Text and future voice input converge on the same Game Business use cases.

## Coze workflows

- Keep workflows separate by responsibility: question judge, final-guess judge, hint generator, content parser, language tutor, and game review.
- Version workflow names, such as `turtle_question_judge_v1`. Introduce a new major version for incompatible input/output changes.
- Validate every Coze response against an internal schema before use.
- Log workflow ID, application workflow version, latency, attempt count, and safe result status. Do not log secrets or unrestricted answers.
- Store credentials and actual workflow IDs in environment/configuration, never source code.
- Frontends never call Coze directly.

## WebSocket conventions

- Authenticate after connection; do not put long-lived tokens in WebSocket URLs.
- Version event names, for example `v1.game.question`.
- Every command carries a request ID and receives a correlated result or error.
- Reconnection returns an authoritative server snapshot.
- Persist or order events as required before broadcasting them to a room.

## Security, observability, and quality

- Secrets, real `.env` files, production identifiers, user data, tokens, and full answers must not be committed.
- Redact sensitive input in logs and exception reports.
- Add structured request IDs across HTTP, WebSocket, jobs, repositories, and external calls.
- Document new user APIs in OpenAPI and keep WebSocket event schemas versioned.
- Test Business use cases, anonymous/multiplayer boundaries, authorization, state transitions, idempotency, WebSocket events, Coze parsing/fallbacks, and answer-leak prevention.
- Run syntax checks, static analysis, code style checks, and relevant tests before completing material changes.
