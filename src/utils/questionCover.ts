const COVERS = {
  rainy: '/static/hgt/illust/illust_rainy_night.jpg',
  fog: '/static/hgt/illust/illust_fog_forest.jpg',
  study: '/static/hgt/illust/illust_old_study.jpg',
  school: '/static/hgt/illust/illust_school_hall.jpg',
  crime: '/static/hgt/illust/illust_crime_alley.jpg',
  hospital: '/static/hgt/illust/illust_hospital_night.jpg',
  family: '/static/hgt/illust/illust_family_table.jpg',
  train: '/static/hgt/illust/illust_train.jpg',
  ferris: '/static/hgt/illust/illust_ferris_wheel.jpg',
  classroom: '/static/hgt/illust/illust_classroom.jpg',
  desk: '/static/hgt/illust/illust_desk.jpg',
  forestHouse: '/static/hgt/illust/illust_forest_house.jpg',
  shipwreck: '/static/hgt/illust/illust_shipwreck.jpg',
  ocean: '/static/hgt/bg/bg_deep_ocean.jpg',
  lighthouse: '/static/hgt/bg/bg_lighthouse.jpg',
  cave: '/static/hgt/bg/bg_underwater_cave.jpg',
  starry: '/static/hgt/bg/bg_starry.jpg',
  light: '/static/hgt/bg/bg_light.jpg',
} as const

const ALL_COVERS = Object.values(COVERS)

// Soft thematic preference: first match seeds a rotated pool, then id-hash picks
// inside that pool so one screen of similar tags still gets distinct covers.
const TAG_COVER_GROUPS: Array<{ names: string[], covers: string[] }> = [
  { names: ['本格', '新本格', '文字诡计'], covers: [COVERS.study, COVERS.desk, COVERS.lighthouse, COVERS.rainy, COVERS.ocean, COVERS.crime, COVERS.fog, COVERS.school, COVERS.hospital, COVERS.family, COVERS.train, COVERS.classroom] },
  { names: ['变格', '超自然', '黑汤'], covers: [COVERS.fog, COVERS.cave, COVERS.ocean, COVERS.crime, COVERS.rainy, COVERS.study, COVERS.lighthouse, COVERS.shipwreck, COVERS.starry, COVERS.forestHouse, COVERS.school, COVERS.hospital] },
  { names: ['犯罪案件', '身份误导', '红汤'], covers: [COVERS.crime, COVERS.rainy, COVERS.hospital, COVERS.fog, COVERS.study, COVERS.train, COVERS.ocean, COVERS.lighthouse, COVERS.desk, COVERS.school, COVERS.forestHouse] },
  { names: ['校园'], covers: [COVERS.classroom, COVERS.school, COVERS.rainy, COVERS.study, COVERS.lighthouse, COVERS.family, COVERS.ocean, COVERS.crime, COVERS.fog, COVERS.desk] },
  { names: ['家庭'], covers: [COVERS.family, COVERS.lighthouse, COVERS.rainy, COVERS.hospital, COVERS.ocean, COVERS.study, COVERS.school, COVERS.forestHouse, COVERS.desk, COVERS.crime] },
  { names: ['医疗'], covers: [COVERS.hospital, COVERS.family, COVERS.fog, COVERS.school, COVERS.rainy, COVERS.study, COVERS.ocean, COVERS.lighthouse, COVERS.crime, COVERS.desk] },
  { names: ['清汤', '短篇', '一句话汤'], covers: [COVERS.lighthouse, COVERS.light, COVERS.ocean, COVERS.family, COVERS.rainy, COVERS.study, COVERS.school, COVERS.crime, COVERS.fog, COVERS.starry] },
  { names: ['旅行', '列车', '交通'], covers: [COVERS.train, COVERS.ferris, COVERS.lighthouse, COVERS.rainy, COVERS.ocean, COVERS.starry, COVERS.study] },
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

export const coverPlaceholderUrl = '/static/hgt/cover/cover_placeholder.png'
export const emptyNetworkUrl = '/static/hgt/empty/empty_network.png'
export const emptyLoadingUrl = '/static/hgt/empty/empty_loading.png'
export const emptySearchUrl = '/static/hgt/empty/empty_search.png'
export const emptyNoneUrl = '/static/hgt/empty/empty_none.png'
export const emptyHistoryUrl = '/static/hgt/empty/empty_history.png'
export const paperTagUrl = '/static/hgt/paper/paper_tag.png'
