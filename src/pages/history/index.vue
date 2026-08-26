<script setup lang="ts">
import { ensureAnonymousSession, gameApi } from '@/api/turtle'

interface HistoryItem { id: string, status: string, title: string, difficulty: number, question_count?: number, created_at?: string }
definePage({ name: 'history', layout: 'tabbar', style: { navigationStyle: 'custom' } })
const router = useRouter()
const items = ref<HistoryItem[]>([])
const filter = ref('all')
const completed = computed(() => items.value.filter(item => ['solved', 'finished'].includes(item.status)).length)
const filtered = computed(() => filter.value === 'all' ? items.value : items.value.filter(item => filter.value === 'completed' ? ['solved', 'finished'].includes(item.status) : item.status === filter.value))
const statusLabel = (status: string) => ({ created: '进行中', playing: '进行中', solved: '已完成', finished: '已完成', abandoned: '已放弃' }[status] || status)
const statusClass = (status: string) => ['solved', 'finished'].includes(status) ? 'completed' : status === 'abandoned' ? 'abandoned' : 'playing'
onMounted(async () => {
  await ensureAnonymousSession()
  items.value = await gameApi.history()
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
        </text><text>难度 {{ item.difficulty }}</text><text>{{ item.question_count || 0 }} 次</text><button @click="router.push({ name: ['solved', 'finished', 'abandoned'].includes(item.status) ? 'result' : 'game', params: { id: item.id } })">
          {{ ['created', 'playing'].includes(item.status) ? '继续' : '查看' }} →
        </button>
      </view>
    </view>
  </view>
</template>

<style scoped>
.history-page{min-height:100%;color:var(--hgt-fg);background:var(--hgt-bg)}.page-head{display:flex;flex-direction:column;padding:32px 36px;border-bottom:1px solid var(--hgt-border)}.eyebrow{color:var(--hgt-muted);font:12px monospace;letter-spacing:.5em}.title{margin-top:8px;font:34px Georgia,serif;letter-spacing:.08em}.stats{display:grid;grid-template-columns:repeat(4,1fr);border-bottom:1px solid var(--hgt-border)}.stats view{display:flex;flex-direction:column;padding:21px 36px;border-right:1px solid var(--hgt-border)}.stats view:last-child{border:0}.stats b{font:26px Georgia,serif}.stats text{margin-top:5px;color:var(--hgt-muted);font:11px monospace;letter-spacing:.12em}.filters{box-sizing:border-box;width:100%;padding:14px 36px;white-space:nowrap;background:var(--hgt-panel);border-bottom:1px solid var(--hgt-border)}.filters button{display:inline-flex;margin-right:8px;padding:7px 12px;color:var(--hgt-muted);background:transparent;border:1px solid var(--hgt-border);font:12px monospace}.filters .active{color:var(--hgt-fg);border-color:var(--hgt-fg)}.filters .playing.active{color:#facc15;border-color:#facc15}.filters .completed.active{color:#4ade80;border-color:#4ade80}.filters .abandoned.active{color:#f87171;border-color:#f87171}.records{margin:30px 36px;border-top:1px solid var(--hgt-border);border-left:1px solid var(--hgt-border)}.table-head,.record{display:grid;grid-template-columns:2fr 1fr 1fr 1fr 1fr;align-items:center;padding:13px 18px;border-right:1px solid var(--hgt-border);border-bottom:1px solid var(--hgt-border);font:12px monospace}.table-head{color:var(--hgt-muted);background:var(--hgt-panel);letter-spacing:.12em}.record-title{font:15px Georgia,serif}.status{justify-self:start;padding:3px 8px;border:1px solid}.status.playing{color:#facc15}.status.completed{color:#4ade80}.status.abandoned{color:#f87171}.record button{justify-self:start;padding:6px 11px;color:var(--hgt-muted);background:transparent;border:1px solid var(--hgt-border);font:12px monospace}.empty{display:flex;flex-direction:column;align-items:center;gap:12px;padding:100px;color:var(--hgt-muted);font:13px monospace}.empty text:first-child{font-size:40px;color:var(--hgt-border)}
@media(max-width:600px){.page-head{padding:24px 18px}.title{font-size:29px}.stats{grid-template-columns:repeat(2,1fr)}.stats view{padding:16px 18px;border-bottom:1px solid var(--hgt-border)}.filters{padding:12px 18px}.records{margin:18px}.table-head{display:none}.record{grid-template-columns:1fr auto;gap:11px}.record>:nth-child(3),.record>:nth-child(4){color:var(--hgt-muted)}}
</style>
