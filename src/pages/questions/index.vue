<script setup lang="ts">
import type { PublicQuestion } from '@/types/game'
import { questionApi } from '@/api/turtle'
import { formatCount } from '@/utils'
import { questionCoverUrl } from '@/utils/questionCover'
import { openQuestionDetail } from '@/utils/questionRoute'

definePage({ name: 'questions', layout: 'tabbar', style: { 'navigationStyle': 'custom', 'mp-toutiao': { navigationStyle: 'default' } } })
const route = useRoute()
const roomId = computed(() => String(route.query.room_id || ''))
const items = ref<PublicQuestion[]>([])
const keyword = ref('')
const difficulty = ref<number>()
const filtersVisible = ref(false)
const excludedRiskTypes = ref<string[]>([])
const viewMode = ref<'grid' | 'list'>('grid')
const loading = ref(false)
const loadError = ref(false)
const page = ref(1)
const pageSize = 21
const total = ref(0)
const activeRiskId = ref<string | null>(null)
const filtered = computed(() => items.value.filter((item) => {
  const matchesKeyword = !keyword.value || item.title.includes(keyword.value) || item.surface.includes(keyword.value)
  const matchesRisk = !item.risk_types?.some(type => excludedRiskTypes.value.includes(type))
  return matchesKeyword && matchesRisk
}))
const activeFilterCount = computed(() => (difficulty.value ? 1 : 0) + excludedRiskTypes.value.length)
const loadmoreState = computed<'loading' | 'finished' | 'error'>(() => {
  if (loadError.value)
    return 'error'
  if (loading.value)
    return 'loading'
  return items.value.length >= total.value ? 'finished' : 'loading'
})
const difficultyLabel = (value: number) => ['未知', '简单', '普通', '中等', '困难', '极难'][value] || `难度 ${value}`
const difficultyClass = (value: number) => ['unknown', 'easy', 'normal', 'medium', 'hard', 'extreme'][value] || 'unknown'
const riskLevelLabels: Record<PublicQuestion['risk_level'], string> = { safe: '安全', caution: '需注意', restricted: '受限内容' }
const riskTypeLabels: Record<string, string> = {
  death: '死亡',
  violence: '暴力',
  gore: '血腥',
  self_harm: '自伤',
  sexual: '性内容',
  child_safety: '未成年人',
  discrimination: '歧视',
  illegal: '违法',
  substance: '成瘾物',
  other: '其他',
}
const riskTypeOptions = Object.entries(riskTypeLabels).map(([value, label]) => ({ value, label }))
const riskLevelLabel = (value: PublicQuestion['risk_level']) => riskLevelLabels[value] || value
const riskTypeText = (types: string[] | undefined) => types?.length ? types.map(type => riskTypeLabels[type] || type).join('、') : '无特别标注'
async function load(reset = false) {
  if (loading.value)
    return
  if (reset) {
    page.value = 1
    total.value = 0
    items.value = []
  }
  loading.value = true
  loadError.value = false
  try {
    const result = await questionApi.list({
      ...(difficulty.value ? { difficulty: difficulty.value } : {}),
      page: page.value,
      page_size: pageSize,
    })
    items.value = reset ? result.items : [...items.value, ...result.items]
    total.value = result.pagination.total || items.value.length
    if (items.value.length < total.value)
      page.value += 1
  }
  catch {
    loadError.value = true
  }
  finally {
    loading.value = false
  }
}
function loadMore() {
  if (!loading.value && items.value.length < total.value)
    void load()
}
function changeDifficulty(value?: number) {
  difficulty.value = value
  void load(true)
}
function toggleExcludedRiskType(value: string) {
  excludedRiskTypes.value = excludedRiskTypes.value.includes(value)
    ? excludedRiskTypes.value.filter(type => type !== value)
    : [...excludedRiskTypes.value, value]
}
function clearFilters() {
  excludedRiskTypes.value = []
  if (difficulty.value !== undefined)
    changeDifficulty()
}
onMounted(() => load(true))
onReachBottom(loadMore)
function openQuestion(id: string) {
  void openQuestionDetail({ id, roomId: roomId.value || undefined })
}
function toggleRisk(id: string) {
  activeRiskId.value = activeRiskId.value === id ? null : id
}
</script>

<template>
  <view class="library-page">
    <view class="page-head">
      <text class="eyebrow">
        TURTLE SOUP · LIBRARY
      </text>
      <text class="title">
        题库
      </text>
      <text class="subtitle">
        浏览全部谜题，按难度与风险筛选
      </text>
    </view>

    <view class="filter-shell">
      <view class="filter-toolbar">
        <view class="search">
          <text class="search-icon">
            ⌕
          </text>
          <input v-model="keyword" placeholder="搜索题目、汤面...">
        </view>
        <button class="filter-trigger" :class="{ active: filtersVisible || activeFilterCount }" @click="filtersVisible = !filtersVisible">
          <text>筛选</text>
          <text v-if="activeFilterCount" class="filter-count">
            {{ activeFilterCount }}
          </text>
          <text class="filter-arrow">
            {{ filtersVisible ? '↑' : '↓' }}
          </text>
        </button>
        <view class="view-toggle">
          <button :class="{ active: viewMode === 'grid' }" aria-label="网格视图" @click="viewMode = 'grid'">
            ⊞
          </button>
          <button :class="{ active: viewMode === 'list' }" aria-label="列表视图" @click="viewMode = 'list'">
            ☰
          </button>
        </view>
      </view>

      <view v-if="filtersVisible" class="filter-panel">
        <view class="filter-group">
          <text class="filter-label">
            难度
          </text>
          <view class="filter-options">
            <button :class="{ active: difficulty === undefined }" @click="changeDifficulty()">
              全部
            </button>
            <button
              v-for="level in [1, 2, 3, 4, 5]"
              :key="level"
              :class="[{ active: difficulty === level }, difficultyClass(level)]"
              @click="changeDifficulty(level)"
            >
              {{ difficultyLabel(level) }}
            </button>
          </view>
        </view>
        <view class="filter-group risk-filter-group">
          <view class="filter-label-row">
            <text class="filter-label">
              排除风险类型
            </text>
            <text class="filter-hint">
              可多选
            </text>
          </view>
          <view class="filter-options risk-options">
            <button
              v-for="option in riskTypeOptions"
              :key="option.value"
              :class="{ excluded: excludedRiskTypes.includes(option.value) }"
              @click="toggleExcludedRiskType(option.value)"
            >
              <text class="option-mark">
                {{ excludedRiskTypes.includes(option.value) ? '×' : '+' }}
              </text>
              {{ option.label }}
            </button>
          </view>
        </view>
        <button v-if="activeFilterCount" class="clear-filter" @click="clearFilters">
          清除筛选
        </button>
      </view>
    </view>

    <view class="result-count">
      {{ keyword || excludedRiskTypes.length ? `当前匹配 ${filtered.length} 个谜题` : `已加载 ${items.length} / ${total} 个谜题` }}
    </view>

    <view v-if="loading && !items.length" class="empty">
      <image class="empty-img" src="/static/hgt/empty/empty_none.png" mode="aspectFit" />
      <text>正在潜入题库…</text>
    </view>
    <view v-else-if="!filtered.length" class="empty">
      <image class="empty-img" src="/static/hgt/empty/empty_none.png" mode="aspectFit" />
      <text>没有找到匹配的谜题</text>
    </view>
    <view v-else class="question-wrap" :class="viewMode">
      <view
        v-for="item in filtered"
        :key="item.id"
        class="question-card"
        @click="openQuestion(item.id)"
      >
        <view class="card-cover">
          <image class="cover-img" :src="questionCoverUrl(item)" mode="aspectFill" />
          <view class="card-tags">
            <text v-if="item.tags?.[0]" class="tag-cat">
              {{ item.tags[0].name }}
            </text>
            <text class="tag-diff" :class="difficultyClass(item.difficulty)">
              {{ difficultyLabel(item.difficulty) }}
            </text>
          </view>
          <view v-if="item.risk_level !== 'safe'" class="risk-wrap" @click.stop="toggleRisk(item.id)">
            <text class="risk" :class="item.risk_level">
              {{ riskLevelLabel(item.risk_level) }}
            </text>
            <view v-if="activeRiskId === item.id" class="risk-tip" @click.stop>
              <text class="risk-tip-title">
                风险类型：{{ riskTypeText(item.risk_types) }}
              </text>
              <text class="risk-tip-note">
                {{ item.risk_note || item.risk_warning || '暂无具体说明' }}
              </text>
            </view>
          </view>
        </view>
        <view class="card-body">
          <text class="question-title">
            {{ item.title }}
          </text>
          <text class="surface">
            {{ item.surface }}
          </text>
          <view v-if="item.tags?.length" class="tags">
            <text v-for="tag in item.tags" :key="tag.id" class="tag">
              {{ tag.name }}
            </text>
          </view>
          <view class="foot">
            <text>{{ formatCount(item.play_count) }} 人玩过</text>
            <text class="enter">
              进入 →
            </text>
          </view>
        </view>
      </view>
    </view>

    <wd-loadmore
      v-if="items.length"
      :state="loadmoreState"
      loading-text="正在加载更多谜题…"
      finished-text="已经到底了"
      error-text="加载失败，点击重试"
      @reload="loadMore"
    />
  </view>
</template>

<style scoped>
.library-page {
  min-height: 100%;
  padding-bottom: 40px;
  background: var(--hgt-bg);
  color: var(--hgt-text);
}
.page-head {
  display: flex;
  padding: 36px 48px 24px;
  gap: 8px;
  flex-direction: column;
  border-bottom: 1px solid var(--hgt-border);
}
.eyebrow {
  color: var(--hgt-brand);
  font-family: var(--hgt-font-mono);
  font-size: 11px;
  letter-spacing: 0.28em;
}
.title {
  color: var(--hgt-text);
  font-family: var(--hgt-font-display);
  font-size: 28px;
  font-weight: 600;
  letter-spacing: 0.08em;
}
.subtitle {
  color: var(--hgt-text-2);
  font-size: 14px;
}
.filter-shell {
  padding: 16px 48px 0;
}
.filter-toolbar {
  display: flex;
  gap: 10px;
  align-items: center;
}
.search {
  display: flex;
  flex: 1;
  max-width: 360px;
  height: 42px;
  padding: 0 14px;
  border: 1px solid var(--hgt-border);
  border-radius: var(--hgt-radius-sm);
  align-items: center;
  gap: 8px;
  background: var(--hgt-card);
}
.search input {
  flex: 1;
  color: var(--hgt-text);
  font-size: 14px;
}
.search-icon {
  color: var(--hgt-text-3);
}
.filter-trigger,
.view-toggle button,
.filter-options button,
.clear-filter {
  display: inline-flex;
  height: 42px;
  margin: 0;
  padding: 0 14px;
  border: 1px solid var(--hgt-border);
  border-radius: var(--hgt-radius-sm);
  align-items: center;
  gap: 6px;
  background: transparent;
  color: var(--hgt-text-2);
  font-size: 13px;
}
.filter-trigger.active,
.view-toggle button.active,
.filter-options button.active {
  border-color: var(--hgt-brand);
  background: var(--hgt-brand-soft);
  color: var(--hgt-brand);
}
.filter-trigger::after,
.view-toggle button::after,
.filter-options button::after,
.clear-filter::after {
  border: 0;
}
.filter-count {
  display: inline-flex;
  min-width: 18px;
  height: 18px;
  padding: 0 5px;
  border-radius: var(--hgt-radius-full);
  align-items: center;
  justify-content: center;
  background: var(--hgt-brand);
  color: var(--hgt-on-brand);
  font-size: 11px;
}
.view-toggle {
  display: flex;
  gap: 6px;
  margin-left: auto;
}
.view-toggle button {
  width: 42px;
  padding: 0;
  justify-content: center;
}
.filter-panel {
  position: relative;
  display: grid;
  margin-top: 12px;
  padding: 18px;
  border: 1px solid var(--hgt-border);
  border-radius: var(--hgt-radius-md);
  gap: 18px;
  background: var(--hgt-card);
  grid-template-columns: minmax(220px, 0.7fr) minmax(280px, 1.3fr);
}
.filter-label {
  display: block;
  margin-bottom: 10px;
  color: var(--hgt-text);
  font-size: 13px;
  font-weight: 600;
}
.filter-label-row {
  display: flex;
  margin-bottom: 10px;
  align-items: center;
  gap: 8px;
}
.filter-label-row .filter-label {
  margin-bottom: 0;
}
.filter-hint {
  color: var(--hgt-text-3);
  font-size: 12px;
}
.filter-options {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}
.risk-options button.excluded {
  border-color: var(--hgt-danger);
  background: rgba(201, 74, 85, 0.12);
  color: var(--hgt-danger);
}
.option-mark {
  width: 12px;
}
.clear-filter {
  position: absolute;
  top: 12px;
  right: 12px;
  height: 30px;
  border: 0;
  color: var(--hgt-brand);
  text-decoration: underline;
}
.result-count {
  padding: 16px 48px 8px;
  color: var(--hgt-text-3);
  font-size: 12px;
}
.empty {
  display: flex;
  min-height: 240px;
  gap: 12px;
  align-items: center;
  justify-content: center;
  flex-direction: column;
  color: var(--hgt-text-2);
}
.empty-img {
  width: 120px;
  height: 120px;
  opacity: 0.85;
}
.question-wrap {
  padding: 12px 48px 24px;
}
.question-wrap.grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 16px;
}
.question-wrap.list {
  display: flex;
  gap: 12px;
  flex-direction: column;
}
.question-card {
  display: flex;
  border: 1px solid var(--hgt-border);
  border-radius: var(--hgt-radius-md);
  flex-direction: column;
  overflow: hidden;
  background: var(--hgt-card);
  box-shadow: var(--hgt-shadow-sm);
  transition: transform var(--hgt-dur-fast), border-color var(--hgt-dur-fast), box-shadow var(--hgt-dur-fast);
}
.question-card:hover {
  border-color: var(--hgt-border-soft);
  box-shadow: var(--hgt-shadow-md);
  transform: translateY(-2px);
}
.question-wrap.list .question-card {
  display: grid;
  grid-template-columns: 160px 1fr;
}
.card-cover {
  position: relative;
  aspect-ratio: 16 / 9;
  overflow: hidden;
  background: var(--hgt-card-2);
}
.question-wrap.list .card-cover {
  aspect-ratio: auto;
  min-height: 120px;
  height: 100%;
}
.cover-img {
  width: 100%;
  height: 100%;
}
.card-tags {
  position: absolute;
  top: 10px;
  left: 10px;
  display: flex;
  gap: 6px;
}
.tag-cat,
.tag-diff {
  padding: 3px 8px;
  border-radius: var(--hgt-radius-xs);
  font-size: 11px;
}
.tag-cat {
  background: rgba(7, 20, 24, 0.75);
  color: var(--hgt-text);
}
.tag-diff.easy {
  background: rgba(94, 135, 135, 0.9);
  color: #fff;
}
.tag-diff.normal,
.tag-diff.medium {
  background: rgba(196, 154, 85, 0.92);
  color: #1a1208;
}
.tag-diff.hard,
.tag-diff.extreme {
  background: rgba(201, 74, 85, 0.92);
  color: #fff;
}
.risk-wrap {
  position: absolute;
  right: 10px;
  bottom: 10px;
}
.risk {
  padding: 2px 7px;
  border-radius: var(--hgt-radius-xs);
  font-size: 11px;
  background: rgba(7, 20, 24, 0.8);
}
.risk.caution {
  color: var(--hgt-warning);
}
.risk.restricted {
  color: var(--hgt-danger);
}
.risk-tip {
  position: absolute;
  z-index: 20;
  right: 0;
  bottom: calc(100% + 8px);
  display: flex;
  width: 240px;
  padding: 12px;
  border: 1px solid var(--hgt-border-soft);
  border-radius: var(--hgt-radius-sm);
  gap: 6px;
  flex-direction: column;
  background: var(--hgt-bg-deep);
  box-shadow: var(--hgt-shadow-float);
}
.risk-tip-title {
  color: var(--hgt-text);
  font-size: 12px;
}
.risk-tip-note {
  color: var(--hgt-text-2);
  font-size: 12px;
  line-height: 1.55;
}
.card-body {
  display: flex;
  padding: 14px 16px 16px;
  gap: 8px;
  flex-direction: column;
}
.question-title {
  color: var(--hgt-text);
  font-family: var(--hgt-font-display);
  font-size: 17px;
  font-weight: 600;
  line-height: 1.35;
}
.surface {
  display: -webkit-box;
  overflow: hidden;
  color: var(--hgt-text-2);
  font-size: 13px;
  line-height: 1.55;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 2;
}
.tags {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  min-height: 22px;
}
.tag {
  padding: 2px 8px;
  border: 1px solid var(--hgt-border);
  border-radius: var(--hgt-radius-xs);
  color: var(--hgt-text-3);
  font-size: 11px;
}
.foot {
  display: flex;
  margin-top: 4px;
  align-items: center;
  justify-content: space-between;
  color: var(--hgt-text-3);
  font-size: 12px;
}
.enter {
  color: var(--hgt-brand);
}

@media (max-width: 1199px) {
  .question-wrap.grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
  .filter-panel {
    grid-template-columns: 1fr;
  }
}
@media (max-width: 767px) {
  .page-head,
  .filter-shell,
  .result-count,
  .question-wrap {
    padding-right: 16px;
    padding-left: 16px;
  }
  .title {
    font-size: 24px;
  }
  .filter-toolbar {
    flex-wrap: wrap;
  }
  .search {
    max-width: none;
    width: 100%;
  }
  .view-toggle {
    margin-left: 0;
  }
  .question-wrap.grid {
    grid-template-columns: 1fr;
  }
  .question-wrap.list .question-card {
    grid-template-columns: 1fr;
  }
  .clear-filter {
    position: static;
    width: max-content;
  }
}
</style>
