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

## Requirements

- PHP 8.1 or later
- Composer
- MySQL
- Redis

## Installation

```bash
composer install
php start.php start
```

Follow SaiAdmin's installation process for its management plugin and configure Eloquent for project modules.

## Related branches

- `ui`: user-facing uni-app/Wot UI client
- `system-manage-ui`: SaiAdmin management client
- `system-manage`: backend (this branch)

SaiAdmin and Webman are licensed under their respective MIT licenses. Preserve upstream notices.
