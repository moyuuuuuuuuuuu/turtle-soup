import type { GameHistoryResult } from '@/types/game'

/** Read the same authoritative duration used by the history list. */
export async function readGameDuration(id: string, history: (params: { page: number, page_size: number }) => Promise<GameHistoryResult>): Promise<number | null> {
  let page = 1
  while (true) {
    const result = await history({ page, page_size: 100 })
    const record = result.items.find(item => item.id === id)
    if (record)
      return typeof record.duration_seconds === 'number' && Number.isFinite(record.duration_seconds) && record.duration_seconds >= 0 ? record.duration_seconds : null
    if (!result.items.length || page >= result.pagination.total_pages)
      return null
    page++
  }
}
