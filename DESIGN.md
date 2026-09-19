# MOYUU 海龟汤 · 水墨风 UI 设计规范

> 来源：用户效果图（水墨首页稿）。落地 token 以本文为准。
> 产品身份：**App/小程序/站点主 UI = 水墨文学风「MOYUU 海龟汤」**。品牌口号：「每一个故事，都是一个小小的世界。」

---

## 1. 风格锚点

- **锚点**：宣纸上的当代文学站——类似纸刊插画 + 书法标题，而非深海游戏 HUD。
- **气质**：雾、留白、衬线中文、朱砂印章。
- **默认主题**：浅色宣纸（light-first）。深色为「夜墨」可选，不抢主视觉。
- **产品优先**：海龟汤推理解谜第一；语言学习能力不打断主循环。

---

## 2. 色板 Design Tokens（图2 · 比例示意 65/25/10/5）

> 底色固定：**`#F8F8F7`**（用户指定）

| Token | Hex | 名称 | 用途 / 比例 |
|---|---|---|---|
| `--hgt-brand` | `#789262` | 竹青 | 主色 65%：CTA、选中、链接、「真相」强调 |
| `--hgt-brand-deep` | `#5F754C` | 竹青加深 | hover / 按下 |
| `--hgt-brand-soft` | `rgba(120,146,98,.12)` | 竹青淡底 | chip 选中、浅强调 |
| `--hgt-on-brand` | `#F8F8F7` | 底色 | 主按钮文字 |
| `--hgt-accent` | `#9E5356` | 明茶褐 | 点缀 25%：印章、困难、装饰线 |
| `--hgt-accent-soft` | `rgba(158,83,86,.12)` | 褐淡底 | |
| `--hgt-moon` | `#D6ECF0` | 月白 | 浅色 10%：封面占位、次级浅面 |
| `--hgt-ink-gray` | `#758A99` | 墨灰 | 深色辅助 5%：信息、弱强调 |
| `--hgt-gold` | `#F0C239` | 图2辅色样本 | 辅色：中等难度、点缀 |
| `--hgt-bg` | `#F8F8F7` | 页面底 | 全局底色 |
| `--hgt-bg-deep` | `#F1F0ED` | 顶栏/底栏 | |
| `--hgt-card` | `#FFFFFF` | 卡片 | |
| `--hgt-card-2` | `#F3F2EF` | 次级面 | |
| `--hgt-text` | `#2C2B28` | 主文字 | |
| `--hgt-text-2` | `#5C5A56` | 次要 | |
| `--hgt-text-3` | `#8A8780` | 弱化 | |
| `--hgt-border` | `#E2E0DA` | 描边 | |

### 夜墨（可选）

底 `#1A1A18`；主色 `#8EAA74`；点缀 `#C48A8D`；墨灰 `#8FA3B0`。

### 使用约束

- 主 CTA：`--hgt-brand` 竹青底 + 宣纸字
- 难度：简单竹青 · 中等 gold · 困难明茶褐
- 首页**不保留**右侧 AI/最近推理/语录侧栏（用户确认可去掉）
- 空态/loading 素材：空态用 `src/static/hgt/empty/*` 水墨插画；**loading 默认用品牌 logo（墨鱼问号汤碗）`HgtLoading` 组件**，轻浮动 + 光晕，文案由 UI 提供
- 分类 chip 仍用后端 tags，不得发明无效标签

> 注：图2「明茶褐」hex 源图残缺为 `#9E536`，落地补全为 **`#9E5356`**。

---

## 3. 字体 Typography

| 角色 | 字体栈 |
|---|---|
| 中文标题 | `"Source Han Serif SC", "Noto Serif SC", "Songti SC", "SimSun", Georgia, serif` |
| 中文正文 | `"Source Han Sans SC", "Noto Sans SC", "PingFang SC", "Microsoft YaHei", sans-serif` |
| 英文/品牌 | `"Cormorant Garamond", "Times New Roman", Georgia, serif` |
| 数据等宽 | `"JetBrains Mono", "SF Mono", Consolas, monospace` |

阶梯：

```
display  36–44px  600  宣纸 hero 大标题
h1       28px     600
h2       22–24px  600
h3       18–20px  600
body     15–16px  400
body-sm  13–14px  400
caption  12px     400
label    10–12px  400  letter-spacing 0.08–0.20em
```

---

## 4. 圆角 · 阴影 · 边框

| 用途 | 值 |
|---|---|
| chip / 输入 | `4–6px` |
| 按钮 | `6–8px`（笔触按钮可更方） |
| 卡片 | `10–12px` |
| 大卡 | `16px` |

阴影：浅色用暖灰轻影 `0 4px 20px rgba(42, 36, 32, 0.06)`；深色用更深墨影。

---

## 5. 壳层 Shell

### PC 顶栏

```
[Logo MOYUU 海龟汤 · 每一个故事，都是一个小小的世界。]
首页 | 题库 | AI推理 | 我的推理 | 友链/捐赠     [搜索] [主题] [头像]
```

- 高 `64px`，背景 `--hgt-bg-deep` 半透明 + blur。
- 品牌：EN `MOYUU` + 中文「海龟汤」；副标小字口号。
- **只展示真实路由**，不为效果图伪造「创作 / 排行」页。

### 手机

- 顶栏：Logo 短标 + 搜索/主题
- 底栏：首页 · 题库 · 推理 · 多人（若开放）· 我的

---

## 6. 首页信息架构（对齐效果图，H5 优先）

```
Hero（水墨山水 + 左侧文案）
  kicker: MOYUU · TURTLE SOUP
  标题: 雾里有故事，你来找真相。（「真相」朱砂）
  副文案
  CTA: 开始探索 | 随机一题
  stats（精选题目 / 推理玩家 / 评分）

分类 chips（后端 /tags，前 5 + 展开）

主区 PC 两栏 / 移动单栏
  左: 精选题库 + 换一批 + 卡片网格
  右: 和 AI 一起推理 | 最近推理 | 语录卡

三步还原真相
多人入口（支持时）
页脚品牌条
```

文案映射：

| 效果图文案 | 落地 |
|---|---|
| 开始探索 | `startPlay` → 题库 |
| 随机一题 | `playRandom` |
| 和 AI 一起推理 | 进入题库/推理 |
| 最近推理 | 登录后 `gameApi.history`；未登录展示题库精选摘要 |
| 语录卡 | 「有些答案，藏在看不见的地方。」 |

分类 chip **必须使用后端 `tags.name`**，不得发明「温情/欢乐/科幻」等无效 id。

---

## 7. 关键组件

### 主按钮 `btn-primary`（笔触）
- 高 46–48px，底 `--hgt-ink`，字宣纸色，衬线/中等字重，文案「开始探索 →」

### 次按钮 `btn-ghost`
- 宣纸透明 + 墨色描边，「随机一题」

### 谜题卡
- 封面 16:10 水墨图；标签：题材 + 难度
- 标题衬线；摘要 2 行；互动：点赞/评论/收藏或星级/时长/人数
- 卡底 `--hgt-card`，描边 `--hgt-border`

### Chip
- 高 32–36px；默认纸色描边；选中朱砂

### 纸卡 / 印章
- 汤面纸 `--hgt-paper`；右上角可叠「真相」朱砂印（`stamp_truth` 或 CSS 印）

---

## 8. 素材

```
src/static/hgt/ink/
  hero_ink_landscape.png   首页 hero 水墨山水
  aside_ai_ink.png         侧栏 AI 竖图
  cover_bus.png            末班车
  cover_empty_room.png     无人的房间
  cover_island.png         无人岛
  cover_sunflower.png      向日葵的约定
```

旧深海 illust/底图可保留为题库封面池的补充，但 hero 与首页主视觉优先 ink。

---

## 9. 实施顺序

1. Token → `App.vue` + `theme.ts`（light-first）
2. 素材入库 + `questionCover` 接入 ink
3. `HgtShell` 品牌/nav
4. 首页 hero + 精选题库 + 侧栏
5. 其余页面随 token 自动贴合；游戏页纸质区保持
6. lint / type-check / H5 build / 微信 build

---

## 10. 边界

- 匿名可浏览公开展示题与单人玩法；多人需登录。
- 客户端不缓存完整答案；后端权威。
- 文案与错误码走 locale/稳定 code，不硬编码后端策略。
- 不把效果图中不存在的后端标签写死进筛选。
