# Multiplayer rooms and donations

## Player room API

All room endpoints require a player access token. Anonymous sessions are rejected.

- `GET /api/v1/rooms`: public joinable rooms, including games already in progress.
- `GET /api/v1/rooms/read?id=...`: authoritative room snapshot for a member.
- `GET /api/v1/rooms/mine`: rooms belonging to the current player.
- `POST /api/v1/rooms`: upgrade the authenticated player's active single-player game (`game_id`) into a room without replacing its question, progress, or messages.
- `POST /api/v1/rooms/join`: join with a room ID or invite code; joining automatically leaves any other active room.
- `POST /api/v1/rooms/ready`: compatibility endpoint; room members are ready immediately after joining.
- `POST /api/v1/rooms/start`: compatibility endpoint; rooms begin playing immediately after creation or joining.
- `POST /api/v1/rooms/leave`: leave the current room; ownership transfers to a random active member and the room closes after the final member leaves.
- `POST /api/v1/rooms/close`: owner closes a room.

The WebSocket adds `v1.room.join`, `v1.room.chat`, `v1.room.ready`,
`v1.room.start`, `v1.room.typing.start`, and `v1.room.typing.stop`. Typing state
is ephemeral, expires client-side after four seconds, and is never persisted.
Game answers remain ordered and persisted through the existing game message
sequence. A room snapshot is authoritative after reconnect.

The WebSocket grants a 45-second reconnect grace period. Heartbeats refresh the
member activity timestamp. A dedicated cleanup process closes rooms with no
active member heartbeat for `ROOM_IDLE_TIMEOUT_SECONDS` (30 minutes by default)
and marks unfinished room games as abandoned. The scan interval is controlled by
`ROOM_CLEANUP_INTERVAL_SECONDS` (60 seconds by default).

The current local deployment uses one WebSocket worker. Before increasing that
worker count, room broadcasting must move from the in-process connection map to
Webman Channel or Redis fan-out.

## Donation API

`GET /api/v1/donations` returns enabled payment channels and the latest enabled
manual donation records. It does not accept payment amounts or create payment
orders.

SaiAdmin provides separate room and donation menus. Donation administrators can
upload one personal collection QR code for WeChat and Alipay. Images are stored
in BOS under a content-hashed object key. Recent donations are entered manually
and can be hidden without removing the public payment channel.

No payment claim made by the user interface is treated as a verified financial
transaction; the “completed payment” action is only a local acknowledgement.
