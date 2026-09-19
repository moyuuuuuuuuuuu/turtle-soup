/** 推理深度：difficulty(1-5) 的品牌表达，数据库仍保持 difficulty */
export type DifficultyLevel = number

export interface DepthInfo {
  level: number
  label: string
  short: string
  code: string
  tone: 'easy' | 'normal' | 'hard' | 'extreme' | 'unknown'
  color: string
}

const DEPTH_TABLE: DepthInfo[] = [
  { level: 1, label: '简单', short: '简单', code: '01', tone: 'easy', color: '#5EC4B8' },
  { level: 2, label: '普通', short: '普通', code: '02', tone: 'normal', color: 'rgba(232,244,242,0.72)' },
  { level: 3, label: '困难', short: '困难', code: '03', tone: 'hard', color: '#C9A46A' },
  { level: 4, label: '很难', short: '很难', code: '04', tone: 'hard', color: '#C9A46A' },
  { level: 5, label: '极难', short: '极难', code: '05', tone: 'extreme', color: '#D05A52' },
]

const UNKNOWN: DepthInfo = { level: 0, label: '未知', short: '未知', code: '00', tone: 'unknown', color: 'rgba(232,244,242,0.42)' }

export function resolveDepth(difficulty?: DifficultyLevel | null): DepthInfo {
  const level = Number(difficulty || 0)
  return DEPTH_TABLE.find(item => item.level === level) || UNKNOWN
}

export function difficultyLabel(difficulty?: DifficultyLevel | null): string {
  return resolveDepth(difficulty).label
}

export function depthCode(difficulty?: DifficultyLevel | null): string {
  return resolveDepth(difficulty).code
}

export function depthFraction(difficulty?: DifficultyLevel | null): string {
  return `${resolveDepth(difficulty).code} / 05`
}

/** 五段细线，表达下潜深度 */
export function depthSegments(difficulty?: DifficultyLevel | null): Array<'on' | 'off'> {
  const level = Math.min(5, Math.max(0, Number(difficulty || 0)))
  return Array.from({ length: 5 }, (_, index) => (index < level ? 'on' : 'off'))
}

/** 预估推理时长；仅在接口未返回真实时长时展示 */
export function estimateMinutes(difficulty?: DifficultyLevel | null, playCount?: number): string | null {
  const level = Number(difficulty || 0)
  if (!level)
    return null
  const base = 6 + level * 2
  const countHint = playCount && playCount > 0 ? Math.min(4, Math.round(playCount / 20)) : 0
  return `约 ${base + countHint} 分钟`
}

export function formatPlayCount(count?: number | null): string | null {
  if (!count || count <= 0)
    return null
  return `${count} 人推理过`
}

export function surfaceExcerpt(surface?: string | null, max = 48): string {
  const text = String(surface || '').replace(/\s+/g, ' ').trim()
  if (!text)
    return ''
  return text.length > max ? `${text.slice(0, max)}…` : text
}

export function tagSummary(tags?: Array<{ name: string }> | null, limit = 2): string {
  return (tags || []).slice(0, limit).map(tag => tag.name).join(' · ')
}
