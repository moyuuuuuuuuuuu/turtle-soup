import request from '@/utils/http'
export interface PlayerRow {
  database_id: number
  id: string
  username: string
  email: string
  avatar_url?: string
  status: 'active' | 'disabled'
  active_sessions: number
  create_time: string
}
export default {
  list: (params: Record<string, unknown>) =>
    request.get<{ items: PlayerRow[]; total: number }>({ url: '/core/player/index', params }),
  read: (id: number) => request.get({ url: '/core/player/read', params: { id } }),
  status: (id: number, status: string) =>
    request.post({ url: '/core/player/status', data: { id, status } }),
  revoke: (id: number) => request.post({ url: '/core/player/revoke', data: { id } }),
  loginLogs: (id: number) => request.get<any[]>({ url: '/core/player/loginLogs', params: { id } }),
  mergeLogs: (id: number) => request.get<any[]>({ url: '/core/player/mergeLogs', params: { id } })
}
