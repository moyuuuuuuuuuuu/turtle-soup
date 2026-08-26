# 玩家账号接入说明

玩家账号与 SaiAdmin 管理员账号完全隔离。玩家接口位于 `/api/v1`，使用 15 分钟 JWT Access Token 和 30 天不透明 Refresh Token；数据库仅保存 Refresh Token 摘要。

## 环境配置

复制 `.env.example` 中 `PLAYER_AUTH_*` 与 `MAIL_*` 配置到本地 `.env`。JWT、验证码 HMAC 密钥和 SMTP 密码必须使用独立随机值，不得提交。修改配置后重启 Webman 与 `player_email` Redis Queue 消费进程。

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

迁移 `20260826010004_create_player_accounts.php` 与 `20260826010005_add_player_management_menu.php` 已生成并完成静态审查，但尚未执行。运行前必须再次获得数据库迁移授权。
