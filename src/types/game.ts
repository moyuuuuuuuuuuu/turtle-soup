export interface PublicQuestion {
  id: string
  title: string
  surface: string
  difficulty: number
  language: string
  risk_level: 'safe' | 'caution'
  risk_warning?: string | null
  tags: Array<{ id: number, name: string }>
  play_count: number
}

export interface HomeStats { question_count: number, today_online: number, success_rate: number, average_duration_seconds: number | null }

export interface GameMessage { sequence: number, user_id?: number | null, username?: string | null, avatar_url?: string | null, role: 'player' | 'host', type: string, content: string, metadata?: Record<string, unknown> }
export interface GameSnapshot { id: string, question_id?: string | null, mode: 'single' | 'multiplayer', room_id?: string | null, status: 'created' | 'playing' | 'solved' | 'finished' | 'abandoned', difficulty: number, question_limit: number, question_count: number, remaining_questions: number, hint_count: number, title: string, surface: string, risk_level: string, messages: GameMessage[], used_hints: number[], discovered_points: string[], bottom?: string | null, points?: Array<{ key: string, content: string, required: boolean, weight: number }> | null, guess?: { content: string, is_solved: boolean, summary: string } | null }
export interface RoomMember { user_id: number, username: string, avatar_url?: string | null, role: 'owner' | 'member', is_ready: boolean, is_self: boolean, is_muted?: boolean }
export interface RoomMessage { sequence: number, user_id: number, username: string, avatar_url?: string | null, content: string, create_time: string }
export interface RoomSnapshot { id: string, invite_code: string, name: string, status: 'waiting' | 'playing' | 'finished' | 'closed', visibility: 'private' | 'public', max_players: number, member_count: number, owner_user_id: number, is_owner: boolean, question_id?: string | null, question?: PublicQuestion | null, game_id?: string | null, members: RoomMember[], messages: RoomMessage[], create_time: string }
export interface DonationPage { channels: Array<{ method: 'wechat' | 'alipay', name: string, qr_code_url: string }>, recent_donations: Array<{ id: string, donor_name: string, amount: string, method?: string | null, message?: string | null, donated_at: string }>, supporter_count: number }
export interface ApiEnvelope<T> { code: string, message: string, data: T, request_id: string, timestamp: number }
