const configuredApiBaseUrl = import.meta.env.VITE_API_BASE_URL
const configuredWebSocketUrl = import.meta.env.VITE_WS_BASE_URL

interface UniH5RuntimeConfig {
  router?: {
    base?: string
    mode?: 'hash' | 'history'
  }
}

declare const __uniConfig: UniH5RuntimeConfig | undefined

export function resolveApiBaseUrl() {
  if (configuredApiBaseUrl)
    return configuredApiBaseUrl.replace(/\/$/, '')

  let baseUrl = 'http://hgt.test/api/v1'
  // #ifdef H5
  baseUrl = '/api/v1'
  // #endif
  return baseUrl
}

export function resolveWebSocketUrl() {
  if (configuredWebSocketUrl)
    return configuredWebSocketUrl

  let socketUrl = 'ws://hgt.test/ws/'
  // #ifdef H5
  const protocol = window.location.protocol === 'https:' ? 'wss:' : 'ws:'
  socketUrl = `${protocol}//${window.location.host}/ws/`
  // #endif
  return socketUrl
}

export function resolveShareUrl(path: string) {
  let shareUrl = path
  // #ifdef H5
  const runtimeRouter = typeof __uniConfig === 'undefined' ? undefined : __uniConfig.router
  const mode = runtimeRouter?.mode || (window.location.hash.startsWith('#/') ? 'hash' : 'history')
  const base = `/${String(runtimeRouter?.base || '/').replace(/^\/+|\/+$/g, '')}`
  const basePath = base === '/' ? '/' : `${base}/`
  const routePath = path.startsWith('/') ? path : `/${path}`
  const pathname = mode === 'hash'
    ? `${basePath}#${routePath}`
    : `${basePath.replace(/\/$/, '')}${routePath}`
  shareUrl = `${window.location.origin}${pathname}`
  // #endif
  return shareUrl
}
