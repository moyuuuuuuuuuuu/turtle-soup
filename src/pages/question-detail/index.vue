<script setup lang="ts">
import type { PublicQuestion } from '@/types/game'
import { gameApi, questionApi, roomApi } from '@/api/turtle'
import { useGameStore } from '@/store/gameStore'
import { usePlayerStore } from '@/store/playerStore'

definePage({ name: 'question-detail', layout: 'tabbar', style: { navigationStyle: 'custom' } })
const route = useRoute()
const router = useRouter()
const store = useGameStore()
const player = usePlayerStore()
const question = ref<PublicQuestion | null>(null)
const loading = ref(true)
const starting = ref(false)
const riskExpanded = ref(false)
const riskConfirmVisible = ref(false)
let riskConfirmResolve: ((confirmed: boolean) => void) | undefined
const questionId = computed(() => String(route.params.id || route.query.id || ''))
const roomId = computed(() => String(route.query.room_id || ''))
const difficultyStars = (value: number) => `${'★'.repeat(Math.max(0, Math.min(5, value)))}${'☆'.repeat(Math.max(0, 5 - value))}`
const riskLevelLabels: Record<PublicQuestion['risk_level'], string> = { safe: '安全', caution: '需注意', restricted: '受限内容' }
const riskTypeLabels: Record<string, string> = { death: '死亡', violence: '暴力', gore: '血腥', self_harm: '自伤', sexual: '性内容', child_safety: '未成年人', discrimination: '歧视', illegal: '违法', substance: '成瘾物', other: '其他' }
const riskLevelLabel = (value: PublicQuestion['risk_level']) => riskLevelLabels[value] || value
const riskTypeText = (types: string[]) => types.length ? types.map(type => riskTypeLabels[type] || type).join('、') : '无特别标注'

function requestRiskConfirmation() {
  riskConfirmVisible.value = true
  return new Promise<boolean>((resolve) => {
    riskConfirmResolve = resolve
  })
}

function settleRiskConfirmation(confirmed: boolean) {
  riskConfirmVisible.value = false
  const resolve = riskConfirmResolve
  riskConfirmResolve = undefined
  resolve?.(confirmed)
}

async function load() {
  try {
    question.value = await questionApi.read(questionId.value)
  }
  finally {
    loading.value = false
  }
}

async function start() {
  if (!question.value)
    return
  let confirmed = false
  if (question.value.risk_level !== 'safe') {
    confirmed = await requestRiskConfirmation()
    if (!confirmed)
      return
  }
  starting.value = true
  try {
    if (roomId.value) {
      const room = await roomApi.next(roomId.value, question.value.id, confirmed)
      if (!room.game_id)
        throw new Error('房间尚未关联游戏，请稍后重试')
      router.replace({ name: 'game', params: { id: room.game_id } })
      return
    }
    store.setGame(await gameApi.create(question.value.id, confirmed))
    router.replace({ name: 'game', params: { id: store.current!.id } })
  }
  finally {
    starting.value = false
  }
}

function joinRoom() {
  router.push({ name: 'public-rooms' })
}

onMounted(async () => {
  await Promise.all([load(), player.restore()])
})
</script>

<template>
  <view class="question-page">
    <wd-loading v-if="loading" class="loading" />
    <template v-else-if="question">
      <view class="question-shell">
        <text class="hgt-mono eyebrow">
          ◉ 谜题档案 · <text class="stars" :aria-label="`难度 ${question.difficulty} 星`">
            {{ difficultyStars(question.difficulty) }}
          </text>
        </text>
        <view class="title-row">
          <text class="hgt-display title">
            {{ question.title }}
          </text><text v-if="question.risk_level !== 'safe'" class="risk-desktop hgt-mono" :class="question.risk_level">
            {{ riskLevelLabel(question.risk_level) }}
          </text>
        </view>
        <view class="rule" />
        <text class="surface">
          {{ question.surface }}
        </text>
        <view v-if="question.risk_level !== 'safe'" class="risk-panel" :class="[question.risk_level, { open: riskExpanded }]">
          <view class="risk-trigger hgt-mono" role="button" @click.stop="riskExpanded = !riskExpanded">
            <text>△ {{ riskLevelLabel(question.risk_level) }}</text>
            <text>{{ riskExpanded ? '收起说明 ↑' : '查看说明 ↓' }}</text>
          </view>
          <view v-if="riskExpanded" class="risk-detail">
            <text>风险类型：{{ riskTypeText(question.risk_types) }}</text>
            <text>风险说明：{{ question.risk_note || question.risk_warning || '暂无具体说明' }}</text>
          </view>
        </view>
        <view class="meta hgt-mono">
          <text v-for="tag in question.tags" :key="tag.id">
            # {{ tag.name }}
          </text>
        </view>
        <view class="actions">
          <button class="start hgt-mono" :loading="starting" @click="start">
            {{ starting ? '正在进入…' : roomId ? '与原队伍继续 →' : '开始推理 →' }}
          </button>
          <view v-if="player.user && !roomId" class="room-actions">
            <button class="room-action hgt-mono" @click="joinRoom">
              加入房间
            </button>
          </view>
        </view>
        <text class="invite-note hgt-mono">
          {{ roomId ? '将保留原房间成员并直接开始下一题' : '默认单人模式 · 进入后可邀请队友' }}
        </text>
      </view>
    </template>
    <HgtConfirmDialog
      v-if="question"
      v-model="riskConfirmVisible"
      eyebrow="风险说明"
      :title="riskLevelLabel(question.risk_level)"
      :description="`风险类型：${riskTypeText(question.risk_types)}\n风险说明：${question.risk_note || question.risk_warning || '暂无具体说明'}`"
      confirm-text="了解并继续"
      tone="warning"
      @confirm="settleRiskConfirmation(true)"
      @cancel="settleRiskConfirmation(false)"
    />
  </view>
</template>

<style scoped>
.question-page{box-sizing:border-box;display:flex;min-height:100vh;padding:72px 8vw;align-items:center;background:var(--background);color:var(--foreground)}.loading{margin:auto}.question-shell{width:min(760px,100%)}.eyebrow,.meta,.invite-note{font-size:11px;letter-spacing:.17em;color:var(--muted-foreground)}.title-row{display:flex;margin-top:24px;gap:20px;align-items:flex-start}.title{font-size:clamp(38px,5vw,68px);line-height:1.15;letter-spacing:.03em}.risk-desktop{margin-top:10px;padding:5px 8px;border:1px solid;font-size:10px;white-space:nowrap}.risk-desktop.caution{border-color:#d97706;color:#d97706}.risk-desktop.restricted{border-color:#dc2626;color:#dc2626}.rule{width:72px;height:1px;margin:30px 0;background:var(--foreground)}.surface{display:block;max-width:680px;font-size:16px;line-height:2;color:var(--muted-foreground);white-space:pre-wrap}.risk-panel{display:none;max-width:680px;margin-top:20px;border:1px solid}.risk-panel.caution{border-color:rgba(217,119,6,.55);color:#b45309}.risk-panel.restricted{border-color:rgba(220,38,38,.55);color:#dc2626}.risk-trigger{display:flex;padding:10px 12px;align-items:center;justify-content:space-between;background:color-mix(in srgb,currentColor 7%,transparent);font-size:11px;letter-spacing:.08em;cursor:pointer}.risk-detail{display:flex;padding:11px 12px;border-top:1px solid currentColor;flex-direction:column;gap:6px;color:var(--muted-foreground);font-size:12px;line-height:1.7}.meta{display:flex;flex-wrap:wrap;margin-top:26px;gap:9px 14px}.meta>text{flex:none;white-space:nowrap}.actions{display:flex;margin-top:44px;gap:14px}.start,.room-action{display:flex;height:48px;margin:0;padding:0;border:1px solid var(--foreground);border-radius:0;align-items:center;justify-content:center;font-size:12px;letter-spacing:.16em}.start{width:220px;background:var(--foreground);color:var(--background);letter-spacing:.2em}.room-actions{display:flex;flex:1}.room-action{width:100%;background:transparent;color:var(--foreground)}.start::after,.room-action::after{border:0}.invite-note{display:block;margin-top:14px;font-size:10px}@media(max-width:767px){.question-page{padding:52px 28px;align-items:flex-start}.title-row{display:block}.title{font-size:42px}.risk-desktop{display:none}.surface{font-size:14px}.risk-panel{display:block;margin-top:18px}.risk-trigger{min-height:22px;padding:11px 12px}.actions{flex-direction:column}.start{width:100%}.room-actions{width:100%}.meta{gap:8px 12px;letter-spacing:.1em}}
.stars{display:inline-block;color:var(--foreground);font-size:16px;line-height:1;letter-spacing:.12em;vertical-align:middle}
</style>
