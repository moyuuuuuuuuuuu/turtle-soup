import type { ApiEnvelope, DonationPage, GameSnapshot, HomeStats, PublicQuestion, RoomSnapshot } from '@/types/game'
import { currentAccessToken, playerApi } from '@/api/player'
import { resolveApiBaseUrl } from '@/config/endpoints'

const baseUrl = resolveApiBaseUrl()
const tokenKey = 'turtle_anonymous_token'
const requestId = () => `${Date.now()}-${Math.random().toString(36).slice(2)}`

export class TurtleApiError extends Error {
  constructor(public readonly code: string, message: string) {
    super(message)
    this.name = 'TurtleApiError'
  }
}

function queryString(params: Record<string, unknown>) {
  return Object.entries(params)
    .filter(([, value]) => value !== undefined && value !== null && value !== '')
    .map(([key, value]) => `${encodeURIComponent(key)}=${encodeURIComponent(String(value))}`)
    .join('&')
}

async function request<T>(path: string, method: 'GET' | 'POST' = 'GET', data?: Record<string, unknown>, retried = false): Promise<T> {
  const token = currentAccessToken() || uni.getStorageSync(tokenKey)
  return new Promise((resolve, reject) => uni.request({
    url: `${baseUrl}${path}`,
    method,
    data,
    header: { 'Authorization': token ? `Bearer ${token}` : '', 'X-Request-Id': requestId() },
    success: ({ data: raw, statusCode }: UniApp.RequestSuccessCallbackResult) => {
      const body = raw as ApiEnvelope<T>
      if (body?.code === 'success')
        return resolve(body.data)
      if (!retried && currentAccessToken() && body?.code === 'auth.token_invalid')
        return playerApi.restore().then(result => result ? request<T>(path, method, data, true).then(resolve, reject) : reject(new TurtleApiError(body.code, body.message || body.code)))
      return reject(new TurtleApiError(body?.code || 'system.error', body?.message || body?.code || `请求失败（HTTP ${statusCode}）`))
    },
    fail: () => reject(new Error('网络请求失败，请检查网络连接')),
  }))
}
export async function ensureAnonymousSession() {
  if (currentAccessToken())
    return currentAccessToken()
  const token = uni.getStorageSync(tokenKey)
  if (token)
    return token
  let device = uni.getStorageSync('turtle_device_id')
  if (!device) {
    device = requestId()
    uni.setStorageSync('turtle_device_id', device)
  }
  const result = await request<{ token: string }>('/anonymous/session', 'POST', { device_id: device })
  uni.setStorageSync(tokenKey, result.token)
  return result.token
}
export const questionApi = {
  list: (params: Record<string, unknown> = {}) => request<{ items: PublicQuestion[], pagination: Record<string, number> }>(`/questions?${queryString(params)}`),
  read: (id: string, language = 'zh-CN') => request<PublicQuestion>(`/questions/read?id=${encodeURIComponent(id)}&language=${encodeURIComponent(language)}`),
  random: (difficulty?: number, riskLevel?: 'safe' | 'caution') => request<PublicQuestion>(`/questions/random?${queryString({ difficulty, risk_level: riskLevel })}`),
}
export const homeApi = { stats: () => request<HomeStats>('/home/stats') }
export const gameApi = { create: (question_id: string, risk_confirmed = false) => request<GameSnapshot>('/games', 'POST', { question_id, language: 'zh-CN', risk_confirmed }), read: (id: string) => request<GameSnapshot>(`/games/read?id=${id}`), history: () => request<Array<{ id: string, status: string, title: string, difficulty: number }>>('/games/history'), ask: (id: string, question: string) => request<GameSnapshot>('/games/ask', 'POST', { id, question, request_id: requestId() }), hint: (id: string, level: number) => request<GameSnapshot>('/games/hint', 'POST', { id, level, request_id: requestId() }), guess: (id: string, guess: string) => request<GameSnapshot>('/games/guess', 'POST', { id, guess, request_id: requestId() }), abandon: (id: string) => request<GameSnapshot>('/games/abandon', 'POST', { id }) }
export const roomApi = {
  list: () => request<RoomSnapshot[]>('/rooms'),
  mine: () => request<RoomSnapshot[]>('/rooms/mine'),
  read: (id: string) => request<RoomSnapshot>(`/rooms/read?id=${encodeURIComponent(id)}`),
  create: (data: { game_id: string, max_players?: number, visibility?: 'private' | 'public', language?: string }) => request<RoomSnapshot>('/rooms', 'POST', data),
  join: (data: { id?: string, invite_code?: string }) => request<RoomSnapshot>('/rooms/join', 'POST', data),
  resolveQuestion: (invite_code: string) => request<{ question_id: string, status: string }>(`/rooms/resolve-question?invite_code=${encodeURIComponent(invite_code)}`),
  ready: (id: string, ready: boolean) => request<RoomSnapshot>('/rooms/ready', 'POST', { id, ready }),
  start: (id: string) => request<RoomSnapshot>('/rooms/start', 'POST', { id }),
  next: (id: string, question_id: string, risk_confirmed = false) => request<RoomSnapshot>('/rooms/next', 'POST', { id, question_id, risk_confirmed }),
  leave: (id: string) => request<void>('/rooms/leave', 'POST', { id }),
  close: (id: string) => request<void>('/rooms/close', 'POST', { id }),
}
export const donationApi = { page: () => request<DonationPage>('/donations') }
