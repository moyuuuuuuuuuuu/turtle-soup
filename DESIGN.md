# 墨鱼海龟汤 · 深海主题 UI 设计规范

> 来源：用户提供效果图（素材包 v1.0 + 设计总览）。无 Figma 源文件，色值/字号从压缩图提取，落地以本文 token 为准；与源文件冲突时以你方书面确认为准。
> 双品牌：**App/小程序 = 深青神秘风「墨鱼海龟汤」**；**分享海报/营销 = Moyuu 金色奇幻风**（不进主 UI）。

---

## 1. 色板 Design Tokens

### 暗色（默认 / 主题）

| Token | Hex | 用途 |
|---|---|---|
| `--hgt-brand` | `#5BC8BD` | 品牌主色、主按钮、链接、高亮 |
| `--hgt-brand-deep` | `#3A9A92` | 主按钮 hover/按下、品牌描边 |
| `--hgt-bg` | `#0C2027` | 页面主背景 |
| `--hgt-bg-deep` | `#071418` | 顶栏/底栏/更深层背景 |
| `--hgt-card` | `#0F2A2D` | 卡片、面板背景 |
| `--hgt-card-2` | `#16383C` | 次级卡片、输入框、hover 面 |
| `--hgt-paper` | `#D0DCB6` | 羊皮纸/汤面底（偏绿米） |
| `--hgt-paper-ink` | `#2A2A24` | 纸上正文 |
| `--hgt-text` | `#E5E8E3` | 主文字 |
| `--hgt-text-2` | `#999D9B` | 次要文字、标签 |
| `--hgt-text-3` | `#6B7574` | 弱化、占位 |
| `--hgt-border` | `#1E3A3A` | 暗色描边 |
| `--hgt-border-soft` | `#2E5155` | 柔和描边、分割 |
| `--hgt-success` | `#5E8787` | 成功/是（低饱和青绿） |
| `--hgt-success-text` | `#7DCCCC` | 成功文字 |
| `--hgt-warning` | `#C49A55` | 警告/中等难度 |
| `--hgt-danger` | `#C94A55` | 危险/困难/删除 |
| `--hgt-info` | `#3882F6` | 信息提示 |
| `--hgt-overlay` | `rgba(4, 12, 14, 0.72)` | 遮罩 |

### 浅色

| Token | Hex | 用途 |
|---|---|---|
| `--hgt-bg` | `#F4F6F3` | 页面底 |
| `--hgt-card` | `#FFFFFF` | 卡片 |
| `--hgt-card-2` | `#EEF2EF` | 次级面 |
| `--hgt-paper` | `#E8E4D4` | 纸质（更浅） |
| `--hgt-text` | `#1A2B2C` | 主文字 |
| `--hgt-text-2` | `#5C6B6A` | 次要 |
| `--hgt-border` | `#D5DEDC` | 描边 |
| `--hgt-border-soft` | `#E5E7EB` | 浅色柔边 |
| `--hgt-brand` | `#2E9A90` | 略加深保证对比 |

### 色板使用约束

- 主 CTA 仅用 `--hgt-brand` 实底 + 深色字或白字（对比不足时改 `#062A28` 字）
- 纸质区只用于汤面 / 汤底 / 最终猜测输入
- 难度色：简单 `success` · 中等 `warning` · 困难 `danger`
- 风险：safe 中性 · caution `warning` · restricted `danger`

---

## 2. 字体 Typography

| 角色 | 字体栈 | 字号/字重/行高 |
|---|---|---|
| 中文标题 | `"Source Han Serif SC", "Noto Serif SC", "Songti SC", serif` | 32 / 28 / 24 · 600 · 1.25 |
| 中文正文 | `"Source Han Sans SC", "Noto Sans SC", "PingFang SC", "Microsoft YaHei", sans-serif` | 16 / 14 / 12 · 400 · 1.6 |
| 英文标题 | `"Fraunces", "Playfair Display", Georgia, serif` | 24 / 20 · 600 · 1.2 |
| 英文正文 | `"Inter", "Roboto", system-ui, sans-serif` | 16 / 14 / 12 · 400 · 1.5 |
| 数据/等宽 | `"JetBrains Mono", "SF Mono", Consolas, monospace` | 12 / 11 · 400 |

阶梯：

```
display  32–40px  600
h1       28px     600
h2       24px     600
h3       20px     600
body     16px     400
body-sm  14px     400
caption  12px     400
label    10–12px  400  letter-spacing 0.06–0.12em
```

落地：优先本地/系统栈；H5 可异步加载 Noto Serif/Sans SC 子集。小程序默认系统字体，避免大包。

---

## 3. 圆角 · 阴影 · 边框

| 用途 | 值 |
|---|---|
| 小（chip/输入） | `4px` |
| 基础（按钮） | `8px` |
| 卡片 | `12px` |
| 大卡/弹层 | `16px` |
| 胶囊/头像圈 | `24px` / `999px` |

| Shadow | Value |
|---|---|
| sm | `0 2px 8px rgba(0,0,0,0.08)` |
| md | `0 4px 16px rgba(0,0,0,0.12)` |
| lg | `0 8px 32px rgba(0,0,0,0.18)` |
| 浮层 | `0 16px 48px rgba(0,0,0,0.28)` |

边框：暗色 `1px solid #1E3A3A`；浅色 `1px solid #E5E7EB`。

---

## 4. 动效

| 档位 | 时长 | 曲线 | 用途 |
|---|---|---|---|
| 快 | 150ms | `ease-out` | hover、chip、按下 |
| 标准 | 250ms | `ease-out` | 展开、显隐、页面元素 |
| 稍慢 | 350ms | `ease-in-out` | 侧栏、底部栏、路由 |
| 揭晓 | 500ms | `cubic-bezier(0.22, 1, 0.36, 1)` | 盖章、主题切换 |

`prefers-reduced-motion: reduce` 时全部降为 `opacity` 或直接无动画。

---

## 5. 断点与栅格

| 档 | 宽度 | 特征 |
|---|---|---|
| 手机 | `< 768` | 单栏；顶栏 56px；底 tab 64px + safe-area |
| 平板 | `768–1199` | 双栏/可收起侧栏；题库 2 列 |
| PC | `≥ 1200`（设计宽 1440） | 顶栏导航；题库 3–4 列；推理三栏 |

内容最大宽：PC `1280–1440`，内边距 `24–48px`。

---

## 6. 壳层 Shell

### PC 顶栏（≥768）

```
[Logo 墨鱼海龟汤]  首页 | 题库 | 推理 | 多人 | 排行榜     [搜索] [头像]
```

高度约 `64px`，背景 `--hgt-bg-deep`，底边 `--hgt-border`。

### 手机顶栏

```
[菜单]  [Logo 短标]  [搜索]
```

高度 `56px` + 状态栏；小程序避让胶囊。

### 手机底栏 Tab

首页 · 题库 · 推理 · 多人 · 我的（5 项）

选中：`--hgt-brand` + 顶部 2px 指示条。

---

## 7. 页面清单与对应

| # | 页面 | 路由 | 第一期 |
|---|---|---|---|
| 01 | 首页/谜题广场 | `pages/index` | 暗+浅 |
| 02 | 题库列表 | `pages/questions` | 暗+浅 |
| 03 | 谜题详情 | `pages/question-detail` | 暗+浅 |
| 04 | AI 推理（单人） | `pages/game` | 暗+浅 |
| 05 | 多人对局 | `pages/game` 多人态 / rooms | 暗+浅 |
| 06 | 最终猜测 | `pages/guess` | 暗+浅 |
| 07 | 揭晓汤底/结果 | game result | 暗+浅 |
| 08 | 我的推理/历史 | `pages/history` + account | 暗+浅 |
| — | 登录/注册/找回 | login/register/reset | 暗+浅 |
| — | 账号设置 | `pages/account` | 暗+浅 |
| — | 创建/加入房间 | `pages/rooms` `public-rooms` | 暗+浅 |
| — | 排行榜 | 待路由 | 暗+浅 |
| — | 法务/隐私/捐赠 | 已有 | 暗色对齐 |
| — | 空态/加载/错误/断线 | 组件 | 暗+浅 |
| — | 小程序分享卡/订阅 | 分享配置 | Moyuu 可选 |

---

## 8. 关键组件形态

### 主按钮 `btn-primary`
- 高 `44–48px`，圆角 `8px`，底 `--hgt-brand`，字 `#062A28` 或白（按对比）
- 文案如「开始推理 →」

### 次按钮 / 危险 / 进行中
- 次：透明底 + `--hgt-border` 描边
- 危险：`--hgt-danger` 文/描边或实底
- 进行中：`--hgt-card-2` + `--hgt-brand` 字

### 谜题卡片
- 封面 16:9，上标签（分类/难度）下标题 + 星级/时长/人数
- 无图：`cover_placeholder` 色块 + 章鱼空图

### 汤面纸卡
- 背景 `--hgt-paper` + `paper_0x` 纹理叠加
- 标签「汤面」，正文 `--hgt-paper-ink`，衬线标题

### AI 对话气泡
- AI：左对齐，`--hgt-card-2`，角色名「AI 主持人」
- 玩家：右对齐，`--hgt-brand` 淡底或描边
- 底部快捷：是 / 不是 / 不重要（`success` / 中性 / `warning` 描边）

### 线索板（PC 右栏 / 手机抽屉）
- Tab：线索 · 情绪 · 笔记
- 条目可勾选；「+ 添加线索」
- 推理进度 `n/limit` +「提交真相」

### 揭晓
- 纸质汤底 + 右上「真相已揭晓」斜章 `stamp_truth.png`
- 左侧推理表现：提问次数 / 命中关键点 / 用时 / 星级评价

---

## 9. 素材包文件名（按目录落地 `src/static/hgt/`）

```
brand/     logo-dark.png  logo-light.png  logo-mark.png
bg/        bg_deep_ocean.jpg  bg_underwater_cave.jpg  bg_lighthouse.jpg
           bg_texture_01.jpg  bg_texture_02.jpg  bg_texture_03.jpg
           bg_starry.jpg  bg_light.jpg
illust/    illust_rainy_night.jpg  illust_train.jpg  illust_ferris_wheel.jpg
           illust_classroom.jpg  illust_desk.jpg  illust_forest_house.jpg
           illust_shipwreck.jpg
paper/     paper_01.png  paper_02.png  paper_03.png  paper_04.png  paper_tag.png
prop/      prop_compass.png  prop_lantern.png  prop_key.png
           prop_letter.png  prop_photo.png  prop_bottle.png
empty/     empty_none.png  empty_loading.png  empty_network.png  empty_search.png
cover/     cover_placeholder.png  cover_example_0x.jpg
ui/        tape.png  water_splash.png  bubbles.png  vignette.png
           film_grain.png  stamp_truth.png
avatars/   avatar_default.png  …
```

无源文件时：用 `image_gen` 按同名/同气质生成，目标可商用、无水印、可压到 ≤200KB（封面 ≤2MB 规范按端调整）。

---

## 10. 交互细节（静态图补全）

1. AI 回答流式逐字/逐句出现；未完成禁用重复提交
2. 快捷「是/不是/不重要」与自由输入并存；快捷键直接发送
3. 最终猜测：textarea + 提交前 `HgtConfirmDialog` 二次确认
4. 线索板手机端底部抽屉，可拖高（沿用现有 chat-resize）
5. 下拉刷新：首页/题库/历史；上拉加载题库分页
6. 风险题开始前确认弹窗（已有逻辑，换皮）
7. 主题切换 500ms 圆形 reveal（保留现有 `HgtThemeTransition`）
8. 空态文案见素材包 09；错误可「重试」；断线「重新连接」

---

## 11. 保真度说明

| 项 | 水平 |
|---|---|
| 布局、信息架构、组件形态 | 严格对齐效果图 |
| 色板、字号、圆角、间距 | 按本文 token 严格实现 |
| 图片素材 | 按目录气质生成/替换，非源文件像素拷贝 |
| 字体 | 开源/系统栈近似思源，非商业字体二进制 |
| 动效时长曲线 | 按第 4 节 |

**不是** Figma 导出级像素 1:1；验收时以「与效果图并排对照无结构性偏差」为准。

---

## 12. 实施顺序

1. token → `App.vue` / `HgtShell` / 全局
2. 素材生成与压缩
3. 01 首页 → 02 题库 → 03 详情 → 04 单人推理 → 06 最终猜测 → 07 揭晓 → 05 多人 → 08 历史/我的
4. 登录注册、空态、浅色统一刷
5. 小程序：胶囊、安全区、分包、分享
6. lint / type-check / H5 构建 / 微信构建

---

## 13. 字体与许可备忘

- 思源宋体 / 思源黑体：SIL OFL，可嵌入产品
- Fraunces / Playfair / Inter：OFL，可选用
- 禁止：未授权商业中文字体二进制打包进仓库
