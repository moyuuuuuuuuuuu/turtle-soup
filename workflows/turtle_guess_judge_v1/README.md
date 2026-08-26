# turtle_guess_judge_v1

将 `dist/Workflow-turtle_guess_judge_v1-draft.zip` 直接导入扣子，选择可用模型后发布，
再把工作流 ID 写入 `COZE_GUESS_JUDGE_WORKFLOW_ID`。输入为冻结题目的 `surface`、
`bottom`、`language`、`key_points` 与 `player_guess`。输出是否解出、命中点 key、
结算摘要和安全说明。是否结束游戏最终由 PHP 决定。
