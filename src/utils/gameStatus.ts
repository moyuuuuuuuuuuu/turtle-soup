/** 全站统一的推理状态表达。避免「失败」措辞。 */
export type GameStatusValue = 'created' | 'playing' | 'solved' | 'finished' | 'abandoned' | string

export interface GameStatusInfo {
  value: string
  label: string
  mark: string
  tone: 'active' | 'solved' | 'ended' | 'idle'
  color: string
}

const TABLE: Record<string, GameStatusInfo> = {
  created: { value: 'created', label: '进行中', mark: '●', tone: 'active', color: '#C9A46A' },
  playing: { value: 'playing', label: '进行中', mark: '●', tone: 'active', color: '#C9A46A' },
  solved: { value: 'solved', label: '已解开', mark: '✓', tone: 'solved', color: '#5EC4B8' },
  finished: { value: 'finished', label: '未解开', mark: '○', tone: 'ended', color: 'rgba(232,244,242,0.42)' },
  abandoned: { value: 'abandoned', label: '已结束', mark: '○', tone: 'ended', color: 'rgba(232,244,242,0.42)' },
}

export function resolveGameStatus(status?: GameStatusValue | null): GameStatusInfo {
  return TABLE[String(status || '')] || { value: String(status || ''), label: '未知', mark: '○', tone: 'idle', color: 'rgba(232,244,242,0.42)' }
}

export function isActiveStatus(status?: GameStatusValue | null): boolean {
  return resolveGameStatus(status).tone === 'active'
}

export function isSolvedStatus(status?: GameStatusValue | null): boolean {
  return resolveGameStatus(status).tone === 'solved'
}

export function formatDuration(seconds?: number | null): string {
  const total = Math.max(0, Number(seconds || 0))
  if (!total)
    return '—'
  const h = Math.floor(total / 3600)
  const m = Math.floor((total % 3600) / 60)
  const s = total % 60
  if (h > 0)
    return `${h}h ${String(m).padStart(2, '0')}m`
  return `${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`
}

export function formatClock(seconds?: number | null): string {
  return formatDuration(seconds)
}

export function formatRelativeTime(input?: string | number | Date | null): string {
  if (!input)
    return ''
  const date = input instanceof Date ? input : new Date(typeof input === 'number' ? input : String(input).replace(' ', 'T'))
  if (Number.isNaN(date.getTime()))
    return String(input)
  const now = Date.now()
  const diff = now - date.getTime()
  const minute = 60_000
  const hour = 3_600_000
  const day = 86_400_000
  if (diff < minute)
    return '刚刚'
  if (diff < hour)
    return `${Math.floor(diff / minute)} 分钟前`
  if (diff < day && date.getDate() === new Date().getDate())
    return `今天 ${String(date.getHours()).padStart(2, '0')}:${String(date.getMinutes()).padStart(2, '0')}`
  if (diff < day * 2)
    return `昨天 ${String(date.getHours()).padStart(2, '0')}:${String(date.getMinutes()).padStart(2, '0')}`
  return `${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`
}

export function ctaForStatus(status?: GameStatusValue | null): string {
  const info = resolveGameStatus(status)
  if (info.tone === 'active')
    return '继续推理 →'
  if (info.tone === 'solved')
    return '再次推理'
  return '开始推理 →'
}
