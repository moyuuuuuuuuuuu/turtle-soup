<script setup lang="ts">
import type { GameHistoryItem, GameHistoryStats } from '@/types/game'
import { ensureAnonymousSession, gameApi } from '@/api/turtle'
import { usePlayerStore } from '@/store/playerStore'
import { difficultyLabel, tagSummary } from '@/utils/depth'
import { formatDuration, formatRelativeTime, isActiveStatus, resolveGameStatus } from '@/utils/gameStatus'

definePage({ name: 'history', layout: 'tabbar', style: { 'navigationStyle': 'custom', 'mp-toutiao': { navigationStyle: 'default' } } })

const router = useRouter()
const player = usePlayerStore()

const PAGE_SIZE = 20

type HistoryFilter = 'all' | 'playing' | 'solved' | 'unsolved'

const filterTabs: Array<{ key: HistoryFilter, label: string }> = [
  { key: 'all', label: '全部' },
  { key: 'playing', label: '进行中' },
  { key: 'solved', label: '已解开' },
  { key: 'unsolved', label: '未解开' },
]

const items = ref<GameHistoryItem[]>([])
const continueItems = ref<GameHistoryItem[]>([])
const stats = ref<GameHistoryStats | null>(null)
const filter = ref<HistoryFilter>('all')
const keyword = ref('')
const sortKey = ref<'update' | 'create'>('update')
const page = ref(1)
const total = ref(0)
const loading = ref(true)
const listLoading = ref(false)
const loadError = ref('')
const loggedIn = ref(false)

const hasContinue = computed(() => continueItems.value.length > 0)

const displayItems = computed(() => {
  const kw = keyword.value.trim().toLowerCase()
  let list = items.value
  if (kw) {
    list = list.filter((item) => {
      const title = String(item.title || '').toLowerCase()
      const surface = String(item.surface || '').toLowerCase()
      const tags = (item.tags || []).map(tag => String(tag.name || '').toLowerCase())
      return title.includes(kw) || surface.includes(kw) || tags.some(tag => tag.includes(kw))
    })
  }
  const timeKey = sortKey.value === 'create' ? 'create_time' : 'update_time'
  return [...list].sort((left, right) => {
    const l = new Date(String(left[timeKey] || 0).replace(' ', 'T')).getTime() || 0
    const r = new Date(String(right[timeKey] || 0).replace(' ', 'T')).getTime() || 0
    return r - l
  })
})

const hasMore = computed(() => items.value.length < total.value)
const isEmpty = computed(() => !listLoading.value && !displayItems.value.length)

const statusMeta = (status: string) => resolveGameStatus(status)

function itemMetaLine(item: GameHistoryItem) {
  const parts = [difficultyLabel(item.difficulty)]
  const tags = tagSummary(item.tags, 2)
  if (tags)
    parts.push(tags)
  return parts.join(' · ')
}

function itemCountLine(item: GameHistoryItem) {
  const count = `${item.question_count || 0} 次提问`
  const duration = formatDuration(item.duration_seconds)
  return duration && duration !== '—' ? `${count} · ${duration}` : count
}

function openRecord(item: GameHistoryItem) {
  if (isActiveStatus(item.status)) {
    router.push({ name: 'game', params: { id: item.id } })
    return
  }
  router.push({ name: 'game', params: { id: item.id }, query: { mode: 'readonly' } })
}

function goLibrary() {
  router.push({ name: 'questions' })
}

function goLogin() {
  router.replace({ name: 'player-login', query: { redirect: '/pages/history/index' } })
}

function toggleSort() {
  sortKey.value = sortKey.value === 'update' ? 'create' : 'update'
}

function changeFilter(next: HistoryFilter) {
  if (filter.value === next)
    return
  filter.value = next
  void loadList(true)
}

function historyParams(reset: boolean) {
  const params: { status?: string, page: number, page_size: number, continue_only?: boolean } = {
    page: reset ? 1 : page.value,
    page_size: PAGE_SIZE,
  }
  if (filter.value === 'playing')
    params.continue_only = true
  else if (filter.value === 'solved')
    params.status = 'solved'
  else if (filter.value === 'unsolved')
    params.status = 'finished'
  return params
}

async function loadContinue() {
  try {
    const result = await gameApi.history({ continue_only: true, page: 1, page_size: 10 })
    continueItems.value = (result.items || [])
      .filter(item => isActiveStatus(item.status))
      .slice(0, 3)
  }
  catch {
    continueItems.value = []
  }
}

async function loadList(reset = false) {
  if (listLoading.value && !reset)
    return
  listLoading.value = true
  loadError.value = ''
  try {
    const params = historyParams(reset)
    const result = await gameApi.history(params)
    const nextItems = result.items || []
    items.value = reset ? nextItems : [...items.value, ...nextItems]
    stats.value = result.stats || stats.value
    total.value = result.pagination?.total ?? items.value.length
    const fetchedPage = params.page
    page.value = nextItems.length ? fetchedPage + 1 : fetchedPage
  }
  catch (error) {
    loadError.value = (error as Error).message || '推理履历加载失败'
  }
  finally {
    listLoading.value = false
  }
}

function loadMore() {
  if (!listLoading.value && hasMore.value)
    void loadList(false)
}

onReachBottom(loadMore)

onMounted(async () => {
  try {
    await player.restore()
    if (!player.user) {
      router.replace({ name: 'player-login', query: { redirect: '/pages/history/index' } })
      return
    }
    loggedIn.value = true
    await ensureAnonymousSession()
    await Promise.all([loadContinue(), loadList(true)])
  }
  finally {
    loading.value = false
  }
})
</script>

<template>
  <view class="history-page">
    <template v-if="!loggedIn && !loading">
      <view class="empty-state">
        <text class="empty-mark">
          ◇
        </text>
        <text class="empty-title">
          需要登录后查看推理履历
        </text>
        <button class="btn-primary empty-action" @click="goLogin">
          去登录
        </button>
      </view>
    </template>
    <template v-else>
      <view class="page-head">
        <text class="title">
          我的推理
        </text>
        <text class="subtitle">
          查看正在进行和已经完成的谜题。
        </text>
      </view>

      <view v-if="loading" class="loading-line">
        正在读取推理履历…
      </view>

      <template v-else>
        <section v-if="hasContinue" class="continue-section">
          <text class="section-label">
            继续推理
          </text>
          <view
            v-for="item in continueItems"
            :key="item.id"
            class="continue-row"
            role="button"
            @click="openRecord(item)"
          >
            <view class="continue-main">
              <view class="continue-title-row">
                <text class="status-dot" :style="{ color: statusMeta(item.status).color }">
                  {{ statusMeta(item.status).mark }}
                </text>
                <text class="continue-title">
                  {{ item.title }}
                </text>
              </view>
              <text class="continue-meta">
                {{ itemMetaLine(item) }}
              </text>
              <text class="continue-meta soft">
                上次推理：{{ formatRelativeTime(item.update_time) || '—' }} · 已提问 {{ item.question_count || 0 }} 次
              </text>
            </view>
            <text class="continue-cta">
              继续推理 →
            </text>
          </view>
        </section>

        <section v-if="stats" class="resume-stats">
          <text class="section-label">
            推理履历
          </text>
          <view class="stats-row">
            <view class="stat-cell">
              <text class="stat-value">
                {{ stats.played ?? 0 }}
              </text>
              <text class="stat-label">
                玩过的谜题
              </text>
            </view>
            <view class="stat-cell">
              <text class="stat-value">
                {{ stats.solved ?? 0 }}
              </text>
              <text class="stat-label">
                已解开
              </text>
            </view>
            <view class="stat-cell">
              <text class="stat-value">
                {{ stats.total_questions ?? 0 }}
              </text>
              <text class="stat-label">
                累计提问
              </text>
            </view>
            <view class="stat-cell">
              <text class="stat-value">
                {{ formatDuration(stats.total_duration_seconds) }}
              </text>
              <text class="stat-label">
                推理时间
              </text>
            </view>
          </view>
        </section>

        <section class="history-section">
          <text class="section-label">
            历史记录
          </text>

          <view class="toolbar">
            <view class="status-tabs">
              <button
                v-for="tab in filterTabs"
                :key="tab.key"
                class="status-tab"
                :class="{ active: filter === tab.key }"
                @click="changeFilter(tab.key)"
              >
                {{ tab.label }}
              </button>
            </view>
            <view class="toolbar-right">
              <input
                v-model="keyword"
                class="search-input"
                type="text"
                placeholder="搜索玩过的谜题"
                confirm-type="search"
              >
              <button class="sort-btn" @click="toggleSort">
                {{ sortKey === 'update' ? '最近推理' : '最近游玩' }}⌄
              </button>
            </view>
          </view>

          <view v-if="loadError" class="error-line">
            {{ loadError }}
            <button class="text-btn" @click="loadList(true)">
              重试
            </button>
          </view>

          <view v-else-if="isEmpty" class="empty-state">
            <text class="empty-mark">
              ◇
            </text>
            <text class="empty-title">
              还没有推理记录
            </text>
            <text class="empty-copy">
              选一碗汤，开始你的第一次推理。
            </text>
            <button class="btn-primary empty-action" @click="goLibrary">
              去题库看看 →
            </button>
          </view>

          <view v-else class="record-list">
            <view
              v-for="item in displayItems"
              :key="item.id"
              class="record-row"
              role="button"
              @click="openRecord(item)"
            >
              <view class="record-main">
                <view class="record-title-row">
                  <text class="record-mark" :style="{ color: statusMeta(item.status).color }">
                    {{ statusMeta(item.status).mark }}
                  </text>
                  <text class="record-title">
                    {{ item.title }}
                  </text>
                </view>
                <text class="record-meta">
                  {{ itemMetaLine(item) }}
                </text>
                <text class="record-meta soft">
                  {{ itemCountLine(item) }}
                </text>
              </view>
              <view class="record-side">
                <text class="record-status" :style="{ color: statusMeta(item.status).color }">
                  {{ statusMeta(item.status).label }}
                </text>
                <text class="record-time">
                  {{ formatRelativeTime(item.update_time) || formatRelativeTime(item.create_time) }}
                </text>
                <text class="record-arrow">
                  →
                </text>
              </view>
            </view>
          </view>

          <view v-if="hasMore && !isEmpty" class="load-more">
            <button class="load-more-btn" :disabled="listLoading" @click="loadMore">
              {{ listLoading ? '加载中…' : '显示更多' }}
            </button>
          </view>
        </section>
      </template>
    </template>
  </view>
</template>

<style scoped>
.history-page {
  min-height: 100%;
  padding-bottom: calc(48px + env(safe-area-inset-bottom));
  background: var(--hgt-bg);
  color: var(--hgt-text);
}

.page-head {
  display: flex;
  box-sizing: border-box;
  width: min(960px, 100%);
  margin: 0 auto;
  padding: 36px 32px 16px;
  gap: 8px;
  flex-direction: column;
}

.title {
  color: var(--hgt-text);
  font-family: var(--hgt-font-display);
  font-size: 28px;
  font-weight: 600;
  letter-spacing: 0.06em;
}

.subtitle {
  color: var(--hgt-text-2);
  font-size: 13px;
  line-height: 1.6;
}

.section-label {
  display: block;
  margin-bottom: 12px;
  color: var(--hgt-text-3);
  font-family: var(--hgt-font-mono);
  font-size: 11px;
  letter-spacing: 0.2em;
}

.loading-line {
  width: min(960px, 100%);
  margin: 0 auto;
  padding: 48px 32px;
  color: var(--hgt-text-3);
  font-size: 13px;
}

/* Continue */
.continue-section {
  box-sizing: border-box;
  width: min(960px, 100%);
  margin: 0 auto;
  padding: 4px 32px 20px;
}

.continue-row {
  display: flex;
  padding: 16px 0;
  border-bottom: 1px solid var(--hgt-border);
  align-items: flex-end;
  justify-content: space-between;
  gap: 16px;
  cursor: pointer;
}

.continue-row:first-of-type {
  border-top: 1px solid var(--hgt-border);
}

.continue-main {
  display: flex;
  min-width: 0;
  gap: 6px;
  flex-direction: column;
}

.continue-title-row {
  display: flex;
  align-items: center;
  gap: 8px;
}

.status-dot,
.record-mark {
  font-family: var(--hgt-font-mono);
  font-size: 12px;
  line-height: 1;
}

.continue-title {
  color: var(--hgt-text-bright);
  font-family: var(--hgt-font-display);
  font-size: 16px;
  font-weight: 600;
}

.continue-meta,
.record-meta {
  color: var(--hgt-text-2);
  font-size: 12px;
  line-height: 1.5;
}

.continue-meta.soft,
.record-meta.soft {
  color: var(--hgt-text-3);
}

.continue-cta {
  flex: none;
  color: var(--hgt-brand);
  font-family: var(--hgt-font-mono);
  font-size: 12px;
  letter-spacing: 0.04em;
}

/* Stats — unframed numbers + thin dividers */
.resume-stats {
  box-sizing: border-box;
  width: min(960px, 100%);
  margin: 0 auto;
  padding: 4px 32px 24px;
}

.stats-row {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  border-top: 1px solid var(--hgt-border);
  border-bottom: 1px solid var(--hgt-border);
}

.stat-cell {
  display: flex;
  padding: 20px 16px;
  gap: 8px;
  flex-direction: column;
  border-right: 1px solid var(--hgt-border);
}

.stat-cell:first-child {
  padding-left: 0;
}

.stat-cell:last-child {
  border-right: 0;
  padding-right: 0;
}

.stat-value {
  color: var(--hgt-text);
  font-family: var(--hgt-font-display);
  font-size: 28px;
  font-weight: 600;
  letter-spacing: 0.02em;
}

.stat-label {
  color: var(--hgt-text-3);
  font-size: 12px;
  letter-spacing: 0.08em;
}

/* History list */
.history-section {
  box-sizing: border-box;
  width: min(960px, 100%);
  margin: 0 auto;
  padding: 4px 32px 24px;
}

.toolbar {
  display: flex;
  margin-bottom: 8px;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  flex-wrap: wrap;
}

.status-tabs {
  display: flex;
  gap: 4px;
}

.status-tab {
  position: relative;
  height: 36px;
  margin: 0;
  padding: 0 12px;
  border: 0;
  background: transparent;
  color: var(--hgt-text-3);
  font-size: 13px;
  line-height: 36px;
}

.status-tab::after {
  border: 0;
}

.status-tab.active {
  color: var(--hgt-text);
}

.status-tab.active::after {
  position: absolute;
  right: 8px;
  bottom: 0;
  left: 8px;
  height: 2px;
  background: var(--hgt-brand);
  content: '';
}

.toolbar-right {
  display: flex;
  align-items: center;
  gap: 10px;
}

.search-input {
  box-sizing: border-box;
  width: min(220px, 42vw);
  height: 34px;
  padding: 0 12px;
  border: 1px solid var(--hgt-border);
  border-radius: var(--hgt-radius-sm);
  background: transparent;
  color: var(--hgt-text);
  font-size: 12px;
}

.sort-btn {
  height: 34px;
  margin: 0;
  padding: 0 8px;
  border: 0;
  background: transparent;
  color: var(--hgt-text-2);
  font-size: 12px;
}

.sort-btn::after {
  border: 0;
}

.record-list {
  display: flex;
  flex-direction: column;
}

.record-row {
  display: flex;
  padding: 16px 0;
  border-bottom: 1px solid var(--hgt-border);
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
  cursor: pointer;
}

.record-row:first-child {
  border-top: 1px solid var(--hgt-border);
}

.record-main {
  display: flex;
  min-width: 0;
  gap: 6px;
  flex-direction: column;
}

.record-title-row {
  display: flex;
  align-items: center;
  gap: 8px;
}

.record-title {
  overflow: hidden;
  color: var(--hgt-text);
  font-family: var(--hgt-font-display);
  font-size: 15px;
  font-weight: 600;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.record-side {
  display: flex;
  flex: none;
  align-items: flex-end;
  gap: 8px;
  flex-direction: column;
}

.record-status {
  font-family: var(--hgt-font-mono);
  font-size: 12px;
}

.record-time {
  color: var(--hgt-text-3);
  font-size: 12px;
}

.record-arrow {
  color: var(--hgt-text-3);
  font-family: var(--hgt-font-mono);
  font-size: 14px;
}

.load-more {
  display: flex;
  padding: 20px 0 8px;
  justify-content: center;
}

.load-more-btn,
.text-btn,
.btn-primary,
.empty-action {
  margin: 0;
}

.load-more-btn {
  min-width: 140px;
  height: 40px;
  padding: 0 18px;
  border: 1px solid var(--hgt-border);
  border-radius: var(--hgt-radius-sm);
  background: transparent;
  color: var(--hgt-text-2);
  font-size: 13px;
}

.load-more-btn::after,
.text-btn::after,
.btn-primary::after,
.empty-action::after {
  border: 0;
}

.load-more-btn:disabled {
  opacity: 0.55;
}

.error-line {
  display: flex;
  padding: 24px 0;
  align-items: center;
  gap: 12px;
  color: var(--hgt-danger);
  font-size: 13px;
}

.text-btn {
  height: 28px;
  margin: 0;
  padding: 0 8px;
  border: 0;
  background: transparent;
  color: var(--hgt-brand);
  font-size: 12px;
}

.empty-state {
  display: flex;
  min-height: 220px;
  padding: 32px 0;
  align-items: center;
  justify-content: center;
  gap: 10px;
  flex-direction: column;
  text-align: center;
}

.empty-mark {
  color: var(--hgt-brand);
  font-family: var(--hgt-font-mono);
  font-size: 20px;
}

.empty-title {
  color: var(--hgt-text);
  font-family: var(--hgt-font-display);
  font-size: 18px;
  font-weight: 600;
}

.empty-copy {
  color: var(--hgt-text-2);
  font-size: 13px;
}

.btn-primary,
.empty-action {
  display: flex;
  height: 40px;
  margin-top: 8px;
  padding: 0 18px;
  border: 0;
  border-radius: var(--hgt-radius-sm);
  align-items: center;
  justify-content: center;
  background: var(--hgt-brand);
  color: var(--hgt-on-brand);
  font-size: 13px;
}

@media (max-width: 767px) {
  .page-head,
  .continue-section,
  .resume-stats,
  .history-section,
  .loading-line {
    padding-right: 16px;
    padding-left: 16px;
  }

  .stats-row {
    grid-template-columns: repeat(2, 1fr);
  }

  .stat-cell {
    padding: 16px 12px;
  }

  .stat-cell:nth-child(2n) {
    border-right: 0;
    padding-right: 0;
  }

  .stat-cell:nth-child(2n + 1) {
    padding-left: 0;
  }

  .stat-cell:nth-child(-n + 2) {
    border-bottom: 1px solid var(--hgt-border);
  }

  .stat-value {
    font-size: 22px;
  }

  .toolbar {
    align-items: stretch;
    flex-direction: column;
  }

  .status-tabs {
    overflow-x: auto;
  }

  .toolbar-right {
    width: 100%;
  }

  .search-input {
    flex: 1;
    width: auto;
  }

  .record-side {
    align-items: flex-end;
  }
}
</style>
