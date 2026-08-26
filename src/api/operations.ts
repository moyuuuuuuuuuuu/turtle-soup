import request from '@/utils/http'

export interface RoomRow {
  id: number
  public_id: string
  invite_code: string
  name: string
  status: string
  visibility: string
  max_players: number
  member_count: number
  create_time: string
}
export interface DonationRow {
  id: number
  public_id: string
  donor_name: string
  amount: string
  method?: string
  message?: string
  donated_at: string
  status: boolean
  sort: number
}
export interface DonationChannel {
  id: number
  method: 'wechat' | 'alipay'
  name: string
  qr_code_url: string
  status: boolean
  sort: number
}

export const roomAdminApi = {
  list: (params: Record<string, unknown>) =>
    request.get<{ items: RoomRow[]; total: number }>({ url: '/core/room/index', params }),
  read: (id: number) => request.get({ url: '/core/room/read', params: { id } }),
  close: (id: number) => request.post({ url: '/core/room/close', data: { id } })
}
export const donationAdminApi = {
  list: (params: Record<string, unknown>) =>
    request.get<{ items: DonationRow[]; total: number }>({ url: '/core/donation/index', params }),
  save: (data: Record<string, unknown>) => request.post({ url: '/core/donation/save', data }),
  update: (data: Record<string, unknown>) => request.put({ url: '/core/donation/update', data }),
  destroy: (ids: number[]) => request.del({ url: '/core/donation/destroy', data: { ids } }),
  channels: () => request.get<DonationChannel[]>({ url: '/core/donation/channels' }),
  updateChannel: (data: FormData) => request.post({ url: '/core/donation/channelUpdate', data }),
  stats: () =>
    request.get<{ supporter_count: number; total_amount: string }>({ url: '/core/donation/stats' })
}
