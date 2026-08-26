# Turtle Soup System Management Backend

Webman backend for the Turtle Soup project, initialized from the `server` package in SaiAdmin 6.1.1.

This branch owns:

- Existing SaiAdmin management APIs
- Public user APIs under `/api/v1/*`
- Anonymous and registered-user authentication
- Single-player and multiplayer game business logic
- WebSocket communication
- Coze workflow integrations
- Persistence, queues, and operational notifications

## Local development

The repository is mounted into the existing DNMP PHP container at `/www/hgt`.
The current development runtime is PHP 8.2, MySQL 8.0, and Redis 7.2.

Create a local environment file and fill in only local credentials:

```bash
cp .env.example .env
docker exec -w /www/hgt php82 composer install
docker exec -w /www/hgt php82 php start.php start
```

The API listens on port `8787` inside the PHP container. Expose or proxy that port through
DNMP when host access is required. The health endpoint is `GET /api/v1/health` and does not
query MySQL or Redis.

Run the baseline checks with:

```bash
docker exec -w /www/hgt php82 composer check
```

Redis queue consumers are disabled by default. Enable them with
`REDIS_QUEUE_ENABLED=true` only after Redis configuration is ready.

## Database safety

Schema changes belong in `database/migrations`; optional development data belongs in
`database/seeds`. Creating either kind of file does not authorize execution. Do not run
Phinx, SaiAdmin installation, migration, seed, or database inspection commands until the
user explicitly authorizes that exact operation.

SaiAdmin 6.1.1 is configured to use its Eloquent implementation. New project modules also
use Eloquent and keep persistence behind repositories.

## Related branches

- `ui`: user-facing uni-app/Wot UI client
- `system-manage-ui`: SaiAdmin management client
- `system-manage`: backend (this branch)

SaiAdmin and Webman are licensed under their respective MIT licenses. Preserve upstream notices.
