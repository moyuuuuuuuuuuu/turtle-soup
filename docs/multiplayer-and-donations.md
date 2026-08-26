# Multiplayer rooms and donations

## Player room API

All room endpoints require a player access token. Anonymous sessions are rejected.

- `GET /api/v1/rooms`: public waiting rooms.
- `GET /api/v1/rooms/read?id=...`: authoritative room snapshot for a member.
- `GET /api/v1/rooms/mine`: rooms belonging to the current player.
- `POST /api/v1/rooms`: create a room and its frozen game snapshot.
- `POST /api/v1/rooms/join`: join with a room ID or invite code.
- `POST /api/v1/rooms/ready`: update readiness.
- `POST /api/v1/rooms/start`: owner starts after every active member is ready.
- `POST /api/v1/rooms/leave`: leave a waiting room.
- `POST /api/v1/rooms/close`: owner closes a room.

The WebSocket adds `v1.room.join`, `v1.room.chat`, `v1.room.ready`,
`v1.room.start`, `v1.room.typing.start`, and `v1.room.typing.stop`. Typing state
is ephemeral, expires client-side after four seconds, and is never persisted.
Game answers remain ordered and persisted through the existing game message
sequence. A room snapshot is authoritative after reconnect.

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
