/**
 * 题目详情路由。
 * uni-app 页面路径是静态的，无法注册 `pages/question-detail/:id`。
 * H5 history 模式下把地址栏美化为 `/pages/question-detail/{id}`；
 * 实际跳转仍使用已注册的 `/pages/question-detail/index?id=...`，保证小程序等端可用。
 */

const PAGE_PATH = '/pages/question-detail/index'
const PRETTY_PATH_RE = /^\/pages\/question-detail\/([^/]+)\/?$/

interface UniH5RuntimeConfig {
  router?: {
    base?: string
    mode?: 'hash' | 'history'
  }
}

declare const __uniConfig: UniH5RuntimeConfig | undefined

function isHistoryRouter() {
  // #ifdef H5
  const runtimeRouter = typeof __uniConfig === 'undefined' ? undefined : __uniConfig.router
  const mode = runtimeRouter?.mode || (window.location.hash.startsWith('#/') ? 'hash' : 'history')
  return mode === 'history'
  // #endif
  // #ifndef H5
  return false
  // #endif
}

function encodeId(id: string) {
  return encodeURIComponent(id)
}

/** 生成 uni-app 可跳转的注册路径（query 形式） */
export function buildQuestionDetailUrl(id: string, roomId?: string) {
  const params = new URLSearchParams({ id })
  if (roomId)
    params.set('room_id', roomId)
  return `${PAGE_PATH}?${params.toString()}`
}

/** 生成 H5 地址栏 / 分享用的 path 形式；非 history 模式回退为 query */
export function buildQuestionDetailPrettyPath(id: string, roomId?: string) {
  // #ifdef H5
  if (isHistoryRouter()) {
    const params = new URLSearchParams()
    if (roomId)
      params.set('room_id', roomId)
    const qs = params.toString()
    const base = `pages/question-detail/${encodeId(id)}`
    return qs ? `/${base}?${qs}` : `/${base}`
  }
  // #endif
  return buildQuestionDetailUrl(id, roomId)
}

/**
 * 启动前把 `/pages/question-detail/{id}` 改写成注册页 query，
 * 否则 uni-app H5 路由无法匹配页面。必须在 createApp / 路由匹配前调用。
 */
export function bootstrapPrettyQuestionRoute() {
  // #ifdef H5
  if (!isHistoryRouter())
    return
  const match = window.location.pathname.match(PRETTY_PATH_RE)
  if (!match)
    return
  const id = decodeURIComponent(match[1])
  if (!id || id === 'index')
    return
  const params = new URLSearchParams(window.location.search)
  params.set('id', id)
  const nextUrl = `${PAGE_PATH}?${params.toString()}${window.location.hash}`
  window.history.replaceState(window.history.state, '', nextUrl)
  // #endif
}

/** 页面加载成功后，把地址栏改回 path 形式 */
export function applyPrettyQuestionDetailUrl(id: string, roomId?: string) {
  // #ifdef H5
  if (!isHistoryRouter() || !id)
    return
  window.history.replaceState(window.history.state, '', buildQuestionDetailPrettyPath(id, roomId))
  // #endif
}

export interface OpenQuestionDetailOptions {
  id: string
  roomId?: string
  replace?: boolean
}

/** 跳转到题目详情，并在 H5 上同步美化地址栏 */
export function openQuestionDetail(options: OpenQuestionDetailOptions) {
  const { id, roomId, replace } = options
  const url = buildQuestionDetailUrl(id, roomId)
  return new Promise<void>((resolve, reject) => {
    const callbacks = {
      url,
      success: () => {
        applyPrettyQuestionDetailUrl(id, roomId)
        resolve()
      },
      fail: (error: { errMsg?: string } | Error) => {
        reject(error instanceof Error ? error : new Error(error?.errMsg || '打开题目失败'))
      },
    }
    if (replace)
      uni.redirectTo(callbacks)
    else
      uni.navigateTo(callbacks)
  })
}
