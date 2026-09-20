<script setup lang="ts">
import type { PublicQuestion, PublicTag } from '@/types/game'
import { ensureAnonymousSession, questionApi, tagApi } from '@/api/turtle'
import QuestionTextCard from '@/components/QuestionTextCard.vue'
import { difficultyLabel } from '@/utils/depth'
import { openQuestionDetail } from '@/utils/questionRoute'

definePage({ name: 'questions', layout: 'tabbar', style: { 'navigationStyle': 'custom', 'mp-toutiao': { navigationStyle: 'default' } } })

const route = useRoute()
const roomId = computed(() => String(route.query.room_id || ''))

interface CategoryTab { label: string, tagId?: number }

/** 主分类下划线标签；tagId 与后端 turtle_tags 对齐 */
const PRIMARY_TABS: CategoryTab[] = [
  { label: '全部' },
  { label: '悬疑', tagId: 3 },
  { label: '逻辑推理', tagId: 4 },
  { label: '本格', tagId: 20 },
  { label: '清汤', tagId: 17 },
]

const DIFFICULTY_OPTIONS: Array<{ label: string, value: number | undefined }> = [
  { label: '不限', value: undefined },
  { label: '简单', value: 1 },
  { label: '普通', value: 2 },
  { label: '困难', value: 3 },
  { label: '很难', value: 4 },
  { label: '极难', value: 5 },
]

type SortKey = 'default' | 'plays_desc' | 'diff_asc' | 'diff_desc'

const SORT_OPTIONS: Array<{ key: SortKey, label: string }> = [
  { key: 'default', label: '默认排序' },
  { key: 'plays_desc', label: '最多推理' },
  { key: 'diff_asc', label: '难度从低到高' },
  { key: 'diff_desc', label: '难度从高到低' },
]

const items = ref<PublicQuestion[]>([])
const keyword = ref('')
const difficulty = ref<number | undefined>(undefined)
const activeTagId = ref<number | undefined>(undefined)
const activeTabLabel = ref('全部')
const filtersVisible = ref(false)
const sortKey = ref<SortKey>('default')
const sortOpen = ref(false)
const loading = ref(true)
const loadError = ref(false)
const page = ref(1)
const total = ref(0)
const categories = ref<CategoryTab[]>(PRIMARY_TABS)
let keywordSearchTimer: ReturnType<typeof setTimeout> | null = null

function resolvePageSize() {
  // #ifdef H5
  return typeof window !== 'undefined' && window.matchMedia('(max-width: 767px)').matches ? 12 : 24
  // #endif
  // #ifndef H5
  return 12
  // #endif
}

const pageSize = resolvePageSize()
const totalPages = computed(() => Math.max(1, Math.ceil(total.value / pageSize)))
const headerCountText = computed(() => {
  if (total.value > 0)
    return `${total.value} 个等待被解开的故事。`
  return '题库在故事表面之下，寻找不合常理的真相。'
})
const resultLine = computed(() => {
  if (loading.value && !items.value.length)
    return ''
  return `${total.value} 个谜题`
})
const sortLabel = computed(() => SORT_OPTIONS.find(item => item.key === sortKey.value)?.label || '默认排序')
const activeTagLabel = computed(() => {
  if (activeTagId.value === undefined)
    return ''
  return activeTabLabel.value
})
const hasActiveFilter = computed(() =>
  activeTagId.value !== undefined
  || difficulty.value !== undefined
  || Boolean(keyword.value.trim()),
)
const SORT_API_MAP: Record<string, string> = {
  default: '',
  plays_desc: 'popular',
  diff_asc: 'difficulty_asc',
  diff_desc: 'difficulty_desc',
}

const displayItems = computed(() => items.value)
const pageList = computed(() => {
  const current = page.value
  const last = totalPages.value
  if (last <= 7)
    return Array.from({ length: last }, (_, i) => i + 1)
  const pages = new Set<number>([1, last, current, current - 1, current + 1])
  if (current <= 3)
    [2, 3, 4].forEach(p => pages.add(p))
  if (current >= last - 2)
    [last - 1, last - 2, last - 3].forEach(p => pages.add(p))
  return [...pages].filter(p => p >= 1 && p <= last).sort((a, b) => a - b)
})

async function loadCategories() {
  try {
    const result = await tagApi.list()
    if (!result.items?.length)
      return
    const byName = new Map(result.items.map((tag: PublicTag) => [tag.name, tag.id]))
    categories.value = [
      { label: '全部' },
      ...PRIMARY_TABS.slice(1).map(tab => ({
        label: tab.label,
        tagId: byName.get(tab.label) ?? tab.tagId,
      })),
    ]
  }
  catch {
    // 保留内置分类，筛选仍可用
  }
}

async function load(reset = false) {
  if (reset) {
    page.value = 1
  }
  loading.value = true
  loadError.value = false
  try {
    await ensureAnonymousSession()
    const trimmedKeyword = keyword.value.trim()
    const sortParam = SORT_API_MAP[sortKey.value]
    const result = await questionApi.list({
      ...(difficulty.value !== undefined ? { difficulty: difficulty.value } : {}),
      ...(activeTagId.value !== undefined ? { tag_id: activeTagId.value } : {}),
      ...(trimmedKeyword ? { keyword: trimmedKeyword } : {}),
      ...(sortParam ? { sort: sortParam } : {}),
      page: page.value,
      page_size: pageSize,
    })
    items.value = result.items || []
    const pagination = result.pagination || {}
    total.value = Number(pagination.total) || items.value.length
  }
  catch {
    loadError.value = true
    if (reset) {
      items.value = []
      total.value = 0
    }
  }
  finally {
    loading.value = false
  }
}

function selectTab(tab: CategoryTab) {
  activeTabLabel.value = tab.label
  activeTagId.value = tab.tagId
  void load(true)
}

function setDifficulty(value: number | undefined) {
  difficulty.value = value
  void load(true)
}

function onKeywordInput() {
  if (keywordSearchTimer)
    clearTimeout(keywordSearchTimer)
  keywordSearchTimer = setTimeout(() => {
    keywordSearchTimer = null
    void load(true)
  }, 300)
}

function clearKeyword() {
  keyword.value = ''
  void load(true)
}

function clearAllFilters() {
  keyword.value = ''
  difficulty.value = undefined
  activeTagId.value = undefined
  activeTabLabel.value = '全部'
  void load(true)
}

function goPage(next: number) {
  const target = Math.min(totalPages.value, Math.max(1, next))
  if (target === page.value && !loadError.value)
    return
  page.value = target
  void load()
}

function setSort(key: SortKey) {
  if (sortKey.value === key) {
    sortOpen.value = false
    return
  }
  sortKey.value = key
  sortOpen.value = false
  void load(true)
}

function openQuestion(id: string) {
  void openQuestionDetail({ id, roomId: roomId.value || undefined })
}

onMounted(() => {
  void load(true)
  void loadCategories()
})
onUnmounted(() => {
  if (keywordSearchTimer)
    clearTimeout(keywordSearchTimer)
})
</script>

<template>
  <view class="library-page">
    <view class="library-bg" aria-hidden="true" />
    <view class="library-veil" aria-hidden="true" />
    <view class="page-shell">
      <view class="page-head">
        <text class="title">
          题库
        </text>
        <text class="subtitle">
          {{ headerCountText }}
        </text>
        <view class="search">
          <text class="search-icon" aria-hidden="true">
            ⌕
          </text>
          <input
            v-model="keyword"
            class="search-input"
            placeholder="搜索题目、汤面或标签……"
            confirm-type="search"
            @input="onKeywordInput"
            @confirm="onKeywordInput"
          >
          <button v-if="keyword" class="search-clear" aria-label="清除搜索" @click="clearKeyword">
            ×
          </button>
        </view>
      </view>

      <view class="filter-bar">
        <view class="tab-row">
          <view class="tabs">
            <button
              v-for="tab in categories"
              :key="tab.label"
              class="tab"
              :class="{ active: activeTagId === tab.tagId && activeTabLabel === tab.label }"
              @click="selectTab(tab)"
            >
              {{ tab.label }}
            </button>
          </view>
          <button
            class="more-filter"
            :class="{ active: filtersVisible }"
            @click="filtersVisible = !filtersVisible"
          >
            更多筛选
            <text class="more-arrow">
              {{ filtersVisible ? '↑' : '↓' }}
            </text>
          </button>
        </view>

        <view v-if="filtersVisible" class="filter-panel">
          <view class="filter-group">
            <text class="filter-label">
              难度
            </text>
            <view class="chip-row">
              <button
                v-for="option in DIFFICULTY_OPTIONS"
                :key="option.label"
                class="chip"
                :class="{ active: difficulty === option.value }"
                @click="setDifficulty(option.value)"
              >
                {{ option.label }}
              </button>
            </view>
          </view>
          <button v-if="hasActiveFilter" class="reset-btn" @click="clearAllFilters">
            重置筛选
          </button>
        </view>

        <view class="result-bar">
          <text class="result-count">
            {{ resultLine }}
          </text>
          <view class="sort-wrap">
            <button class="sort-trigger" @click="sortOpen = !sortOpen">
              {{ sortLabel }}
              <text class="sort-arrow">
                ⌄
              </text>
            </button>
            <view v-if="sortOpen" class="sort-menu">
              <button
                v-for="option in SORT_OPTIONS"
                :key="option.key"
                class="sort-item"
                :class="{ active: sortKey === option.key }"
                @click="setSort(option.key)"
              >
                {{ option.label }}
              </button>
            </view>
          </view>
        </view>
      </view>

      <view v-if="hasActiveFilter" class="active-chips">
        <button v-if="activeTagLabel" class="active-chip" @click="selectTab({ label: '全部' })">
          {{ activeTagLabel }}
          <text class="chip-x">
            ×
          </text>
        </button>
        <button v-if="difficulty !== undefined" class="active-chip" @click="setDifficulty(undefined)">
          {{ difficultyLabel(difficulty) }}
          <text class="chip-x">
            ×
          </text>
        </button>
        <button v-if="keyword.trim()" class="active-chip" @click="clearKeyword">
          {{ keyword.trim() }}
          <text class="chip-x">
            ×
          </text>
        </button>
        <button class="active-chip clear" @click="clearAllFilters">
          清除全部
        </button>
      </view>

      <view v-if="loading && !items.length" class="skeleton-grid">
        <view v-for="n in 6" :key="n" class="skeleton-card">
          <view class="sk-bar sk-meta" />
          <view class="sk-bar sk-title" />
          <view class="sk-bar sk-line" />
          <view class="sk-bar sk-line short" />
        </view>
      </view>
      <view v-else-if="loadError && !items.length" class="empty">
        <text class="empty-mark">
          ◇
        </text>
        <text class="empty-title">
          网络好像迷路了
        </text>
        <button class="empty-action" @click="load(true)">
          重新加载
        </button>
      </view>
      <view v-else-if="!displayItems.length" class="empty">
        <text class="empty-mark">
          ◇
        </text>
        <text class="empty-title">
          没找到这碗汤
        </text>
        <text class="empty-desc">
          试试换一个关键词，或者减少一些筛选条件。
        </text>
        <button class="empty-action" @click="clearAllFilters">
          清除筛选
        </button>
      </view>
      <view v-else class="question-grid">
        <QuestionTextCard
          v-for="(item, index) in displayItems"
          :key="item.id"
          :question="item"
          :index="index"
          @click="openQuestion"
        />
      </view>

      <view v-if="totalPages > 1 && items.length" class="pager">
        <button class="pager-nav" :disabled="page <= 1" @click="goPage(page - 1)">
          ←
        </button>
        <template v-for="(p, i) in pageList" :key="p">
          <text v-if="i > 0 && p - pageList[i - 1] > 1" class="pager-gap">
            …
          </text>
          <button class="pager-page" :class="{ active: p === page }" @click="goPage(p)">
            {{ p }}
          </button>
        </template>
        <button class="pager-nav" :disabled="page >= totalPages" @click="goPage(page + 1)">
          →
        </button>
      </view>
    </view>
  </view>
</template>

<style scoped>
.library-page {
  position: relative;
  min-height: 100%;
  padding-bottom: 48px;
  overflow: hidden;
  background: var(--hgt-bg);
  color: var(--hgt-text);
}

/* 通透海底背景：顶部可见，向下渐隐到正常底色 */
.library-bg {
  position: absolute;
  inset: 0;
  z-index: 0;
  pointer-events: none;
  background-color: #04181d;
  background-image: url('/static/hgt/bg/bg_deep_ocean.jpg');
  background-position: center 12%;
  background-repeat: no-repeat;
  background-size: cover;
  background-attachment: scroll;
  filter: brightness(1.08) saturate(1.05);
  -webkit-mask-image: linear-gradient(180deg, #000 0%, rgba(0, 0, 0, 0.9) 22%, rgba(0, 0, 0, 0.55) 40%, rgba(0, 0, 0, 0.2) 55%, transparent 72%);
  mask-image: linear-gradient(180deg, #000 0%, rgba(0, 0, 0, 0.9) 22%, rgba(0, 0, 0, 0.55) 40%, rgba(0, 0, 0, 0.2) 55%, transparent 72%);
}

.library-veil {
  position: absolute;
  inset: 0;
  z-index: 0;
  pointer-events: none;
  background:
    linear-gradient(90deg,
      rgba(4, 20, 24, 0.42) 0%,
      rgba(4, 20, 24, 0.28) 40%,
      rgba(4, 20, 24, 0.22) 70%,
      rgba(4, 20, 24, 0.18) 100%),
    linear-gradient(180deg,
      rgba(4, 20, 24, 0.34) 0%,
      rgba(4, 20, 24, 0.38) 28%,
      rgba(4, 20, 24, 0.52) 48%,
      rgba(4, 20, 24, 0.78) 72%,
      var(--hgt-bg) 100%);
}

.page-shell {
  position: relative;
  z-index: 1;
  width: min(1400px, 100%);
  margin: 0 auto;
  padding: 28px 32px 0;
  box-sizing: border-box;
}

.page-head {
  display: flex;
  flex-direction: column;
  gap: 10px;
  padding-bottom: 20px;
}

.title {
  color: var(--hgt-text-bright);
  font-family: var(--hgt-font-display);
  font-size: 28px;
  font-weight: 600;
  letter-spacing: 0.08em;
}

.subtitle {
  color: var(--hgt-text-2);
  font-size: 14px;
  line-height: 1.6;
}

.search {
  display: flex;
  align-items: center;
  gap: 10px;
  width: min(640px, 100%);
  height: 44px;
  margin-top: 6px;
  padding: 0 14px;
  box-sizing: border-box;
  border: 1px solid var(--hgt-border);
  border-radius: var(--hgt-radius-sm);
  background: rgba(15, 53, 57, 0.28);
}

.search-icon {
  color: var(--hgt-text-3);
  font-size: 14px;
}

.search-input {
  flex: 1;
  min-width: 0;
  color: var(--hgt-text);
  font-size: 14px;
}

.search-clear {
  display: flex;
  flex: none;
  box-sizing: border-box;
  width: 22px;
  height: 22px;
  margin: 0;
  padding: 0;
  border: 0;
  border-radius: 999px;
  align-items: center;
  justify-content: center;
  background: var(--hgt-card-2);
  color: var(--hgt-text-2);
  font-size: 14px;
  line-height: 1;
}

.search-clear::after {
  border: 0;
}

/* 筛选控件条：半透明磨砂底，避免叠在亮水纹上看不清 */
.filter-bar {
  position: relative;
  z-index: 20;
  margin-top: 4px;
  padding: 6px 12px 0;
  border: 1px solid rgba(117, 220, 211, 0.08);
  border-radius: var(--hgt-radius-md);
  background: rgba(4, 20, 24, 0.42);
  backdrop-filter: blur(10px);
  -webkit-backdrop-filter: blur(10px);
}

.filter-bar .tab-row {
  border-bottom-color: rgba(117, 220, 211, 0.1);
}

.filter-bar .filter-panel {
  margin-top: 4px;
  padding-bottom: 10px;
}

.filter-bar .result-bar {
  margin-top: 8px;
  margin-bottom: 4px;
}

.tab-row {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 12px;
  border-bottom: 1px solid var(--hgt-border-soft);
}

.tabs {
  display: flex;
  flex: 1;
  min-width: 0;
  gap: 4px;
  overflow-x: auto;
}

.tab {
  position: relative;
  flex: none;
  height: 40px;
  margin: 0;
  padding: 0 12px;
  border: 0;
  border-radius: 0;
  background: transparent;
  color: var(--hgt-text-2);
  font-size: 13px;
  white-space: nowrap;
}

.tab::after {
  border: 0;
}

.tab.active {
  color: var(--hgt-text-bright);
}

.tab.active::after {
  content: '';
  position: absolute;
  left: 12px;
  right: 12px;
  bottom: 0;
  height: 2px;
  background: var(--hgt-brand);
}

.more-filter {
  display: inline-flex;
  flex: none;
  height: 40px;
  margin: 0;
  padding: 0 10px;
  border: 0;
  background: transparent;
  color: var(--hgt-text-2);
  font-size: 13px;
  align-items: center;
  gap: 4px;
}

.more-filter::after {
  border: 0;
}

.more-filter.active {
  color: var(--hgt-brand);
}

.more-arrow {
  font-size: 11px;
  opacity: 0.8;
}

.filter-panel {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-start;
  justify-content: space-between;
  gap: 14px;
  margin-top: 14px;
  padding: 14px 0;
  border-bottom: 1px solid var(--hgt-border-soft);
}

.filter-group {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.filter-label {
  color: var(--hgt-text-3);
  font-family: var(--hgt-font-mono);
  font-size: 11px;
  letter-spacing: 0.16em;
}

.chip-row {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.chip {
  display: inline-flex;
  height: 30px;
  margin: 0;
  padding: 0 12px;
  border: 1px solid var(--hgt-border);
  border-radius: var(--hgt-radius-full);
  align-items: center;
  background: transparent;
  color: var(--hgt-text-2);
  font-size: 12px;
}

.chip::after {
  border: 0;
}

.chip.active {
  border-color: var(--hgt-brand);
  background: var(--hgt-brand-soft);
  color: var(--hgt-brand);
}

.reset-btn {
  height: 30px;
  margin: 0;
  padding: 0 8px;
  border: 0;
  background: transparent;
  color: var(--hgt-brand);
  font-size: 12px;
  text-decoration: underline;
}

.reset-btn::after {
  border: 0;
}

.active-chips {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-top: 14px;
}

.active-chip {
  display: inline-flex;
  height: 28px;
  margin: 0;
  padding: 0 10px;
  border: 1px solid var(--hgt-border);
  border-radius: var(--hgt-radius-full);
  align-items: center;
  gap: 6px;
  background: transparent;
  color: var(--hgt-text-2);
  font-size: 12px;
}

.active-chip::after {
  border: 0;
}

.active-chip.clear {
  border-color: transparent;
  color: var(--hgt-text-3);
}

.chip-x {
  color: var(--hgt-text-3);
  font-size: 13px;
  line-height: 1;
}

.result-bar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin-top: 18px;
  margin-bottom: 12px;
  min-height: 28px;
}

.result-count {
  color: var(--hgt-text-3);
  font-family: var(--hgt-font-mono);
  font-size: 12px;
  letter-spacing: 0.06em;
}

.sort-wrap {
  position: relative;
}

.sort-trigger {
  display: inline-flex;
  height: 28px;
  margin: 0;
  padding: 0 4px;
  border: 0;
  background: transparent;
  color: var(--hgt-text);
  font-size: 13px;
  align-items: center;
  gap: 4px;
}

.sort-trigger::after {
  border: 0;
}

.sort-arrow {
  color: var(--hgt-text-3);
  font-size: 12px;
}

.sort-menu {
  position: absolute;
  z-index: 40;
  top: calc(100% + 6px);
  right: 0;
  display: flex;
  min-width: 148px;
  padding: 6px;
  border: 1px solid var(--hgt-border);
  border-radius: var(--hgt-radius-sm);
  flex-direction: column;
  background: var(--hgt-bg-deep);
}

.sort-item {
  height: 34px;
  margin: 0;
  padding: 0 10px;
  border: 0;
  border-radius: var(--hgt-radius-xs);
  background: transparent;
  color: var(--hgt-text-2);
  font-size: 13px;
  text-align: left;
}

.sort-item::after {
  border: 0;
}

.sort-item.active {
  color: var(--hgt-brand);
  background: var(--hgt-brand-soft);
}

.question-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 16px;
}

/* 卡片略透，让海底背景透出一点 */
.question-grid :deep(.q-card) {
  background: rgba(12, 40, 46, 0.42) !important;
  border-color: rgba(117, 220, 211, 0.1) !important;
  backdrop-filter: blur(6px);
  -webkit-backdrop-filter: blur(6px);
}

.skeleton-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 16px;
}

.skeleton-card {
  display: flex;
  min-height: 176px;
  padding: 18px;
  border: 1px solid var(--hgt-border-soft);
  border-radius: var(--hgt-radius-md);
  flex-direction: column;
  gap: 12px;
  background: rgba(15, 53, 57, 0.16);
}

.sk-bar {
  height: 10px;
  border-radius: 2px;
  background: linear-gradient(
    90deg,
    rgba(232, 244, 242, 0.06),
    rgba(232, 244, 242, 0.12),
    rgba(232, 244, 242, 0.06)
  );
}

.sk-meta {
  width: 36%;
}

.sk-title {
  width: 72%;
  height: 14px;
  margin-top: 6px;
}

.sk-line {
  width: 100%;
}

.sk-line.short {
  width: 68%;
}

.empty {
  display: flex;
  min-height: 280px;
  padding: 32px 16px;
  align-items: center;
  justify-content: center;
  flex-direction: column;
  gap: 10px;
}

.empty-mark {
  color: var(--hgt-brand);
  font-size: 22px;
  opacity: 0.7;
}

.empty-title {
  color: var(--hgt-text);
  font-family: var(--hgt-font-display);
  font-size: 18px;
  font-weight: 600;
}

.empty-desc {
  color: var(--hgt-text-2);
  font-size: 13px;
  line-height: 1.6;
  text-align: center;
}

.empty-action {
  height: 34px;
  margin: 6px 0 0;
  padding: 0 14px;
  border: 1px solid var(--hgt-border);
  border-radius: var(--hgt-radius-sm);
  background: transparent;
  color: var(--hgt-brand);
  font-size: 13px;
}

.empty-action::after {
  border: 0;
}

.pager {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: center;
  gap: 6px;
  margin-top: 28px;
}

.pager-nav,
.pager-page {
  display: inline-flex;
  min-width: 32px;
  height: 32px;
  margin: 0;
  padding: 0 8px;
  border: 1px solid transparent;
  border-radius: var(--hgt-radius-xs);
  align-items: center;
  justify-content: center;
  background: transparent;
  color: var(--hgt-text-2);
  font-family: var(--hgt-font-mono);
  font-size: 13px;
}

.pager-nav::after,
.pager-page::after {
  border: 0;
}

.pager-nav[disabled] {
  opacity: 0.28;
}

.pager-page.active {
  border-color: var(--hgt-border);
  color: var(--hgt-brand);
  background: var(--hgt-brand-soft);
}

.pager-gap {
  color: var(--hgt-text-3);
  font-family: var(--hgt-font-mono);
  font-size: 12px;
  padding: 0 2px;
}

@media (max-width: 1024px) {
  .question-grid,
  .skeleton-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (max-width: 767px) {
  .library-bg {
    background-position: center 0;
    background-size: 100% 260px;
    filter: brightness(1.18) saturate(1.08);
    -webkit-mask-image: linear-gradient(180deg, #000 0%, rgba(0, 0, 0, 0.75) 55%, transparent 100%);
    mask-image: linear-gradient(180deg, #000 0%, rgba(0, 0, 0, 0.75) 55%, transparent 100%);
  }

  .library-veil {
    background:
      linear-gradient(180deg,
        rgba(4, 20, 24, 0.28) 0%,
        rgba(4, 20, 24, 0.34) 35%,
        rgba(4, 20, 24, 0.58) 60%,
        var(--hgt-bg) 100%);
  }

  .page-shell {
    padding: 20px 16px 0;
  }

  .title {
    font-size: 24px;
  }

  .tab-row {
    flex-direction: column;
    align-items: stretch;
    gap: 0;
  }

  .more-filter {
    justify-content: flex-start;
    height: 36px;
    padding-left: 12px;
  }

  .question-grid,
  .skeleton-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
  }

  .skeleton-card {
    min-height: 150px;
    padding: 14px;
  }
}
</style>
