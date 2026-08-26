# Turtle Soup Management UI Engineering Instructions

## Scope

This branch contains only the SaiAdmin management frontend derived from `saiadmin-artd` in SaiAdmin 6.1.1.

It manages turtle soup content, games, users, language-learning data, AI workflow configuration, and operational records. It does not contain the user-facing client or PHP backend.

## Branch boundaries

- `ui` contains the user-facing uni-app/Wot UI client.
- `system-manage-ui` contains this Vue management client and its TypeScript API modules.
- `system-manage` contains the Webman backend, management API, user API, WebSocket server, and external integrations.
- Do not add PHP code or user-facing uni-app pages to this branch.

## Upstream policy

- This branch is initialized from `saiadmin-artd` in SaiAdmin tag `6.1.1`.
- Preserve the SaiAdmin MIT license and upstream copyright notices.
- Prefer extending existing SaiAdmin conventions over replacing framework facilities.
- Keep project-specific code clearly separated from upstream code where practical.
- Do not perform unrelated bulk formatting or structural rewrites of upstream files.
- Evaluate upstream upgrades explicitly; never silently track upstream `main`.

## Frontend conventions

- Use Vue 3, TypeScript, Element Plus, Pinia, Vue Router, and the existing Art Design Pro/SaiAdmin patterns.
- Use `<script setup lang="ts">` and Composition API for new components.
- Keep TypeScript types for API inputs, outputs, filters, tables, and forms.
- Keep management API modules under `src/api`, grouped by business domain.
- Reuse existing table, form, permission, dictionary, and layout facilities before adding parallel abstractions.
- Put user-facing management text in the existing locale system.

## API behavior

- The management API continues to use SaiAdmin's existing route, response, authentication, and permission conventions.
- Do not force the user API `/api/v1/*` response shape onto existing SaiAdmin endpoints.
- Stable backend error codes may be used for programmatic handling; never branch on localized error messages.
- Keep API adaptation in API/client utilities rather than scattering transport-specific logic through views.

## Management modules

Expected project modules include:

- Question bank, translations, reasoning points, hints, tags, review, publish, and unpublish.
- Game sessions, room records, messages, progress, and AI request traces.
- Users, anonymous sessions, identities, and language profiles.
- Coze workflow identifiers, versions, prompts, execution logs, and cost/latency metrics.
- Exception reports and notification history, without exposing secrets or full turtle soup answers unnecessarily.

## Security

- Enforce permissions in the backend; frontend permission directives are presentation helpers only.
- Do not log or display access tokens, refresh tokens, passwords, email codes, Coze credentials, or unredacted personal information.
- Display the complete turtle soup answer only to authorized management users and only where operationally necessary.
- Never commit real `.env` files, credentials, production identifiers, or user data.

## Quality

- Keep views focused on presentation and orchestration; move reusable behavior into composables, stores, components, and API modules.
- Avoid catch-all components, stores, and utility files.
- Add focused tests for permissions, form transformations, status-code handling, and critical management workflows as they are introduced.
- Run the existing lint, type-check/build, and relevant tests before completing material changes.
