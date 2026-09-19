<script setup lang="ts">
import type { GameSnapshot } from '@/types/game'
import { ensureAnonymousSession, gameApi } from '@/api/turtle'
import { usePlayerStore } from '@/store/playerStore'
import { emptyHistoryUrl } from '@/utils/questionCover'
import { openQuestionDetail } from '@/utils/questionRoute'

interface HistoryItem { id: string, status: string, title: string, difficulty: number, question_count?: number, created_at?: string }
definePage({ name: 'history', layout: 'tabbar', style: { 'navigationStyle': 'custom', 'mp-toutiao': { navigationStyle: 'default' } } })
const router = useRouter()
const player = usePlayerStore()
const items = ref<HistoryItem[]>([])
const filter = ref('all')
const loading = ref(true)
const resultLoading = ref(false)
const selectedResult = ref<GameSnapshot | null>(null)
const completed = computed(() => items.value.filter(item => ['solved', 'finished'].includes(item.status)).length)
const filtered = computed(() => filter.value === 'all' ? items.value : items.value.filter(item => filter.value === 'completed' ? ['solved', 'finished'].includes(item.status) : item.status === filter.value))
const statusLabel = (status: string) => ({ created: '进行中', playing: '进行中', solved: '已完成', finished: '已完成', abandoned: '已放弃' }[status] || status)
const statusClass = (status: string) => ['solved', 'finished'].includes(status) ? 'completed' : status === 'abandoned' ? 'abandoned' : 'playing'
const difficultyStars = (value: number) => `${'★'.repeat(Math.max(0, Math.min(5, value)))}${'☆'.repeat(Math.max(0, 5 - value))}`
async function openRecord(item: HistoryItem) {
  if (['created', 'playing'].includes(item.status)) {
    router.push({ name: 'game', params: { id: item.id } })
    return
  }
  resultLoading.value = true
  try {
    selectedResult.value = await gameApi.read(item.id)
  }
  catch (error) {
    uni.showToast({ title: (error as Error).message || '结算信息加载失败', icon: 'none' })
  }
  finally {
    resultLoading.value = false
  }
}
function replay() {
  const questionId = selectedResult.value?.question_id
  if (!questionId)
    return
  selectedResult.value = null
  void openQuestionDetail({ id: questionId })
}
onMounted(async () => {
  try {
    await player.restore()
    if (!player.user) {
      router.replace({ name: 'player-login', query: { redirect: '/pages/history/index' } })
      return
    }
    await ensureAnonymousSession()
    items.value = await gameApi.history()
  }
  finally { loading.value = false }
})
</script>

<template>
  <view class="history-page">
    <view class="page-head">
      <text class="eyebrow">
        ◎ 游玩记录
      </text><text class="title">
        历史记录
      </text>
    </view>
    <template v-if="player.user">
      <view class="stats">
        <view><b>{{ items.length }}</b><text>总游戏数</text></view><view><b>{{ completed }}</b><text>完成数</text></view><view><b>{{ items.length ? Math.round(completed / items.length * 100) : 0 }}%</b><text>完成率</text></view><view><b>{{ items.reduce((sum, item) => sum + (item.question_count || 0), 0) }}</b><text>累计提问</text></view>
      </view>
      <scroll-view scroll-x class="filters">
        <button v-for="tab in [{ key: 'all', label: '全部' }, { key: 'playing', label: '进行中' }, { key: 'completed', label: '已完成' }, { key: 'abandoned', label: '已放弃' }]" :key="tab.key" :class="[tab.key, { active: filter === tab.key }]" @click="filter = tab.key">
          {{ tab.label }}
        </button>
      </scroll-view>
      <view v-if="!filtered.length" class="empty">
        <image class="empty-img" :src="emptyHistoryUrl" mode="aspectFit" />
        <text>日志还空着，去题库开一碗吧</text>
      </view>
      <view v-else class="records">
        <view class="table-head">
          <text>题目</text><text>状态</text><text>难度</text><text>提问次数</text><text>操作</text>
        </view>
        <view v-for="item in filtered" :key="item.id" class="record">
          <text class="record-title">
            {{ item.title }}
          </text><text class="status" :class="statusClass(item.status)">
            {{ statusLabel(item.status) }}
          </text><text class="stars" :aria-label="`难度 ${item.difficulty} 星`">
            {{ difficultyStars(item.difficulty) }}
          </text><text>{{ item.question_count || 0 }} 次</text><button :disabled="resultLoading" @click="openRecord(item)">
            {{ ['created', 'playing'].includes(item.status) ? '继续' : '查看' }} →
          </button>
        </view>
      </view>
    </template>
    <wd-popup :model-value="Boolean(selectedResult)" position="center" :root-portal="true" custom-class="history-result-popup" @update:model-value="!$event && (selectedResult = null)">
      <view v-if="selectedResult" class="result-modal">
        <text class="result-label">
          {{ selectedResult.status === 'solved' ? '推理成功' : selectedResult.status === 'abandoned' ? '本局已放弃' : '本局结束' }}
        </text>
        <text class="result-title">
          {{ selectedResult.title }}
        </text>
        <text v-if="selectedResult.guess?.summary" class="result-summary">
          {{ selectedResult.guess.summary }}
        </text>
        <view class="result-bottom">
          <text class="result-section-label">
            汤底揭晓
          </text>
          <text>{{ selectedResult.bottom || '暂无汤底内容' }}</text>
        </view>
        <view v-if="selectedResult.points?.length" class="result-points">
          <text class="result-section-label">
            关键推理点
          </text>
          <text v-for="point in selectedResult.points" :key="point.key" class="result-point">
            {{ point.content }}
          </text>
        </view>
        <view class="result-actions">
          <button @click="selectedResult = null">
            关闭
          </button>
          <button class="primary" :disabled="!selectedResult.question_id" @click="replay">
            再次游玩
          </button>
        </view>
      </view>
    </wd-popup>
  </view>
</template>

<style scoped>
.history-page {
  min-height: 100%;
  padding-bottom: 40px;
  background: var(--hgt-bg);
  color: var(--hgt-text);
}
.page-head {
  display: flex;
  padding: 36px 48px 24px;
  border-bottom: 1px solid var(--hgt-border);
  gap: 8px;
  flex-direction: column;
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
.stats {
  display: grid;
  border-bottom: 1px solid var(--hgt-border);
  grid-template-columns: repeat(4, 1fr);
}
.stats view {
  display: flex;
  padding: 22px 48px;
  border-right: 1px solid var(--hgt-border);
  gap: 6px;
  flex-direction: column;
}
.stats view:last-child {
  border-right: 0;
}
.stats b {
  color: var(--hgt-text);
  font-family: var(--hgt-font-display);
  font-size: 24px;
  font-weight: 600;
}
.stats text {
  color: var(--hgt-text-2);
  font-size: 12px;
  letter-spacing: 0.1em;
}
.filters {
  box-sizing: border-box;
  width: 100%;
  padding: 14px 48px;
  border-bottom: 1px solid var(--hgt-border);
  white-space: nowrap;
  background: var(--hgt-card);
}
.filters button {
  display: inline-flex;
  height: 34px;
  margin-right: 8px;
  padding: 0 14px;
  border: 1px solid var(--hgt-border);
  border-radius: var(--hgt-radius-full);
  align-items: center;
  background: transparent;
  color: var(--hgt-text-2);
  font-size: 13px;
}
.filters button::after {
  border: 0;
}
.filters .active {
  border-color: var(--hgt-brand);
  background: var(--hgt-brand-soft);
  color: var(--hgt-brand);
}
.filters .playing.active {
  border-color: var(--hgt-warning);
  color: var(--hgt-warning);
  background: rgba(240, 194, 57, 0.14);
}
.filters .completed.active {
  border-color: var(--hgt-success);
  color: var(--hgt-success-text);
  background: rgba(120, 146, 98, 0.16);
}
.filters .abandoned.active {
  border-color: var(--hgt-danger);
  color: var(--hgt-danger);
  background: rgba(158, 83, 86, 0.12);
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
  width: 176px;
  height: 176px;
  opacity: 1;
  border-radius: 0;
  filter: drop-shadow(0 12px 32px rgba(42, 42, 40, 0.12));
}
.records {
  margin: 24px 48px;
  border: 1px solid var(--hgt-border);
  border-radius: var(--hgt-radius-md);
  overflow: hidden;
  background: var(--hgt-card);
}
.table-head,
.record {
  display: grid;
  padding: 14px 18px;
  border-bottom: 1px solid var(--hgt-border);
  align-items: center;
  grid-template-columns: 2fr 1fr 1fr 1fr 1fr;
  font-size: 13px;
}
.table-head {
  background: var(--hgt-card-2);
  color: var(--hgt-text-3);
  font-size: 12px;
  letter-spacing: 0.1em;
}
.record:last-child {
  border-bottom: 0;
}
.record-title {
  color: var(--hgt-text);
  font-family: var(--hgt-font-display);
  font-size: 15px;
  font-weight: 600;
}
.status {
  justify-self: start;
  padding: 3px 8px;
  border-radius: var(--hgt-radius-xs);
  font-size: 12px;
  border: 1px solid;
}
.status.playing {
  border-color: rgba(240, 194, 57, 0.5);
  color: var(--hgt-warning);
}
.status.completed {
  border-color: rgba(120, 146, 98, 0.5);
  color: var(--hgt-success-text);
}
.status.abandoned {
  border-color: rgba(158, 83, 86, 0.5);
  color: var(--hgt-danger);
}
.stars {
  letter-spacing: 0.08em;
  white-space: nowrap;
  color: var(--hgt-warning);
}
.record button {
  justify-self: start;
  height: 32px;
  margin: 0;
  padding: 0 12px;
  border: 1px solid var(--hgt-border);
  border-radius: var(--hgt-radius-sm);
  background: transparent;
  color: var(--hgt-brand);
  font-size: 12px;
}
.record button::after {
  border: 0;
}
.result-modal {
  display: flex;
  box-sizing: border-box;
  width: 100%;
  padding: 28px;
  gap: 14px;
  flex-direction: column;
  background: var(--hgt-card);
  color: var(--hgt-text);
}
.result-label,
.result-section-label {
  color: var(--hgt-text-3);
  font-size: 11px;
  letter-spacing: 0.16em;
}
.result-title {
  color: var(--hgt-text);
  font-family: var(--hgt-font-display);
  font-size: 24px;
  font-weight: 600;
}
.result-summary {
  color: var(--hgt-text-2);
  font-size: 13px;
  line-height: 1.7;
}
.result-bottom {
  display: flex;
  padding: 18px;
  border: 1px solid var(--hgt-border);
  border-radius: var(--hgt-radius-md);
  gap: 10px;
  flex-direction: column;
  background: var(--hgt-paper);
  color: var(--hgt-paper-ink);
  font-size: 14px;
  line-height: 1.8;
}
.result-bottom .result-section-label {
  color: #5a5e48;
}
.result-points {
  display: flex;
  gap: 8px;
  flex-direction: column;
}
.result-point {
  padding: 8px 0;
  border-bottom: 1px solid var(--hgt-border);
  color: var(--hgt-text-2);
  font-size: 13px;
  line-height: 1.6;
}
.result-actions {
  display: flex;
  margin-top: 8px;
  gap: 10px;
}
.result-actions button {
  display: flex;
  height: 44px;
  margin: 0;
  padding: 0 16px;
  border: 1px solid var(--hgt-border-soft);
  border-radius: var(--hgt-radius-sm);
  align-items: center;
  justify-content: center;
  flex: 1;
  background: transparent;
  color: var(--hgt-text);
  font-size: 14px;
}
.result-actions button::after {
  border: 0;
}
.result-actions .primary {
  border-color: transparent;
  background: var(--hgt-brand);
  color: var(--hgt-on-brand);
}
:deep(.history-result-popup) {
  box-sizing: border-box;
  width: min(560px, calc(100vw - 32px));
  border: 1px solid var(--hgt-border);
  border-radius: var(--hgt-radius-lg);
  background: var(--hgt-card);
  color: var(--hgt-text);
  overflow: hidden;
}
:deep(.history-result-popup) .result-modal {
  width: 100%;
  border: 0;
  box-shadow: none;
}

@media (max-width: 767px) {
  .page-head,
  .filters {
    padding-right: 16px;
    padding-left: 16px;
  }
  .stats {
    grid-template-columns: repeat(2, 1fr);
  }
  .stats view {
    padding: 16px;
    border-bottom: 1px solid var(--hgt-border);
  }
  .stats view:nth-child(2n) {
    border-right: 0;
  }
  .records {
    display: flex;
    margin: 16px;
    border: 0;
    gap: 10px;
    flex-direction: column;
    background: transparent;
  }
  .table-head {
    display: none;
  }
  .record {
    display: grid;
    padding: 14px 12px;
    border: 1px solid var(--hgt-border);
    border-radius: var(--hgt-radius-md);
    gap: 8px;
    grid-template-columns: minmax(0, 1fr) auto auto auto auto;
    background: var(--hgt-card);
  }
  .record-title {
    overflow: hidden;
    font-size: 14px;
    text-overflow: ellipsis;
    white-space: nowrap;
  }
  .status {
    padding: 2px 6px;
    font-size: 11px;
  }
}
</style>
