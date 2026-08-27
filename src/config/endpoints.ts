const configuredApiBaseUrl = import.meta.env.VITE_API_BASE_URL
const configuredWebSocketUrl = import.meta.env.VITE_WS_BASE_URL

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
