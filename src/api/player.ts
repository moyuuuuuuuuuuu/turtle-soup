/* eslint-disable style/max-statements-per-line */
import { resolveApiBaseUrl } from '@/config/endpoints'

export interface PlayerUser { id: string, username: string, email: string | null, avatar_url?: string, bio?: string | null, status: string }
export interface PlayerSession { id: string, device_name: string, platform: string, last_used_at?: string, expires_at: string }
interface Envelope<T> { code: string, message: string, data: T }
export interface AuthResult { access_token: string, refresh_token: string, expires_in: number, session: PlayerSession, user: PlayerUser, merged_games: number }
export interface LegalDocuments { service_terms: string, privacy_policy: string }

const baseUrl = resolveApiBaseUrl()
const refreshKey = 'turtle_player_refresh_token'
let accessToken = ''
let restorePromise: Promise<AuthResult | null> | null = null

export function currentAccessToken() { return accessToken }
export function invalidatePlayerSession() {
  accessToken = ''
  uni.removeStorageSync(refreshKey)
}
function accessTokenExpiresAt(token: string) {
  try {
    const payload = token.split('.')[1]
    if (!payload)
      return 0
    const normalized = payload.replace(/-/g, '+').replace(/_/g, '/')
    return Number(JSON.parse(atob(normalized)).exp || 0)
  }
  catch {
    return 0
  }
}
export function currentDeviceId() { let id = uni.getStorageSync('turtle_device_id'); if (!id) { id = `${Date.now()}-${Math.random().toString(36).slice(2)}`; uni.setStorageSync('turtle_device_id', id) } return String(id) }
function currentDevice() { const info = uni.getSystemInfoSync(); const rawPlatform = String(info.uniPlatform || info.platform || 'unknown'); return { name: [info.deviceBrand, info.model].filter(Boolean).join(' ') || '当前设备', platform: rawPlatform === 'web' ? 'h5' : rawPlatform } }

async function call<T>(path: string, method: 'GET' | 'POST' | 'DELETE' = 'POST', data?: Record<string, unknown>, authenticated = false, retried = false): Promise<T> {
  return new Promise((resolve, reject) => uni.request({
    url: `${baseUrl}${path}`,
    method,
    data,
    header: { 'Authorization': authenticated && accessToken ? `Bearer ${accessToken}` : '', 'X-Anonymous-Token': String(uni.getStorageSync('turtle_anonymous_token') || ''), 'X-Device-Id': currentDeviceId(), 'X-Device-Name': currentDevice().name, 'X-Platform': currentDevice().platform },
    success: ({ data: raw }) => {
      const body = raw as Envelope<T>; if (body?.code === 'success')
        return resolve(body.data); if (authenticated && !retried && body?.code === 'auth.token_invalid')
        return restoreAccess().then(result => result ? call<T>(path, method, data, true, true).then(resolve, reject) : reject(new Error(body.message || body.code))); return reject(new Error(body?.message || body?.code || '操作失败，请稍后重试'))
    },
    fail: () => reject(new Error('网络请求失败，请检查网络连接')),
  }))
}

async function uploadAvatarFile(filePath: string, retried = false): Promise<PlayerUser> {
  await ensurePlayerAccessToken()
  return new Promise((resolve, reject) => uni.uploadFile({
    url: `${baseUrl}/me/avatar`,
    filePath,
    name: 'avatar',
    header: { 'Authorization': accessToken ? `Bearer ${accessToken}` : '', 'X-Device-Id': currentDeviceId(), 'X-Device-Name': currentDevice().name, 'X-Platform': currentDevice().platform },
    success: ({ data: raw }) => {
      try {
        const body = JSON.parse(raw) as Envelope<PlayerUser>
        if (body?.code === 'success')
          return resolve(body.data)
        if (!retried && body?.code === 'auth.token_invalid')
          return restoreAccess().then(result => result ? uploadAvatarFile(filePath, true).then(resolve, reject) : reject(new Error(body.message || body.code)))
        reject(new Error(body?.message || body?.code || '头像上传失败'))
      }
      catch {
        reject(new Error('头像上传响应格式异常'))
      }
    },
    fail: () => reject(new Error('头像上传失败，请检查网络连接')),
  }))
}
function accept(result: AuthResult) { accessToken = result.access_token; uni.setStorageSync(refreshKey, result.refresh_token); uni.removeStorageSync('turtle_anonymous_token'); return result }

async function performRestore() {
  const refresh_token = String(uni.getStorageSync(refreshKey) || '')
  if (!refresh_token)
    return null
  try { return accept(await call<AuthResult>('/auth/token/refresh', 'POST', { refresh_token })) }
  catch { invalidatePlayerSession(); return null }
}

async function restoreAccess() {
  if (restorePromise)
    return restorePromise
  restorePromise = performRestore().finally(() => { restorePromise = null })
  return restorePromise
}

export async function ensurePlayerAccessToken() {
  if (accessToken && accessTokenExpiresAt(accessToken) > Math.floor(Date.now() / 1000) + 30)
    return accessToken
  return (await restoreAccess())?.access_token || ''
}

export const playerApi = {
  legalDocuments: () => call<LegalDocuments>('/legal/documents', 'GET'),
  sendCode: (email: string, purpose: string) => call<{ sent: boolean }>('/auth/email-code/send', 'POST', { email, purpose }),
  register: (data: Record<string, unknown>) => call<AuthResult>('/auth/register', 'POST', data).then(accept),
  passwordLogin: (email: string, password: string) => call<AuthResult>('/auth/login/password', 'POST', { email, password }).then(accept),
  codeLogin: (email: string, email_code: string) => call<AuthResult>('/auth/login/email-code', 'POST', { email, email_code }).then(accept),
  miniProgramLogin: (platform: 'wechat' | 'douyin', code: string, anonymous_code = '') => call<AuthResult>('/auth/login/mini-program', 'POST', { platform, code, anonymous_code }).then(accept),
  restore: restoreAccess,
  me: () => call<PlayerUser>('/me', 'GET', undefined, true),
  sessions: () => call<PlayerSession[]>('/me/sessions', 'GET', undefined, true),
  logout: async (all = false) => {
    try {
      await call(all ? '/auth/logout-all' : '/auth/logout', 'POST', {}, true)
    }
    finally {
      invalidatePlayerSession()
    }
  },
  revokeSession: (session_id: string) => call('/me/sessions', 'DELETE', { session_id }, true),
  changeUsername: (username: string) => call<PlayerUser>('/me/username', 'POST', { username }, true),
  updateProfile: (username: string, bio: string) => call<PlayerUser>('/me/profile', 'POST', { username, bio }, true),
  uploadAvatar: uploadAvatarFile,
  changeEmail: (email: string, password: string, email_code: string) => call<PlayerUser>('/me/email/change', 'POST', { email, password, email_code }, true),
  changePassword: (current_password: string, password: string) => call<AuthResult>('/auth/password/change', 'POST', { current_password, password }, true).then(accept),
  resetPassword: (email: string, email_code: string, password: string) => call<AuthResult>('/auth/password/reset', 'POST', { email, email_code, password }).then(accept),
}
