# turtle_content_parser_v1

Coze workflow contract for converting a source story into a reviewable turtle-soup draft.

The PHP adapter sends the fields documented in `input.schema.json` to Coze and expects
`output.schema.json`. The result is always validated server-side and can only be adopted as
a draft. It is never published automatically.

The importable package is generated from the target workspace compatibility template at:

`dist/Workflow-turtle_content_parser_v1-draft.zip`

Import it from the Coze resource library. The imported workflow is a draft; select an available
model if Coze reports that the exported model reference is unavailable, test it, and publish it.
Then place the published workflow ID and API token in the backend `.env` and set
`COZE_DRIVER=coze`.

生产和本地联调均通过 `COZE_API_TOKEN` 提供可轮换的服务令牌。令牌只保存在
`.env` 或部署平台的安全环境变量中，不得写入工作流包、数据库、日志或版本库。
后端会在超时、连接失败、限流和服务端错误时进行有限重试；鉴权失败和非法输出
不会重试。

内容解析工作流通常需要十几秒至一分钟完成，默认超时为 120 秒，并额外重试
1 次。可通过 `COZE_TIMEOUT_SECONDS`、`COZE_RETRY_TIMES` 和
`COZE_RETRY_DELAY_MS` 按部署环境调整。
