<script setup lang="ts">
import type { GameSnapshot } from '@/types/game'
import { ensureAnonymousSession, gameApi } from '@/api/turtle'
import { usePlayerStore } from '@/store/playerStore'

interface HistoryItem { id: string, status: string, title: string, difficulty: number, question_count?: number, created_at?: string }
definePage({ name: 'history', layout: 'tabbar', style: { navigationStyle: 'custom' } })
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
  router.push({ name: 'question-detail', params: { id: questionId } })
}
onMounted(async () => {
  try {
    await player.restore()
    if (!player.user)
      return
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
        <text>◎</text><text>暂无记录</text>
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
    <view v-else-if="!loading" class="login-guide">
      <view class="guide-symbol">
        ◎
      </view><text class="guide-title">
        登录后查看完整推理记录
      </text><text class="guide-copy">
        保存每一次游戏进度、完成结果和提问次数，随时回来继续未完成的谜题。
      </text><view class="guide-actions">
        <button class="primary" @click="router.push({ name: 'player-login' })">
          登录账号
        </button><button @click="router.push({ name: 'player-register' })">
          创建账号
        </button>
      </view><text class="guide-note">
        未登录仍可从题库开始单人推理
      </text>
    </view>
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
.history-page{min-height:100%;color:var(--hgt-fg);background:var(--hgt-bg)}.page-head{display:flex;flex-direction:column;padding:32px 36px;border-bottom:1px solid var(--hgt-border)}.eyebrow{color:var(--hgt-muted);font:12px monospace;letter-spacing:.5em}.title{margin-top:8px;font:34px Georgia,serif;letter-spacing:.08em}.stats{display:grid;grid-template-columns:repeat(4,1fr);border-bottom:1px solid var(--hgt-border)}.stats view{display:flex;flex-direction:column;padding:21px 36px;border-right:1px solid var(--hgt-border)}.stats view:last-child{border:0}.stats b{font:26px Georgia,serif}.stats text{margin-top:5px;color:var(--hgt-muted);font:11px monospace;letter-spacing:.12em}.filters{box-sizing:border-box;width:100%;padding:14px 36px;white-space:nowrap;background:var(--hgt-panel);border-bottom:1px solid var(--hgt-border)}.filters button{display:inline-flex;margin-right:8px;padding:7px 12px;color:var(--hgt-muted);background:transparent;border:1px solid var(--hgt-border);font:12px monospace}.filters .active{color:var(--hgt-fg);border-color:var(--hgt-fg)}.filters .playing.active{color:#facc15;border-color:#facc15}.filters .completed.active{color:#4ade80;border-color:#4ade80}.filters .abandoned.active{color:#f87171;border-color:#f87171}.records{margin:30px 36px;border:1px solid var(--hgt-border);background:var(--hgt-panel)}.table-head,.record{display:grid;grid-template-columns:2fr 1fr 1fr 1fr 1fr;align-items:center;padding:13px 18px;border-bottom:1px solid var(--hgt-border);font:12px monospace}.record:last-child{border-bottom:0}.table-head{color:var(--hgt-muted);background:var(--hgt-panel);letter-spacing:.12em}.record{background:var(--hgt-bg)}.record-title{font:15px Georgia,serif}.status{justify-self:start;padding:3px 8px;border:1px solid}.status.playing{color:#facc15}.status.completed{color:#4ade80}.status.abandoned{color:#f87171}.record button{justify-self:start;padding:6px 11px;color:var(--hgt-muted);background:transparent;border:1px solid var(--hgt-border);font:12px monospace}.empty{display:flex;flex-direction:column;align-items:center;gap:12px;padding:100px;color:var(--hgt-muted);font:13px monospace}.empty text:first-child{font-size:40px;color:var(--hgt-border)}
.history-page{min-height:100vh;color:var(--hgt-fg,var(--foreground,#f0f0f0));background:var(--hgt-bg,var(--background,#080808))}.login-guide{display:flex;box-sizing:border-box;width:min(620px,calc(100% - 56px));min-height:390px;margin:70px auto;padding:56px;border:1px solid var(--hgt-border,var(--border,#222));align-items:center;justify-content:center;background:var(--hgt-panel,var(--card,#111));text-align:center;flex-direction:column}.guide-symbol{display:flex;width:64px;height:64px;border:1px solid var(--hgt-border,var(--border,#222));align-items:center;justify-content:center;color:var(--hgt-muted,var(--muted-foreground,#666));font-size:26px}.guide-title{margin-top:28px;font:28px Georgia,serif}.guide-copy{max-width:440px;margin-top:16px;color:var(--hgt-muted,var(--muted-foreground,#666));font-size:13px;line-height:1.9}.guide-actions{display:flex;width:100%;margin-top:30px;gap:12px}.guide-actions button{display:flex;height:44px;min-height:44px;margin:0;padding:0 20px;border:1px solid var(--hgt-fg,var(--foreground,#f0f0f0));border-radius:0;align-items:center;justify-content:center;background:transparent;color:var(--hgt-fg,var(--foreground,#f0f0f0));font:11px monospace;letter-spacing:.14em;line-height:normal;flex:1 1 0}.guide-actions .primary{background:var(--hgt-fg,var(--foreground,#f0f0f0));color:var(--hgt-bg,var(--background,#080808))}.guide-note{margin-top:20px;color:var(--hgt-muted,var(--muted-foreground,#666));font:9px monospace;letter-spacing:.14em}
.stars{letter-spacing:.08em;white-space:nowrap}
.result-mask{position:fixed;z-index:60;inset:0;display:flex;padding:24px;align-items:center;justify-content:center;background:#000b}.result-modal{box-sizing:border-box;width:min(640px,100%);max-height:86vh;padding:32px;border:1px solid var(--hgt-border);overflow-y:auto;background:var(--hgt-panel);box-shadow:0 24px 80px #0008}.result-label,.result-section-label{display:block;color:var(--hgt-muted);font:10px monospace;letter-spacing:.18em}.result-title{display:block;margin:10px 0 22px;font:30px Georgia,serif}.result-summary{display:block;margin-bottom:18px;color:var(--hgt-muted);font-size:12px;line-height:1.8}.result-bottom{display:flex;padding:20px;border-left:2px solid var(--hgt-fg);background:var(--hgt-bg);gap:12px;flex-direction:column;font-size:14px;line-height:1.9}.result-points{display:flex;margin-top:22px;gap:8px;flex-direction:column}.result-point{padding:10px 0;border-bottom:1px solid var(--hgt-border);font-size:12px;line-height:1.7}.result-actions{display:flex;margin-top:26px;gap:12px}.result-actions button{display:flex;height:44px;margin:0;border:1px solid var(--hgt-fg);border-radius:0;align-items:center;justify-content:center;background:transparent;color:var(--hgt-fg);font:11px monospace;flex:1}.result-actions .primary{background:var(--hgt-fg);color:var(--hgt-bg)}
:deep(.history-result-popup){box-sizing:border-box;width:min(640px,calc(100vw - 48px));border:1px solid var(--border);border-radius:0;background:var(--card);color:var(--foreground)}:deep(.history-result-popup) .result-modal{width:100%;border:0;background:var(--card);box-shadow:none}
.records{border:1px solid var(--border,#222);background:var(--card,#111)}.table-head,.record{border-bottom:1px solid var(--border,#222)}.record{background:var(--background,#080808)}
.stats{border:1px solid var(--border,#222)}.stats view{border-right:1px solid var(--border,#222)}.stats view:last-child{border-right:0}
@media(max-width:600px){.page-head{padding:24px 18px}.title{font-size:29px}.stats{grid-template-columns:repeat(2,1fr)}.stats view{padding:16px 18px;border-bottom:1px solid var(--hgt-border)}.filters{padding:12px 18px}.records{display:flex;margin:18px;border:0;gap:10px;flex-direction:column;background:transparent}.table-head{display:none}.record{grid-template-columns:minmax(0,1fr) auto auto auto auto;padding:14px 12px;border:1px solid var(--hgt-border);gap:8px}.record:last-child{border-bottom:1px solid var(--hgt-border)}.record-title{overflow:hidden;font-size:13px;text-overflow:ellipsis;white-space:nowrap}.record .status{padding:2px 5px;font-size:10px}.record .stars{font-size:10px}.record>:nth-child(3),.record>:nth-child(4){color:var(--hgt-muted)}.record button{padding:5px 7px;white-space:nowrap}.login-guide{margin:36px auto;padding:38px 24px}.guide-actions{flex-direction:column}}
@media(max-width:600px){.record,.record:last-child{border:1px solid var(--border,#222)}}
@media(max-width:600px){.stats view{border-right:1px solid var(--border,#222);border-bottom:1px solid var(--border,#222)}.stats view:nth-child(2n){border-right:0}.stats view:nth-last-child(-n+2){border-bottom:0}}
</style>
