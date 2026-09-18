# 友链功能 · 服务端配合说明

> 客户端分支：`ui`（本仓库）  
> 服务端归属：`system-manage`（Webman / 用户 API / 管理 API）  
> 文档用途：给后端同学对齐「友链」需要暴露的 HTTP 契约与后台能力。  
> 客户端现状：页面与请求层已就绪，**当前只依赖一个公开列表接口**；申请流程目前是「复制模板」，尚未调用服务端提交接口。

---

## 1. 职责边界

| 侧 | 职责 |
|---|---|
| **客户端 `ui`** | 展示已审核友链卡片；失败时用内置 fallback 数据兜底；提供「申请友链」说明与模板复制 |
| **服务端 `system-manage`** | 维护友链数据源；提供公开列表 API；后台增删改与审核；保证只返回可对外展示的记录 |
| **管理端 `system-manage-ui`** | （建议）后台友链管理界面，不进本客户端 |

客户端**不在本地实现**审核、排序权威、可用性探测、状态机；一切以服务端返回为准。

---

## 2. 必做：公开友链列表

### 2.1 接口

| 项 | 值 |
|---|---|
| Method | `GET` |
| Path | `/api/v1/friend-links` |
| Auth | **无需登录**（匿名可访问；与 `/home/stats`、`/donations` 同类公开内容） |
| 幂等 | 纯读接口，无 `request_id` 要求 |
| 客户端调用 | `src/api/turtle.ts` → `friendLinkApi.list()` |
| 页面 | `src/pages/friends/index.vue` |

### 2.2 统一响应信封

与项目现有 player API 一致，**不要**返回裸数组：

```json
{
  "code": "success",
  "message": "",
  "data": {
    "items": [ /* FriendLink[] */ ]
  },
  "request_id": "…",
  "timestamp": 1710000000
}
```

客户端只认 `body.code === 'success'`，并取出 `body.data`。  
`data` 必须是对象，且至少包含 `items` 数组（即使为空，也建议 `items: []`，不要省略字段或返回 `null`）。

### 2.3 `FriendLink` 字段（与客户端类型对齐）

客户端类型定义在 `src/types/game.ts`：

```ts
export interface FriendLink {
  id: string
  name: string
  url: string
  logo_url?: string | null
  description?: string | null
}
```

| 字段 | 类型 | 必填 | 说明 |
|---|---|---|---|
| `id` | `string` | 是 | 稳定唯一标识，用于列表 `:key`。建议用主键字符串或业务 slug，**不要每次列表随机生成** |
| `name` | `string` | 是 | 站点名称，展示在卡片标题；空时客户端会显示异常，服务端请校验非空 |
| `url` | `string` | 是 | **完整外链**，建议强制 `https://`；H5 端会 `window.open`，小程序端会复制该 URL |
| `logo_url` | `string \| null` | 否 | Logo 绝对 URL。为 `null`/空时客户端用名称首字 + 色相渐变兜底 |
| `description` | `string \| null` | 否 | 一句话简介。为空时客户端显示占位文案「值得顺路拜访的一站。」 |

示例 `data`：

```json
{
  "items": [
    {
      "id": "1",
      "name": "墨鱼的工具箱",
      "url": "https://tooldeck.moyuu.ink",
      "logo_url": "https://cdn.example.com/friends/tooldeck.png",
      "description": "精选实用的在线工具，让效率更进一步。"
    },
    {
      "id": "vuejs",
      "name": "Vue.js",
      "url": "https://vuejs.org",
      "logo_url": null,
      "description": "渐进式 JavaScript 框架。"
    }
  ]
}
```

### 2.4 返回规则（服务端侧建议）

1. **只返回 `status = active/approved` 的记录**；草稿、待审、下线、黑名单不得出现在公开列表。
2. **排序权威在服务端**：建议支持 `sort_weight`（或类似字段）+ 更新时间，接口直接按最终展示顺序返回；客户端目前**不再二次排序**。
3. **URL 规范化**：入库前校验可解析、协议白名单（至少 `https`/`http`）；对外返回完整绝对地址。
4. **Logo**：建议返回可公开访问的绝对 URL（自有 CDN 或对象存储）。避免相对路径，小程序端无法可靠拼接私有相对路径。
5. **简介长度**：客户端 UI 约两行截断，建议服务端限制约 40–80 字，避免超长字段拖垮卡片。
6. **性能**：列表通常 < 100 条；可加短缓存（例如 60–300s），无需分页参数（客户端当前只调一次、全量展示）。
7. **失败语义**：数据源异常时返回非 `success` 的 `code`（如 `system.error` / `friend_links.unavailable`），不要返回半截 JSON。

### 2.5 客户端对失败/空数据的行为（便于联调）

| 服务端行为 | 客户端表现 |
|---|---|
| `code = success` 且 `items` 有数据 | 正常渲染卡片网格 |
| `code = success` 且 `items` 为空数组 | 展示空态「还没有朋友停靠…」+ 申请入口（**不会**再吃 fallback） |
| `code = success` 但 `items` 缺失/空 | 触发内置 demo 友链（开发/兜底） |
| HTTP 失败或 `code != success` | **当前实现也会回落到 demo 友链**（页面几乎不暴露错误态） |

> 说明：联调时若一直看到 Obsidian / Laravel 等 demo 站点，优先检查接口路径、网关前缀 `/api/v1`、信封 `code` 字段，而不是怀疑页面写死。

---

## 3. 建议做：站点回链信息（支撑「先添加本站」）

申请规则要求对方先添加本站友链。若希望页面展示「如何回链本站」或未来做自动校验，建议服务端额外提供：

| 项 | 值 |
|---|---|
| Method | `GET` |
| Path | `/api/v1/friend-links/site`（或 `/api/v1/site/friend-link`） |
| Auth | 公开 |

建议响应 `data`：

```json
{
  "name": "墨鱼海龟汤",
  "url": "https://turtle-soup.moyuu.ink",
  "logo_url": "https://…/logo.png",
  "description": "海龟汤推理与轻量语言陪伴",
  "apply_email": "friend@example.com",
  "apply_rules": [
    "站点可正常访问，内容健康、无恶意软件",
    "请先添加本站友链，再提交申请",
    "需提供：名称、地址、Logo、一句话简介"
  ]
}
```

**优先级：P2（可选）**  
当前客户端申请弹层是静态文案 + 复制模板，**没有**该接口也能上线。

---

## 4. 可选增强：在线提交友链申请

若希望申请从「复制模板发邮件/人工」升级为站内提交，再补以下能力。  
**当前客户端未接线**，属于后续迭代。

### 4.1 提交申请

| 项 | 值 |
|---|---|
| Method | `POST` |
| Path | `/api/v1/friend-links/apply` |
| Auth | 建议：登录用户可提交；匿名可拒绝（`auth.login_required`）或允许匿名 + 风控 |
| 幂等 | 状态变更请求，**建议支持 `request_id`** |

Request body（对齐现有申请模板字段）：

```json
{
  "name": "站点名称",
  "url": "https://example.com",
  "logo_url": "https://example.com/logo.png",
  "description": "一句话简介",
  "reciprocal_url": "https://对方站友链页",
  "contact_email": "contact@example.com",
  "request_id": "1710000000-abc123"
}
```

成功 `data` 建议：

```json
{
  "application_id": "…",
  "status": "pending",
  "message": "申请已提交，审核通过后将展示在友链页"
}
```

建议稳定错误码（客户端按 code 处理，不匹配中文文案）：

| code | 场景 |
|---|---|
| `auth.login_required` | 需要登录 |
| `friend_link.duplicate` | 同 URL / 同域名已有申请或已收录 |
| `friend_link.invalid_url` | URL 非法 |
| `friend_link.rate_limited` | 提交过频 |
| `friend_link.apply_closed` | 暂未开放申请 |

### 4.2 （可选）查询我的申请状态

| Method | `GET` | Path `/api/v1/friend-links/applications/mine` | Auth 登录 |

```json
{
  "items": [
    {
      "id": "…",
      "name": "…",
      "url": "…",
      "status": "pending | approved | rejected",
      "reject_reason": null,
      "created_at": "2026-01-01T00:00:00Z"
    }
  ]
}
```

---

## 5. 服务端内部模型与后台（不对客户端暴露）

客户端不消费这些字段，但后台与审核需要。建议表结构至少包含：

| 字段 | 说明 |
|---|---|
| `id` | 主键 |
| `name` / `url` / `logo_url` / `description` | 与公开 API 同义 |
| `status` | `pending` / `approved` / `rejected` / `offline` 等 |
| `sort_weight` | 展示顺序 |
| `contact_email` | 申请人联系方式 |
| `reciprocal_url` | 对方回链地址（审核用） |
| `note` / `reject_reason` | 内部备注、拒绝原因 |
| `approved_at` / `updated_at` | 审计 |

管理端（`system-manage-ui`）建议能力：

- 列表筛选：状态、名称、域名
- 通过 / 下线 / 删除 / 调序
- 编辑展示字段
- 查看申请记录与拒绝原因

**审核建议（人工即可，不必一上来做爬虫）：**

1. URL 可访问  
2. 内容健康、无恶意软件  
3. 已添加本站友链（可人工打开 `reciprocal_url` 核对）  
4. Logo 可访问（若提交了）

---

## 6. 本站基础信息（环境与运营配置）

建议在服务端/运营配置中固化（可与第 3 节接口同源），避免客户端写死业务文案：

```text
站点名称：墨鱼海龟汤
站点地址：https://turtle-soup.moyuu.ink
Logo：     <公开 URL>
简介：     海龟汤推理游戏
申请渠道： <邮箱 / 管理后台 / 未来在线表单>
```

申请模板字段（客户端已写死，与后端对齐时请保持一致）：

```text
【墨鱼海龟汤 · 友链申请】
站点名称：
站点地址：
Logo：
一句话简介：
回链地址：
联系邮箱：
```

---

## 7. 安全与平台注意

1. **公开列表可缓存**，但不要把管理接口、申请详情挂到同一公开路径且不做鉴权。
2. **Logo / 外链** 若接受 UGC，入库需校验协议，避免 `javascript:` 等伪协议。
3. **微信小程序**：外链不可直接打开，客户端会复制 `url`；因此 **`url` 必须是用户可手动访问的真实地址**。
4. **CORS（H5）**：H5 相对路径走 `/api/v1` 反代时一般无跨域；若 `VITE_API_BASE_URL` 指到独立域名，需服务端允许前端 Origin。
5. **不要**把完整管理后台鉴权逻辑放到客户端；客户端只消费公开数据。
6. 错误请继续使用**稳定英文 code**，文案由客户端/后台翻译。

---

## 8. 联调验收清单

服务端完成后，用以下用例自测，并可让客户端同学复验：

- [ ] `GET /api/v1/friend-links` 未登录返回 `code=success`，`data.items` 为数组  
- [ ] 每条含 `id` / `name` / `url`；`logo_url`、`description` 缺省可为 `null`  
- [ ] 仅 `approved` 数据出现在列表；顺序符合后台配置  
- [ ] 空库时 `items: []`（客户端展示空态，而不是接口 500）  
- [ ] 非 success 时信封结构完整（`code` / `message` / `request_id`）  
- [ ] H5：卡片点击可打开 `url`  
- [ ] 小程序：点击后能复制出正确 `url`  
- [ ] `logo_url` 可在浏览器直接访问；为空时卡片仍有首字兜底  
- [ ] （若实现申请）POST `/friend-links/apply` 幂等：相同 `request_id` 不重复入库  
- [ ] （若实现申请）重复 URL、频率限制返回约定错误码  

### 客户端本地联调提示

```ts
// src/api/turtle.ts
export const friendLinkApi = {
  list: () => request<{ items: FriendLink[] }>('/friend-links'),
}
```

- 请求基址：`resolveApiBaseUrl()` → H5 默认 `/api/v1`，其它端可用 `VITE_API_BASE_URL`  
- 完整路径即：`{BASE}/friend-links`  
- 页面入口：账户页「友链」→ `/pages/friends/index`；导航壳 `HgtShell` 也有友链入口  

---

## 9. 优先级汇总

| 优先级 | 事项 | 是否阻塞客户端上线 |
|---|---|---|
| **P0** | `GET /api/v1/friend-links` 公开列表 + 信封 + 字段 | **是**（没有接口时页面走 demo 数据，不能作为生产内容） |
| **P0** | 后台可维护友链（哪怕先手工 SQL/简单 CRUD） | 是（否则无真实数据源） |
| **P1** | 字段校验、排序、状态过滤、短缓存 | 否，但强烈建议 |
| **P2** | `GET /friend-links/site` 回链站点信息 | 否 |
| **P3** | 在线申请 `POST /friend-links/apply` + 审核流 | 否（客户端后续再接） |

---

## 10. 客户端已就绪 / 明确不做的部分

**已就绪：**

- 友链页 UI、加载/空态、卡片展示、外链打开/复制  
- `FriendLink` 类型与 `friendLinkApi.list()`  
- 申请说明弹层与模板复制  
- 入口：账户页、顶栏导航  

**默认不做（等服务端契约再扩）：**

- 客户端提交申请、查看审核状态  
- 客户端排序/过滤审核状态  
- 客户端探测对方是否回链  
- 把答案/管理权限下放到前端  

---

## 11. 变更约定

1. **字段只增不改义**：新增可选字段欢迎；重命名/改类型需提前同步客户端改类型定义。  
2. **列表接口保持公开、轻量**；复杂管理查询放 `/admin/*`（管理端）。  
3. 若客户端将改为消费申请接口，以本文第 4 节为准更新 `src/api/turtle.ts` 与类型，而不是在页面里散落 `uni.request`。  

---

**一句话结论：**  
客户端友链页已经按「公开列表 + 信封 + `items[]`」写好；服务端**最少只要实现** `GET /api/v1/friend-links`（返回已审核友链）并配好后台数据维护即可配合上线；申请在线化与站点信息接口是后续增强，不阻塞第一版。
