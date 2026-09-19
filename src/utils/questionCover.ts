/** 水墨封面池：仅使用 ink 目录，保证与主题一致 */
const INK = '/static/hgt/ink'

const COVERS = {
  catLantern: `${INK}/cover_cat_lantern.png`,
  landscape: `${INK}/hero_ink_landscape.png`,
  bus: `${INK}/cover_bus.png`,
  room: `${INK}/cover_empty_room.png`,
  island: `${INK}/cover_island.png`,
  sunflower: `${INK}/cover_sunflower.png`,
  study: `${INK}/cover_study.png`,
  fog: `${INK}/cover_fog.png`,
  rainy: `${INK}/cover_rainy.png`,
  classroom: `${INK}/cover_classroom.png`,
  family: `${INK}/cover_family.png`,
  hospital: `${INK}/cover_hospital.png`,
  train: `${INK}/cover_train.png`,
  crime: `${INK}/cover_crime.png`,
} as const

const ALL_COVERS = [
  COVERS.bus,
  COVERS.room,
  COVERS.island,
  COVERS.sunflower,
  COVERS.study,
  COVERS.fog,
  COVERS.rainy,
  COVERS.classroom,
  COVERS.family,
  COVERS.hospital,
  COVERS.train,
  COVERS.crime,
  COVERS.landscape,
  COVERS.catLantern,
]

// Soft thematic preference: first match seeds a rotated pool, then id-hash picks
// inside that pool so one screen of similar tags still gets distinct covers.
const TAG_COVER_GROUPS: Array<{ names: string[], covers: string[] }> = [
  { names: ['本格', '新本格', '文字诡计'], covers: [COVERS.study, COVERS.crime, COVERS.rainy, COVERS.room, COVERS.train, COVERS.classroom, COVERS.catLantern, COVERS.family] },
  { names: ['变格', '超自然', '黑汤'], covers: [COVERS.fog, COVERS.island, COVERS.catLantern, COVERS.rainy, COVERS.study, COVERS.room, COVERS.hospital] },
  { names: ['犯罪案件', '身份误导', '红汤'], covers: [COVERS.crime, COVERS.rainy, COVERS.hospital, COVERS.bus, COVERS.fog, COVERS.study, COVERS.train] },
  { names: ['校园'], covers: [COVERS.classroom, COVERS.room, COVERS.study, COVERS.rainy, COVERS.family, COVERS.catLantern] },
  { names: ['家庭'], covers: [COVERS.family, COVERS.sunflower, COVERS.room, COVERS.hospital, COVERS.study, COVERS.catLantern] },
  { names: ['医疗'], covers: [COVERS.hospital, COVERS.family, COVERS.fog, COVERS.room, COVERS.study] },
  { names: ['清汤', '短篇', '一句话汤'], covers: [COVERS.sunflower, COVERS.room, COVERS.family, COVERS.catLantern, COVERS.study, COVERS.bus] },
  { names: ['旅行', '列车', '交通'], covers: [COVERS.train, COVERS.island, COVERS.bus, COVERS.rainy, COVERS.catLantern] },
  { names: ['逻辑推理', '悬疑'], covers: ALL_COVERS },
]

function hashId(id: string) {
  let hash = 0
  for (let i = 0; i < id.length; i++)
    hash = (hash * 31 + id.charCodeAt(i)) | 0
  return Math.abs(hash)
}

export interface CoverSource {
  id?: string
  difficulty?: number
  tags?: Array<{ name: string }>
}

export function questionCoverUrl(question?: CoverSource | null) {
  const id = question?.id || ''
  const tagNames = new Set((question?.tags || []).map(tag => tag.name))

  for (const group of TAG_COVER_GROUPS) {
    if (group.names.some(name => tagNames.has(name)))
      return group.covers[hashId(id) % group.covers.length]
  }

  return ALL_COVERS[hashId(id) % ALL_COVERS.length]
}

/** 按题目/对局 id 轮换纸纹，避免全站同一张 paper_01 */
export function paperTextureUrl(seed?: string | null) {
  const papers = [
    '/static/hgt/paper/paper_01.png',
    '/static/hgt/paper/paper_02.png',
    '/static/hgt/paper/paper_03.png',
    '/static/hgt/paper/paper_04.png',
  ]
  return papers[hashId(String(seed || 'default')) % papers.length]
}

export const coverPlaceholderUrl = '/static/hgt/ink/cover_cat_lantern.png'
export const emptyNetworkUrl = '/static/hgt/empty/empty_network.png'
/** 首选 HgtLoading 组件；此 URL 仅作兼容占位（品牌 logo） */
export const emptyLoadingUrl = '/static/brand/logo-mark-light.png'
export const emptySearchUrl = '/static/hgt/empty/empty_search.png'
export const emptyNoneUrl = '/static/hgt/empty/empty_none.png'
export const emptyHistoryUrl = '/static/hgt/empty/empty_history.png'
export const paperTagUrl = '/static/hgt/paper/paper_tag.png'
