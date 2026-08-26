# 玩家账号接入说明

玩家账号与 SaiAdmin 管理员账号完全隔离。玩家接口位于 `/api/v1`，使用 15 分钟 JWT Access Token 和 30 天不透明 Refresh Token；数据库仅保存 Refresh Token 摘要。

玩家只允许使用邮箱登录：邮箱 + 密码或邮箱 + 验证码。用户名仅作为公开显示名称，不再参与登录匹配；注册时可留空，系统会从邮箱前缀生成一个可修改的显示名称。

## 环境配置

复制 `.env.example` 中 `PLAYER_*`、`MAIL_*` 与 `SMTP_*` 配置到本地 `.env`。JWT、令牌摘要、验证码 HMAC 密钥和 SMTP 密码必须使用独立随机值，不得提交。发件地址使用 `SMTP_FROM_ADDRESS`（兼容旧的 `MAIL_FROM_ADDRESS`）；修改配置后重启 Webman 与 `player_email` Redis Queue 消费进程。

默认头像使用邮箱首字母生成 SVG，并由后端上传到百度 BOS。必须配置 `BOS_ACCESS_KEY`、`BOS_SECRET_KEY`、`BOS_ENDPOINT`、`BOS_BUCKET` 和公开访问基址 `BOS_PUBLIC_BASE_URL`。对象键为 `avatars/default/{sha256(玩家 ID 前两位/玩家公开 ID)}.svg`，不包含邮箱，也不直接暴露玩家 ID。

## 账号与匿名合并

注册、密码登录和邮箱验证码登录均可携带 `X-Anonymous-Token`。认证成功后，服务端在事务中把该匿名会话的游戏归属转移至玩家账号、记录合并审计并撤销匿名令牌。重复合并不会复制游戏、消息、提示、猜测或 AI 审计记录。

## 会话安全

- 同一玩家最多保留三个有效设备会话，第四台设备不会自动挤掉旧设备。
- Refresh Token 每次使用后轮换；复用旧令牌会撤销整个令牌族。
- 修改或找回密码会撤销全部旧会话并为当前设备重新签发会话。
- 换绑邮箱需要当前密码和新邮箱验证码，成功后撤销其他设备并异步通知旧邮箱。
- 玩家被后台禁用后，HTTP、刷新与 WebSocket 鉴权均拒绝继续使用。

## 微信预留边界

`turtle_user_identities` 仅预留 `wechat_mini_program` 和 `wechat_official_account` 身份数据、UnionID 与扩展字段。本阶段没有微信登录路由、Service 骨架或外部授权调用。

## 迁移状态

迁移 `20260826010004_create_player_accounts.php` 与 `20260826010005_add_player_management_menu.php` 已于 2026-08-27 获授权后在本地 `turtle_soup` 数据库执行完成。

## 本地真实链路验收

2026-08-27 已完成邮箱验证码注册、邮箱密码/验证码登录、匿名游戏合并、BOS 默认头像、三设备限制、刷新令牌轮换与复用检测、指定会话撤销、全部退出、换绑邮箱、修改/找回密码及玩家 WebSocket 鉴权。测试账号保留；其最终密码为本地临时随机值，使用者应通过“忘记密码”页面设置自己的密码。
