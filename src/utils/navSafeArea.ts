/**
 * 小程序/端内顶部安全高度（状态栏 + 自定义导航或胶囊占位）。
 * - 自定义导航：返回 statusBar + 胶囊区域高度，内容需 padding-top
 * - 原生默认导航：页面视口已在导航栏下方，返回 0，避免二次占位
 */
export interface TopNavMetrics {
  /** 内容区应预留的顶部高度（px） */
  offset: number
  /** 是否存在原生默认导航（页面视口已扣除导航） */
  defaultNav: boolean
  /** 状态栏高度 */
  statusBarHeight: number
}

/** Shell 沉浸布局所需的完整视口度量 */
export interface ShellChromeMetrics extends TopNavMetrics {
  /** 可视内容区高度（px）；原生导航时为导航下方 windowHeight */
  viewportHeight: number
  /** 底部安全区（home indicator）高度（px），无法测量时为 0 */
  safeBottom: number
}

const H5_CHROME_HEIGHT = 56

interface WindowMetrics {
  statusBarHeight: number
  windowHeight: number
  screenHeight: number
  windowWidth: number
}

interface MenuRect {
  height: number
  top: number
  left: number
}

function readWindowMetrics(): WindowMetrics {
  const uniAny = uni as {
    getWindowInfo?: () => {
      statusBarHeight?: number
      windowHeight?: number
      screenHeight?: number
      windowWidth?: number
      safeAreaInsets?: { bottom?: number }
      safeArea?: { bottom?: number }
    }
  }
  const info = typeof uniAny.getWindowInfo === 'function'
    ? uniAny.getWindowInfo()
    : uni.getSystemInfoSync()
  return {
    statusBarHeight: Number(info.statusBarHeight) || 0,
    windowHeight: Number(info.windowHeight) || 0,
    screenHeight: Number(info.screenHeight) || 0,
    windowWidth: Number(info.windowWidth) || 0,
  }
}

function readSafeBottom(): number {
  try {
    const uniAny = uni as {
      getWindowInfo?: () => {
        safeAreaInsets?: { bottom?: number }
        safeArea?: { bottom?: number }
        screenHeight?: number
      }
    }
    const info = typeof uniAny.getWindowInfo === 'function'
      ? uniAny.getWindowInfo()
      : (uni.getSystemInfoSync() as {
          safeAreaInsets?: { bottom?: number }
          safeArea?: { bottom?: number }
          screenHeight?: number
        })
    const insetBottom = Number(info?.safeAreaInsets?.bottom)
    if (Number.isFinite(insetBottom) && insetBottom >= 0)
      return insetBottom
    const safeBottomEdge = Number(info?.safeArea?.bottom)
    const screenHeight = Number(info?.screenHeight)
    if (Number.isFinite(safeBottomEdge) && Number.isFinite(screenHeight) && screenHeight > 0)
      return Math.max(0, screenHeight - safeBottomEdge)
  }
  catch {}
  return 0
}

function readMenuRect(): MenuRect | null {
  try {
    const uniAny = uni as {
      getMenuButtonBoundingClientRect?: () => {
        height?: number
        top?: number
        left?: number
        right?: number
      }
    }
    const fn = uniAny.getMenuButtonBoundingClientRect
    if (typeof fn !== 'function')
      return null
    const menu = fn.call(uni)
    const height = Number(menu?.height) || 0
    if (height <= 0)
      return null
    return {
      height,
      top: Number(menu?.top) || 0,
      left: Number(menu?.left) || 0,
    }
  }
  catch {
    return null
  }
}

function resolveMpTopNav(): TopNavMetrics {
  const { statusBarHeight } = readWindowMetrics()
  const menu = readMenuRect()
  const menuHeight = menu?.height ?? 32
  const menuTop = menu?.top ?? statusBarHeight + 4
  const verticalGap = Math.max(0, menuTop - statusBarHeight)
  const navBarHeight = menuHeight + verticalGap * 2
  const customNavHeight = statusBarHeight + navBarHeight

  // Product pages explicitly use custom navigation except on MP-TOUTIAO.
  // A keyboard or desktop mini-program window must not be mistaken for native navigation.
  return {
    offset: Math.max(statusBarHeight, customNavHeight),
    defaultNav: false,
    statusBarHeight,
  }
}

/** 当前运行环境顶部导航度量 */
export function resolveTopNavMetrics(): TopNavMetrics {
  let metrics: TopNavMetrics = {
    offset: H5_CHROME_HEIGHT,
    defaultNav: false,
    statusBarHeight: 0,
  }

  // #ifdef H5
  metrics = { offset: H5_CHROME_HEIGHT, defaultNav: false, statusBarHeight: 0 }
  // #endif

  // #ifdef MP-TOUTIAO
  // 抖音端使用原生默认导航，页面视口已扣除
  metrics = { offset: 0, defaultNav: true, statusBarHeight: 0 }
  // #endif

  // #ifdef MP-WEIXIN || MP-ALIPAY || MP-BAIDU || MP-QQ || MP-LARK || MP-JD || MP-KUAISHOU || MP-HARMONY || APP-PLUS || APP-NVUE || APP-HARMONY
  metrics = resolveMpTopNav()
  // #endif

  return metrics
}

/** 当前运行环境的顶部占位高度（px） */
export function resolveTopNavOffset(): number {
  return resolveTopNavMetrics().offset
}

/**
 * Shell 沉浸布局度量：顶部导航 + 可视高度 + 底部安全区。
 * 小程序端优先用系统 px，避免 100dvh / env(safe-area-inset-bottom) 在抖音等端不稳定。
 */
export function resolveShellChromeMetrics(): ShellChromeMetrics {
  const top = resolveTopNavMetrics()
  const { windowHeight } = readWindowMetrics()
  // Browser screen coordinates are not viewport coordinates. H5 uses CSS env().
  let safeBottom = 0
  // #ifndef H5
  safeBottom = readSafeBottom()
  // #endif

  let viewportHeight = windowHeight
  if (!Number.isFinite(viewportHeight) || viewportHeight <= 0) {
    // #ifdef H5
    viewportHeight = typeof window !== 'undefined' ? window.innerHeight : 0
    // #endif
  }

  return {
    ...top,
    viewportHeight,
    safeBottom,
  }
}

/** 自定义导航时胶囊右侧避让 padding（px） */
export function resolveCapsuleRightPadding(): number {
  let pad = 16
  // #ifdef MP-WEIXIN || MP-ALIPAY || MP-BAIDU || MP-QQ || MP-LARK || MP-JD || MP-KUAISHOU
  const { windowWidth } = readWindowMetrics()
  const menu = readMenuRect()
  if (menu && windowWidth)
    pad = Math.max(16, windowWidth - menu.left + 12)
  // #endif
  return pad
}
