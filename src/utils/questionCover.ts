const COVERS = {
  rainy: '/static/hgt/illust/illust_rainy_night.jpg',
  fog: '/static/hgt/illust/illust_fog_forest.jpg',
  study: '/static/hgt/illust/illust_old_study.jpg',
  school: '/static/hgt/illust/illust_school_hall.jpg',
  crime: '/static/hgt/illust/illust_crime_alley.jpg',
  hospital: '/static/hgt/illust/illust_hospital_night.jpg',
  family: '/static/hgt/illust/illust_family_table.jpg',
  ocean: '/static/hgt/bg/bg_deep_ocean.jpg',
  lighthouse: '/static/hgt/bg/bg_lighthouse.jpg',
} as const

const ALL_COVERS = Object.values(COVERS)

// Soft thematic preference: first match seeds a rotated pool, then id-hash picks
// inside that pool so one screen of similar tags still gets distinct covers.
const TAG_COVER_GROUPS: Array<{ names: string[], covers: string[] }> = [
  { names: ['本格', '新本格', '文字诡计'], covers: [COVERS.study, COVERS.lighthouse, COVERS.rainy, COVERS.ocean, COVERS.crime, COVERS.fog, COVERS.school, COVERS.hospital, COVERS.family] },
  { names: ['变格', '超自然', '黑汤'], covers: [COVERS.fog, COVERS.ocean, COVERS.crime, COVERS.rainy, COVERS.study, COVERS.lighthouse, COVERS.school, COVERS.hospital, COVERS.family] },
  { names: ['犯罪案件', '身份误导', '红汤'], covers: [COVERS.crime, COVERS.rainy, COVERS.hospital, COVERS.fog, COVERS.study, COVERS.ocean, COVERS.lighthouse, COVERS.school, COVERS.family] },
  { names: ['校园'], covers: [COVERS.school, COVERS.rainy, COVERS.study, COVERS.lighthouse, COVERS.family, COVERS.ocean, COVERS.crime, COVERS.fog, COVERS.hospital] },
  { names: ['家庭'], covers: [COVERS.family, COVERS.lighthouse, COVERS.rainy, COVERS.hospital, COVERS.ocean, COVERS.study, COVERS.school, COVERS.crime, COVERS.fog] },
  { names: ['医疗'], covers: [COVERS.hospital, COVERS.family, COVERS.fog, COVERS.school, COVERS.rainy, COVERS.study, COVERS.ocean, COVERS.lighthouse, COVERS.crime] },
  { names: ['清汤', '短篇', '一句话汤'], covers: [COVERS.lighthouse, COVERS.ocean, COVERS.family, COVERS.rainy, COVERS.study, COVERS.school, COVERS.crime, COVERS.fog, COVERS.hospital] },
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
