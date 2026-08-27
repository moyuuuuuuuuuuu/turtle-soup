# 海龟汤生产发布、回滚与恢复手册

本文覆盖用户端、SaiAdmin 管理端和 Webman 后端。命令中的路径、容器名和域名必须按生产环境替换；任何数据库迁移均须先备份并由发布负责人明确批准。

## 1. 发布前闸门

后端：

```bash
composer install --no-dev --classmap-authoritative --no-interaction
composer validate:release
composer audit --no-interaction
composer check
composer config:check -- /path/to/production.env
```

`validate:release` 保留严格校验，但忽略依赖版本范围建议；本项目按上游策略精确固定
SaiAdmin `6.1.1`，该固定不是待自动放宽的告警。
`config:check` 只输出违规项，不输出任何配置值；必须对准备部署的生产配置文件执行，
不能用仓库中的本地 `.env.example` 代替。

用户端与管理端：

```bash
pnpm install --frozen-lockfile
pnpm lint
pnpm type-check
pnpm build:h5:production
pnpm build:mp-weixin

# system-manage-ui
pnpm lint
pnpm build
```

生产 `.env` 必须满足启动校验：`APP_DEBUG=false`、HTTPS `APP_URL`、明确的 CORS 域名、独立且不少于 32 字符的四个签名密钥、真实 Coze 驱动和工作流、Redis Queue 已启用。真实密钥不得写入仓库、构建产物或日志。

## 2. 数据库备份与迁移

发布前生成带时间戳的 MySQL 一致性备份，并在隔离环境实际恢复一次。记录备份文件校验和、数据库版本、迁移前版本和负责人。

```bash
mysqldump --single-transaction --routines --triggers turtle_soup > turtle_soup-before-release.sql
sha256sum turtle_soup-before-release.sql
php vendor/bin/phinx status -c phinx.php
php vendor/bin/phinx migrate -c phinx.php
php vendor/bin/phinx status -c phinx.php
```

禁止对生产库运行 Seeder。迁移失败时停止流量切换，不得继续启动新版本；优先恢复旧应用和备份。只有迁移文件明确支持且已演练时才执行 Phinx rollback。

## 3. 发布顺序

1. 上传静态构建产物到带版本号的目录，不覆盖当前目录。
2. 部署后端代码并执行只读检查，再执行已批准的迁移。
3. 重启 Webman，确认 HTTP、WebSocket、房间清理和队列进程全部存活。
4. 切换 Nginx 静态目录软链接或发布目录。
5. 依次验证健康检查、登录、题库、单人游戏、多人房间、管理端登录。

```bash
php start.php restart -d
php start.php status
curl --fail --show-error https://hgt.example.com/api/v1/health
```

Nginx 必须终止 TLS，并代理 `/api/` 与 `/game` WebSocket；保留客户端 IP 与 `X-Forwarded-Proto`。不要把 Webman、Redis、MySQL 或 Vite 开发服务器直接暴露到公网。

## 4. 冒烟验收

- 未登录用户只能进行单人推理，不能创建或加入房间。
- 两个测试账号可登录、加入同一房间并实时看到成员、聊天、输入状态和汤底揭晓。
- 未结束游戏的所有 HTTP 与 WebSocket 快照均不含汤底和推理点答案。
- 房主退出后转移房主，最后一个成员退出后房间关闭；超时房间由清理进程关闭。
- 管理端权限不足的账号无法读取汤底、玩家或房间管理接口。
- 响应含 `X-Request-Id`、`X-Content-Type-Options: nosniff`、`X-Frame-Options: DENY`；HTTPS 响应含 HSTS。
- 队列失败任务、异常日志、磁盘、数据库连接数和 WebSocket 断线率可观测。

## 5. 回滚

应用回滚优先切回上一版静态目录和上一版后端发布目录，然后重启 Webman。若新迁移只做兼容性加表/加列，可先保留数据库结构；涉及不兼容数据写入时，必须按迁移评审中的专项方案回滚或从备份恢复，禁止临时手写 DDL。

回滚后重复健康检查、登录、单人游戏和管理端冒烟，并记录请求 ID与日志证据。

## 6. 故障恢复

- **Redis/队列**：恢复 Redis 后重启消费者，检查积压和失败任务；邮箱、报告等异步任务必须保持幂等。
- **WebSocket**：当前部署只允许一个 `game.websocket` worker。扩容前必须实现跨进程广播；重连后以服务器快照覆盖客户端状态。
- **Coze**：超时或无效响应不得改变游戏完成状态，不得把原始第三方响应返回前端；恢复后用固定测试题验证问题裁判与最终猜测工作流。
- **数据库**：先隔离写流量，再按已验证备份恢复；核对迁移版本、用户/游戏/成员数量及最近完成记录。
- **密钥泄漏**：立即轮换相关密钥，撤销所有玩家会话，检查日志与仓库历史；不得只修改 `.env` 后继续使用旧令牌。

## 7. 当前扩容边界

房间实时广播目前依赖单 WebSocket worker。单机单 worker 可以上线；在完成 Redis/ChannelServer 跨进程广播和压测之前，不得增加 WebSocket worker 数或进行多节点水平扩容。
