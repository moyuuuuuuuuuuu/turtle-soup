# 匿名单人游戏 API v1

所有 HTTP 接口位于 `/api/v1`。除匿名会话签发和公开题库外，请使用
`Authorization: Bearer <anonymous-token>`；令牌不得放在 URL 中。

## HTTP

| 方法 | 路径 | 用途 |
| --- | --- | --- |
| POST | `/anonymous/session` | 按设备标识签发匿名令牌 |
| POST | `/anonymous/session/renew` | 续期当前匿名令牌 |
| GET | `/questions` | 已发布且公开的题目列表 |
| GET | `/questions/read` | 公开题目详情，不含汤底与推理点 |
| GET | `/questions/random` | 按难度、标签和语言随机选题 |
| POST | `/games` | 创建题目快照并开局 |
| GET | `/games/read` | 当前权威快照 |
| GET | `/games/history` | 匿名设备历史 |
| POST | `/games/ask` | HTTP 兼容提问入口 |
| POST | `/games/hint` | 使用固定提示 |
| POST | `/games/guess` | 提交唯一最终猜测 |
| POST | `/games/abandon` | 放弃并结算 |

状态为 `created / playing / solved / finished / abandoned`。`bottom` 与完整
`points` 仅在后三种结算状态返回。`caution` 题目创建时必须传
`risk_confirmed=true`；`restricted` 不进入公开查询。

## WebSocket

默认监听 `GAME_WS_LISTEN`（开发环境 `ws://hgt.test:8790`）。每个消息结构：

```json
{"event":"v1.game.join","request_id":"client-unique-id","data":{"game_id":"..."}}
```

客户端事件：`v1.auth`、`v1.ping`、`v1.game.join`、`v1.game.question`、
`v1.game.hint`、`v1.game.guess`。服务端事件：`v1.authenticated`、`v1.pong`、
`v1.game.snapshot`、`v1.game.answer`、`v1.game.solved`、`v1.game.finished`、
`v1.game.error`。

断线重连后重新认证并发送 `v1.game.join`，服务端返回包含完整有序消息的权威
快照。状态变更命令必须复用原 `request_id` 重试；AI 超时或非法结果不会写入
消息、扣减次数或改变状态。

每次问题或最终猜测调用均写入脱敏的 AI 请求审计，只记录工作流名称、状态、
尝试次数、耗时、错误码和判定摘要。审计不得保存玩家原文、汤底、模型完整回复。

## 扣子判定器

开发环境使用 `COZE_GAME_DRIVER=mock`。真实环境设置为 `coze`，并配置
`COZE_QUESTION_JUDGE_WORKFLOW_ID` 与 `COZE_GUESS_JUDGE_WORKFLOW_ID`。服务端只
接受问题判定 `yes / no / irrelevant / partial`，最终猜测必须返回布尔
`is_solved`；其余结果统一拒绝为 `ai.invalid_response`。
