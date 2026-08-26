# Turtle Soup UI Engineering Instructions

## Scope

This branch contains only the user-facing Turtle Soup client. It targets H5 first and must remain compatible with WeChat Mini Program and other future uni-app targets.

The product is a turtle soup puzzle game first. Language learning is optional assistance and must not interrupt or dominate the game experience.

## Technology

- Use uni-app, Vue 3, TypeScript, Vite, Pinia, Alova, and Wot UI.
- Use `<script setup lang="ts">` and Composition API for new Vue components.
- Keep TypeScript strict and do not introduce untyped API payloads when a stable type can be defined.
- Use Wot UI for standard interface elements before creating custom replacements.
- Use uni-app APIs instead of browser-only globals unless code is explicitly platform-gated.
- Verify material UI changes on both H5 and WeChat Mini Program builds.

## Branch boundaries

- `ui` contains this user client and its TypeScript API/WebSocket clients.
- `system-manage-ui` contains only the SaiAdmin management client.
- `system-manage` contains the Webman backend, user API, management API, WebSocket server, and external-service integrations.
- Do not copy backend business rules into this branch.

## Product and identity rules

- Anonymous users may browse public questions and play single-player games.
- Anonymous users must not create or join multiplayer rooms.
- Registered users may use multiplayer features.
- Supported initial sign-in methods are username/password, email/password, and email verification code.
- Preserve an anonymous user's current session when prompting for sign-in, and support migration of eligible anonymous data after registration.
- Treat `interface_locale`, `content_locale`, and `learning_locale` as separate settings.

## API conventions

- User HTTP APIs are provided by the `system-manage` branch under `/api/v1/*`.
- Keep user API request modules under `src/api` and shared payload types under the project's type directory.
- Never expose or request the turtle soup answer before the game is finished.
- Treat the backend as authoritative for identity, permissions, game state, discovered points, scoring, and completion.
- Handle stable English error codes such as `auth.login_required`, `room.full`, and `ai.workflow_timeout` rather than matching localized messages.
- Preserve and send an idempotent `request_id` for state-changing requests when the API supports it.
- Do not put access tokens in WebSocket URLs. Authenticate after connection using the agreed authentication event.

## Realtime conventions

- HTTP handles resource loading and ordinary CRUD; WebSocket handles live game events and AI processing updates.
- WebSocket event names are versioned, for example `v1.game.question`.
- Reconnect by requesting a server snapshot; do not reconstruct authoritative state only from local messages.
- Text and future voice input must converge on the same game flow. Treat voice transcription as another input source, not another game implementation.

## UI and language-learning behavior

- Keep the core loop fast: read the surface, ask, receive a short judgement, request hints, and submit a final guess.
- Language corrections are secondary, optional, concise, and must never block an AI host answer.
- Store user-facing text in locale resources rather than hard-coding it in components.
- Design layouts mobile-first and account for safe areas, virtual keyboards, and long streaming conversations.
- Avoid showing fake AI stages. Only render processing states emitted by the backend.

## Security and privacy

- Do not store passwords, refresh tokens, AI credentials, complete answers, or other secrets in client code or logs.
- Avoid logging full user conversations by default.
- Do not commit real environment files, credentials, production identifiers, or private user data.
- Client-side checks improve UX but never replace server-side authorization.

## Quality

- Keep pages thin; extract reusable state and behavior into stores, composables, and API modules.
- Avoid large catch-all stores and utility files.
- Add tests for authentication state, anonymous restrictions, API error handling, and realtime reconnection as those features are implemented.
- Run lint, type checking, and relevant platform builds before completing material changes.
- Preserve upstream Wot Starter licensing and avoid unrelated bulk rewrites of upstream files.
