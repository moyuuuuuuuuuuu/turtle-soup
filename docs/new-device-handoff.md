# 新设备开发接续指南

更新日期：2026-08-27 18:55（Asia/Shanghai）

## 0. 本次换机交接快照

本节是当前工作树的最新事实，优先级高于下方较早的阶段说明。三个长期分支会在本次
交接完成后分别提交并推送，换机后应先执行 `git fetch --all --prune`，再确认本节记录
的远端提交。

### 本次远端提交

- 后端 `system-manage`：`336e45a`（多人业务、生产加固、测试与发布手册）。
- 用户端 `ui`：`7a54976`（多人游戏闭环、页面与移动端体验）。
- 管理端 `system-manage-ui`：`5944797`（构建依赖、CI 与组件兼容修复）。
- 本文档提交位于上述后端提交之后；换机时以远端 `system-manage` 最新提交为准。

### 已完成

- 用户端已按当前 Figma 视觉语言重做登录、注册、题库、题目详情、游戏、历史、个人
  中心、捐赠、房间及公共房间，并补齐桌面端和移动端适配、粒子背景和主题切换。
- `/pages/room/index` 和独立结算页已经移除；创建、加入和多人游戏统一在
  `/pages/game/index` 完成，结束结果由弹层及历史记录承载。
- 多人房间已实现自动开局、邀请码/分享链接加入、单用户仅在一个房间、房主退出自动
  转让、最后一人退出解散、队伍聊天、输入状态、未读提示、禁言、踢人、退出房间、
  汤底同步揭晓和队伍共享结果。
- `20260827030000_add_bio_to_turtle_users` 与
  `20260827040000_create_game_players` 已在本机开发库执行；换新数据库时仍须按迁移顺序
  正常执行，禁止复制本机迁移状态作为生产依据。
- 增加空闲房间清理进程、首页真实统计、统一 API 限流、安全响应头、生产配置启动校验、
  CI 依赖审计以及生产发布/备份/回滚手册。
- 用户端生产依赖安全审计已清零；后端 `composer audit` 和管理端生产依赖审计均无已知漏洞。

### 当前验证证据

- 后端：`composer check` 通过，54 个测试、228 个断言，PHPStan 0 错误，PHP CS Fixer 通过。
- 用户端：`pnpm lint`、`pnpm type-check`、`pnpm build:h5`、
  `pnpm build:mp-weixin`、`pnpm audit --prod` 全部通过。
- 管理端：`pnpm lint`、`pnpm build`、`pnpm audit --prod` 全部通过。
- 容器后端已重启，7 类 Worker、70 个进程运行正常；`/api/v1/health` 返回成功且包含安全响应头。
- 使用 `moyuuuuuuuu@outlook.com` 与 `moyuucat@gmail.com` 完成 API 双账号验收：登录、
  创建游戏、创建房间、邀请码加入、2 人房间快照及队员共享历史记录均通过。密码属于
  本地环境数据，不写入本文或 Git。

### 尚未达到“完全上线”的项目

1. 当前仅有 1 道已发布题目，仍需准备并人工审核至少 10 道正式题目（3 简单、4 中等、3 困难）。
2. 仍需补齐 Coze 工作流 JSON Schema、异常输出、提示注入、矛盾回答和汤底泄漏自动化测试。
3. 仍需用两个真实浏览器会话完成 WebSocket 端到端验收：聊天、输入状态、未读、禁言、
   踢人、房主转让、断线重连、同步揭晓汤底及队伍下一题。
4. 当前只允许单个 WebSocket Worker。增加 Worker 或多节点部署前，必须完成 Channel/Redis
   跨进程广播和并发房间指令串行化。
5. 异常事件聚合、告警邮件、指标和生产监控尚未完成。
6. 正式发布前必须配置 HTTPS、生产域名、独立生产密钥、备份位置、告警收件人并执行一次
   备份恢复及回滚演练。详细步骤见 `docs/production-release-runbook.md`。

### 换机后第一组命令

```bash
git -C /你的路径/dnmp/www/hgt status -sb
git -C /你的路径/hgt-worktrees/ui status -sb
git -C /你的路径/hgt-worktrees/system-manage-ui status -sb

docker exec -w /www/hgt php82 composer check
docker exec -w /www/hgt php82 php webman start -d

cd /你的路径/hgt-worktrees/ui
pnpm install --frozen-lockfile
pnpm lint && pnpm type-check && pnpm build:h5 && pnpm build:mp-weixin

cd /你的路径/hgt-worktrees/system-manage-ui
pnpm install --frozen-lockfile
pnpm lint && pnpm build
```

本项目使用同一个 GitHub 仓库的三个长期分支：

| 分支 | 内容 | 当前远端基线 |
| --- | --- | --- |
| `system-manage` | Webman/PHP 后端、公开 API、WebSocket、数据库迁移 | `81a64c1` |
| `ui` | uni-app + Vue 3 + TypeScript + Wot UI 用户端 | `a0e3627` |
| `system-manage-ui` | SaiAdmin 管理端 Vue 前端 | `566cab2` |

仓库地址：`git@github.com:moyuuuuuuuuuuu/turtle-soup.git`

> 上述三个提交已经推送到远端，包含多人房间、捐赠和新版 UI。新设备 clone 后仍需
> 按本文配置本地 `.env` 和数据库；这些敏感或设备相关数据不会进入 Git。

## 1. 当前开发阶段

已经完成并通过本地检查：

- SaiAdmin 题库、标签、版本历史、风险信息和 AI 创作。
- 扣子题目解析、问题判定和最终猜测判定的真实工作流接入。
- 匿名单人游戏闭环、三级提示、最终猜测、结算和历史记录。
- 玩家邮箱注册/登录、邮箱验证码登录、刷新令牌、三设备限制和匿名历史合并。
- SMTP 邮件发送和百度 BOS 默认头像。
- 多人房间数据库和核心业务：创建、邀请码加入、准备、房主开局、队伍聊天、
  输入状态、退出、房主转让、关闭、WebSocket 广播及重连快照。
- 捐赠模块：后台上传个人微信/支付宝收款码、手工维护最近捐赠；用户端不选择金额。
- 用户端已开始按最新 Figma/React 参考实现新版视觉，首页、题库、历史、题目详情、
  游戏、房间和捐赠已经迁移到新设计语言。
- H5 与微信小程序构建通过；PHPUnit、PHPStan 和 PHP CS Fixer 通过。

下一步优先级：

1. 使用至少两个真实玩家完成多人对局端到端验收。
2. 增加空闲房间超时关闭任务。
3. WebSocket 增加 Worker 前，将房间广播改为 Webman Channel/Redis 跨进程广播。
4. 继续精确还原登录、注册、找回密码和个人中心页面。
5. 在后台上传真实收款码并录入最近捐赠记录。
6. 完成后再进入英语辅助、学习记录和 WebRTC 语音阶段。

完整任务状态见根目录 `TODO.md`，多人和捐赠接口见
`docs/multiplayer-and-donations.md`。

## 2. 新设备准备

建议安装：

- Git 和可访问 GitHub 的 SSH Key。
- Docker Desktop。
- DNMP，容器名称保持为 `nginx`、`php82`、`mysql`、`redis`。
- Node.js 22、Corepack 和 pnpm 9.9。
- 可选：微信开发者工具，用于运行 `dist/build/mp-weixin`。

先验证 GitHub SSH：

```bash
ssh -T git@github.com
```

## 3. 克隆三个分支

推荐让后端直接位于 DNMP 的 `www/hgt`，其他两个分支使用 Git worktree：

```bash
cd /你的路径/dnmp/www
git clone --branch system-manage git@github.com:moyuuuuuuuuuuu/turtle-soup.git hgt
cd hgt

mkdir -p /你的路径/hgt-worktrees
git worktree add /你的路径/hgt-worktrees/ui ui
git worktree add /你的路径/hgt-worktrees/system-manage-ui system-manage-ui
```

如果希望保留旧设备的路径习惯，可增加符号链接：

```bash
ln -s /你的路径/dnmp/www/hgt /你的路径/hgt
```

确认分支：

```bash
git -C /你的路径/dnmp/www/hgt branch --show-current
git -C /你的路径/hgt-worktrees/ui branch --show-current
git -C /你的路径/hgt-worktrees/system-manage-ui branch --show-current
```

依次应输出 `system-manage`、`ui`、`system-manage-ui`。

## 4. 后端环境文件

```bash
cd /你的路径/dnmp/www/hgt
cp .env.example .env
```

将旧设备 `.env` 中的真实配置通过密码管理器或加密方式迁移。不要把 `.env`、令牌、
SMTP 授权码、BOS Key 或数据库密码提交到 Git。至少需要配置以下项目：

```dotenv
APP_ENV=local
APP_DEBUG=true
APP_URL=http://hgt.test
CORS_ALLOWED_ORIGINS=http://localhost:5173,http://localhost:3006

DB_HOST=mysql
DB_PORT=3306
DB_NAME=turtle_soup
DB_USER=turtle_soup
DB_PASSWORD=<本机数据库密码>

REDIS_HOST=redis
REDIS_PORT=6379
REDIS_QUEUE_ENABLED=true

COZE_DRIVER=coze
COZE_GAME_DRIVER=coze
COZE_API_TOKEN=<长期服务令牌>
COZE_WORKFLOW_ID=7678359297013743667
COZE_QUESTION_JUDGE_WORKFLOW_ID=7678365429597831220
COZE_GUESS_JUDGE_WORKFLOW_ID=7678364048027549705

PLAYER_JWT_SECRET=<256位随机密钥>
PLAYER_TOKEN_HASH_SECRET=<另一个256位随机密钥>
PLAYER_EMAIL_CODE_SECRET=<另一个256位随机密钥>
ANONYMOUS_TOKEN_SECRET=<独立随机密钥>

SMTP_HOST=<SMTP服务器>
SMTP_PORT=465
SMTP_USERNAME=<发件账号>
SMTP_PASSWORD=<SMTP授权码>
SMTP_ENCRYPTION=ssl
SMTP_FROM_ADDRESS=<发件邮箱>
SMTP_FROM_NAME=海龟汤

BOS_ACCESS_KEY=<百度BOS Access Key>
BOS_SECRET_KEY=<百度BOS Secret Key>
BOS_ENDPOINT=https://bj.bcebos.com
BOS_BUCKET=turtle-soup
BOS_PUBLIC_BASE_URL=https://turtle-soup.bj.bcebos.com

GAME_WS_LISTEN=websocket://0.0.0.0:8790
```

如果不迁移旧数据库，三个玩家密钥可以重新生成；如果需要保留旧玩家会话和匿名令牌，
必须保持这些密钥与旧设备一致。

## 5. DNMP 与域名

在新设备 hosts 文件增加：

```text
127.0.0.1 hgt.test
```

DNMP Nginx 增加 `services/nginx/conf.d/hgt.conf`：

```nginx
server {
    listen 80;
    server_name hgt.test;

    location /game {
        proxy_pass http://php82:8790;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host $host;
        proxy_read_timeout 60s;
    }

    location / {
        proxy_pass http://php82:8787;
    }
}
```

确保 DNMP 将 `/你的路径/dnmp/www` 挂载为 PHP 容器内的 `/www`，然后启动或重启
`nginx`、`php82`、`mysql`、`redis`。

## 6. 安装依赖

后端：

```bash
docker exec -w /www/hgt php82 composer install
```

用户端：

```bash
cd /你的路径/hgt-worktrees/ui
corepack enable
pnpm install --frozen-lockfile
```

管理端：

```bash
cd /你的路径/hgt-worktrees/system-manage-ui
pnpm install --frozen-lockfile
```

## 7. 恢复数据库

有两种方式，二选一。

### A. 精确保留旧设备数据

在旧设备导出 `turtle_soup`，通过加密渠道复制到新设备，然后导入新设备 MySQL。
导出文件可能包含玩家邮箱、会话摘要、游戏历史和管理数据，不得提交 Git 或上传公开网盘。

导入后只执行状态检查，不要重复运行 Seeder：

```bash
docker exec -w /www/hgt php82 php webman sai:migrate status
docker exec -w /www/hgt php82 php vendor/bin/phinx status -c phinx.php
```

### B. 创建全新的开发数据库

先创建空的 `turtle_soup` 数据库和 `.env` 中指定的账号，然后按顺序执行：

```bash
# SaiAdmin 基础表
docker exec -w /www/hgt php82 php webman sai:migrate

# 只导入纯净基础数据，禁止 DemoSeeder
docker exec -w /www/hgt php82 php webman sai:migrate seed --seed PureSeeder

# 项目题库、游戏、玩家、多人房间和捐赠迁移
docker exec -w /www/hgt php82 php vendor/bin/phinx migrate -c phinx.php
```

全新数据库没有旧设备上的题目、测试玩家、游戏历史、捐赠记录或收款码配置，需要在
后台重新创建或导入。

当前项目迁移的最新版本应为：

```text
20260827010000 CreateMultiplayerRoomsAndDonations
```

## 8. 启动项目

后端、WebSocket 和 Redis Queue：

```bash
docker exec -w /www/hgt php82 php webman start -d
docker exec -w /www/hgt php82 php webman status
```

用户端：

```bash
cd /你的路径/hgt-worktrees/ui
pnpm dev:h5
```

访问：`http://localhost:5173`

管理端：

```bash
cd /你的路径/hgt-worktrees/system-manage-ui
pnpm dev
```

访问：`http://localhost:3006`

后端 API：`http://hgt.test`
WebSocket：`ws://hgt.test/game`

用户端 `.env.development` 应包含：

```dotenv
VITE_API_BASE_URL=http://hgt.test/api/v1
VITE_WS_BASE_URL=ws://hgt.test/game
VITE_ENV_NAME=development
```

管理端 `.env.development` 应包含：

```dotenv
VITE_API_URL=/api
VITE_API_PROXY_URL=http://hgt.test
```

## 9. 接手前验证

```bash
# 后端
docker exec -w /www/hgt php82 composer check

# 用户端
cd /你的路径/hgt-worktrees/ui
pnpm lint
pnpm type-check
pnpm build:h5
pnpm build:mp-weixin

# 管理端
cd /你的路径/hgt-worktrees/system-manage-ui
pnpm build
```

再检查：

- `http://hgt.test/api/v1/donations` 返回 `code: success`。
- 管理端可以登录，并能看到题库、玩家、多人成房间和捐赠管理菜单。
- 用户端能读取公开题目、创建单人游戏。
- 两个注册玩家能加入同一房间，并看到对方的输入状态。
- 单人游戏页面不存在队伍成员和队伍讨论 DOM。

## 10. CodeGraph

三个工作目录分别初始化或同步：

```bash
codegraph init /你的路径/dnmp/www/hgt
codegraph init /你的路径/hgt-worktrees/ui
codegraph init /你的路径/hgt-worktrees/system-manage-ui
```

已有 `.codegraph` 时使用 `codegraph sync <目录>`。

## 11. 远端确认

在旧设备完成提交和推送后，记录三个最新提交：

```bash
git -C /你的路径/dnmp/www/hgt status -sb
git -C /你的路径/hgt-worktrees/ui status -sb
git -C /你的路径/hgt-worktrees/system-manage-ui status -sb

git ls-remote --heads git@github.com:moyuuuuuuuuuuu/turtle-soup.git \
  system-manage ui system-manage-ui
```

三个 `status -sb` 应没有未提交文件，`ls-remote` 返回的提交应等于旧设备本地各分支的
`git rev-parse HEAD`。如果不相等，不要在新设备继续开发，否则会遗漏旧设备的实现。

## 12. 安全与数据库约束

- 真实凭证只保存在本地 `.env`，不得提交。
- 数据表变更只通过 Phinx 迁移；禁止手写 DDL。
- 自动化工具未获得明确许可前不得执行迁移、Seeder、清库、回滚或直接修改数据库。
- 默认只使用 `PureSeeder`，禁止导入 `DemoSeeder`。
- PEM、Key、数据库转储、玩家 Token、验证码和收款码原图不得进入 Git。
