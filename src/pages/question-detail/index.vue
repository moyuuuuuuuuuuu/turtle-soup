<script setup lang="ts">
import type { PublicQuestion } from '@/types/game'
import { gameApi, questionApi, roomApi, TurtleApiError } from '@/api/turtle'
import { useGameSocket } from '@/composables/useGameSocket'
import { useGameStore } from '@/store/gameStore'
import { usePlayerStore } from '@/store/playerStore'
import { supportsPublicRooms } from '@/utils/platform'
import { paperTagUrl, paperTextureUrl, questionCoverUrl } from '@/utils/questionCover'
import { applyPrettyQuestionDetailUrl } from '@/utils/questionRoute'

definePage({ name: 'question-detail', layout: 'tabbar', style: { 'navigationStyle': 'custom', 'mp-toutiao': { navigationStyle: 'default' } } })

const route = useRoute()
const router = useRouter()
const store = useGameStore()
const player = usePlayerStore()
const socket = useGameSocket()
const question = ref<PublicQuestion | null>(null)
const loading = ref(true)
const starting = ref(false)
const riskExpanded = ref(false)
const riskConfirmVisible = ref(false)
let riskConfirmResolve: ((confirmed: boolean) => void) | undefined

const questionId = computed(() => String(route.query.id || route.params.id || ''))
const roomId = computed(() => String(route.query.room_id || ''))

const difficultyLabel = (level: number) => ['未知', '简单', '普通', '中等', '困难', '极难'][level] || '未知'
const difficultyClass = (level: number) => (level <= 2 ? 'easy' : level === 3 ? 'mid' : 'hard')
const stars = (level: number) => '★'.repeat(Math.min(5, Math.max(1, level))) + '☆'.repeat(5 - Math.min(5, Math.max(1, level)))
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
const riskLevelLabel = (value: PublicQuestion['risk_level']) => riskLevelLabels[value] || value
const riskTypeText = (types: string[]) => (types.length ? types.map(type => riskTypeLabels[type] || type).join('、') : '无特别标注')
const riskTypeChips = computed(() => {
  const types = question.value?.risk_types || []
  return types.map(type => riskTypeLabels[type] || type)
})
const riskNote = computed(() => {
  const note = (question.value?.risk_note || question.value?.risk_warning || '').trim()
  if (!note)
    return ''
  const typeText = riskTypeText(question.value?.risk_types || [])
  if (note === typeText || riskTypeChips.value.includes(note))
    return ''
  return note
})
const estimateMinutes = computed(() => (question.value ? 8 + question.value.difficulty * 3 : 15))

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
    applyPrettyQuestionDetailUrl(questionId.value, roomId.value || undefined)
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
    if (player.user) {
      try {
        const joinedRooms = await roomApi.mine()
        for (const joinedRoom of joinedRooms)
          await socket.roomLeave(joinedRoom.id, 'switch_question')
      }
      catch (error) {
        if (!(error instanceof TurtleApiError) || !['auth.login_required', 'auth.token_invalid'].includes(error.code))
          throw error
        player.clear()
      }
    }
    store.setGame(await gameApi.create(question.value.id, confirmed))
    router.replace({ name: 'game', params: { id: store.current!.id } })
  }
  catch (error) {
    uni.showToast({ title: (error as Error).message || '开始游戏失败', icon: 'none' })
  }
  finally {
    starting.value = false
  }
}

function joinRoom() {
  if (!supportsPublicRooms)
    return
  router.push({ name: 'public-rooms' })
}

onMounted(async () => {
  await Promise.all([load(), player.restore()])
})
</script>

<template>
  <view class="detail-page">
    <view v-if="question" class="page-bg" aria-hidden="true">
      <image class="page-bg-img" :src="questionCoverUrl(question)" mode="aspectFill" />
      <view class="page-bg-fade" />
    </view>
    <wd-loading v-if="loading" class="loading" />
    <template v-else-if="question">
      <view class="detail-grid">
        <!-- Left: meta + paper soup -->
        <view class="detail-main">
          <view class="title-block">
            <text class="detail-title">
              {{ question.title }}
            </text>
            <view class="badge-row">
              <text class="badge" :class="difficultyClass(question.difficulty)">
                {{ difficultyLabel(question.difficulty) }}
              </text>
              <text v-if="question.risk_level !== 'safe'" class="badge risk" :class="question.risk_level">
                {{ riskLevelLabel(question.risk_level) }}
              </text>
            </view>
          </view>

          <view class="meta-row">
            <text class="meta-item">
              ⏱ 约 {{ estimateMinutes }} 分钟
            </text>
            <text class="meta-item">
              ◎ {{ question.play_count }} 人玩过
            </text>
            <text class="meta-item">
              ★ {{ stars(question.difficulty) }}
            </text>
            <text v-for="tag in question.tags" :key="tag.id" class="meta-tag">
              # {{ tag.name }}
            </text>
          </view>

          <!-- Paper surface -->
          <view class="paper">
            <view class="paper-sheet">
              <image class="paper-texture" :src="paperTextureUrl(question.id)" mode="aspectFill" />
              <view class="paper-veil" />
              <view class="paper-inner">
                <text class="paper-label">
                  汤面
                </text>
                <text class="paper-body">
                  {{ question.surface }}
                </text>
              </view>
            </view>
            <image class="paper-tape" src="/static/hgt/ui/tape.png" mode="aspectFit" />
            <image class="paper-tag" :src="paperTagUrl" mode="aspectFit" />
          </view>

          <view v-if="question.risk_level !== 'safe'" class="risk-panel" :class="[question.risk_level, { open: riskExpanded }]">
            <view class="risk-trigger" role="button" @click.stop="riskExpanded = !riskExpanded">
              <text>△ {{ riskLevelLabel(question.risk_level) }}</text>
              <text>{{ riskExpanded ? '收起 ↑' : '说明 ↓' }}</text>
            </view>
            <view v-if="riskExpanded" class="risk-detail">
              <text>风险类型：{{ riskTypeText(question.risk_types) }}</text>
              <text v-if="riskNote">
                风险说明：{{ riskNote }}
              </text>
            </view>
          </view>

          <view class="actions">
            <button class="btn-primary btn-main-cta" :loading="starting" @click="start">
              {{ starting ? '正在进入…' : roomId ? '与原队伍继续 →' : '开始推理 →' }}
            </button>
            <view v-if="supportsPublicRooms && player.user && !roomId" class="room-actions">
              <button class="btn-ghost" @click="joinRoom">
                加入房间
              </button>
            </view>
          </view>
          <text class="invite-note">
            {{ roomId ? '将保留原房间成员并直接开始下一题' : '默认单人模式 · 进入后可邀请队友' }}
          </text>
        </view>
      </view>
    </template>
    <view v-else class="empty">
      <image class="empty-img" src="/static/hgt/cover/cover_placeholder.png" mode="aspectFit" />
      <text>谜题不存在或已下架</text>
    </view>

    <HgtConfirmDialog
      v-if="question"
      v-model="riskConfirmVisible"
      eyebrow="风险说明"
      :title="riskLevelLabel(question.risk_level)"
      confirm-text="了解并继续"
      tone="warning"
      @confirm="settleRiskConfirmation(true)"
      @cancel="settleRiskConfirmation(false)"
    >
      <view class="risk-dialog-body">
        <view class="risk-dialog-row">
          <text class="risk-dialog-label hgt-mono">
            风险类型
          </text>
          <view class="risk-dialog-chips">
            <text v-for="chip in riskTypeChips" :key="chip" class="risk-dialog-chip">
              {{ chip }}
            </text>
            <text v-if="!riskTypeChips.length" class="risk-dialog-empty">
              无特别标注
            </text>
          </view>
        </view>
        <view v-if="riskNote" class="risk-dialog-row">
          <text class="risk-dialog-label hgt-mono">
            风险说明
          </text>
          <text class="risk-dialog-note">
            {{ riskNote }}
          </text>
        </view>
        <text class="risk-dialog-tip">
          {{ question.risk_level === 'restricted' ? '内容可能引起不适，确认后再进入。' : '题材可能偏沉重，确认后再进入。' }}
        </text>
      </view>
    </HgtConfirmDialog>
  </view>
</template>

<style scoped>
.detail-page {
  position: relative;
  box-sizing: border-box;
  min-height: 100%;
  padding: 40px 48px 64px;
  overflow: hidden;
  background: var(--hgt-bg);
  color: var(--hgt-text);
}

/* Cover becomes a top-anchored page background that fades downward */
.page-bg {
  position: absolute;
  inset: 0;
  z-index: 0;
  pointer-events: none;
  overflow: hidden;
}
.page-bg-img {
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: min(100%, 92vh);
}
.page-bg-fade {
  position: absolute;
  inset: 0;
  background: linear-gradient(
    180deg,
    color-mix(in srgb, var(--hgt-bg) 12%, transparent) 0%,
    color-mix(in srgb, var(--hgt-bg) 38%, transparent) 28%,
    color-mix(in srgb, var(--hgt-bg) 78%, transparent) 58%,
    var(--hgt-bg) 86%,
    var(--hgt-bg) 100%
  );
}
.loading {
  display: flex;
  position: relative;
  z-index: 1;
  min-height: 320px;
  margin: auto;
  align-items: center;
  justify-content: center;
}
.detail-grid {
  position: relative;
  z-index: 1;
  display: grid;
  width: min(var(--hgt-content-max), 100%);
  margin: 0 auto;
  /* 右侧留空给背景图 */
  grid-template-columns: minmax(0, 1.35fr) minmax(200px, 0.75fr);
  gap: 36px;
  align-items: start;
}
.detail-main {
  display: flex;
  min-width: 0;
  gap: 18px;
  flex-direction: column;
}
.title-block {
  display: flex;
  gap: 14px;
  flex-wrap: wrap;
  align-items: center;
}
.detail-title {
  color: var(--hgt-text);
  font-family: var(--hgt-font-display);
  font-size: 32px;
  font-weight: 600;
  line-height: 1.25;
  letter-spacing: 0.04em;
}
.badge-row {
  display: flex;
  gap: 8px;
}
.badge {
  padding: 4px 10px;
  border-radius: var(--hgt-radius-xs);
  font-size: 12px;
  line-height: 1.4;
}
.badge.easy {
  background: rgba(94, 135, 135, 0.25);
  color: var(--hgt-success-text);
}
.badge.mid {
  background: rgba(196, 154, 85, 0.22);
  color: var(--hgt-warning);
}
.badge.hard {
  background: rgba(201, 74, 85, 0.22);
  color: var(--hgt-danger);
}
.badge.risk.caution {
  background: rgba(196, 154, 85, 0.22);
  color: var(--hgt-warning);
}
.badge.risk.restricted {
  background: rgba(201, 74, 85, 0.22);
  color: var(--hgt-danger);
}
.meta-row {
  display: flex;
  flex-wrap: wrap;
  gap: 8px 14px;
  color: var(--hgt-text-2);
  font-size: 13px;
}
.meta-tag {
  color: var(--hgt-brand);
}

/* Paper soup */
.paper {
  position: relative;
  min-height: 220px;
  padding-top: 10px;
}
.paper-sheet {
  position: relative;
  min-height: 220px;
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: var(--hgt-radius-md);
  overflow: hidden;
  background: var(--hgt-paper);
  box-shadow: var(--hgt-shadow-float);
}
.paper-texture {
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  opacity: 0.92;
}
.paper-texture :deep(uni-image),
.paper-texture :deep(.uni-image),
.paper-texture :deep(.uni-image-wrapper) {
  width: 100%;
  height: 100%;
}
.paper-tape {
  position: absolute;
  z-index: 2;
  top: 0;
  left: 50%;
  width: 104px;
  height: 32px;
  transform: translateX(-50%) rotate(-2deg);
  opacity: 0.92;
  pointer-events: none;
}
.paper-tag {
  position: absolute;
  z-index: 2;
  top: 22px;
  right: 6px;
  width: 34px;
  height: 84px;
  opacity: 0.88;
  pointer-events: none;
}
.paper-veil {
  position: absolute;
  inset: 0;
  pointer-events: none;
  background: linear-gradient(
    160deg,
    color-mix(in srgb, var(--hgt-paper) 18%, transparent),
    color-mix(in srgb, var(--hgt-paper) 42%, transparent)
  );
}
.paper-inner {
  position: relative;
  z-index: 1;
  display: flex;
  min-height: 220px;
  padding: 28px 40px 28px 32px;
  gap: 14px;
  box-sizing: border-box;
  flex-direction: column;
  justify-content: center;
}
.paper-label {
  color: #5a5e48;
  font-size: 12px;
  letter-spacing: 0.28em;
}
.paper-body {
  color: var(--hgt-paper-ink);
  font-family: var(--hgt-font-display);
  font-size: 18px;
  line-height: 1.9;
  white-space: pre-wrap;
}

.risk-panel {
  border: 1px solid var(--hgt-border);
  border-radius: var(--hgt-radius-sm);
  overflow: hidden;
}
.risk-panel.caution {
  border-color: rgba(196, 154, 85, 0.55);
}
.risk-panel.restricted {
  border-color: rgba(201, 74, 85, 0.55);
}
.risk-trigger {
  display: flex;
  padding: 12px 14px;
  align-items: center;
  justify-content: space-between;
  background: var(--hgt-card-2);
  color: var(--hgt-text-2);
  font-size: 12px;
  letter-spacing: 0.08em;
}
.risk-detail {
  display: flex;
  padding: 12px 14px;
  gap: 8px;
  flex-direction: column;
  background: var(--hgt-card);
  color: var(--hgt-text-2);
  font-size: 13px;
  line-height: 1.7;
}

.risk-dialog-body {
  display: flex;
  padding: 14px 16px;
  gap: 14px;
  flex-direction: column;
  border: 1px solid rgba(196, 154, 85, 0.28);
  border-radius: var(--hgt-radius-sm);
  background: rgba(196, 154, 85, 0.08);
}
.risk-dialog-row {
  display: flex;
  gap: 10px;
  flex-direction: column;
}
.risk-dialog-label {
  color: var(--hgt-warning);
  font-size: 10px;
  letter-spacing: 0.18em;
}
.risk-dialog-chips {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}
.risk-dialog-chip {
  padding: 4px 10px;
  border: 1px solid rgba(196, 154, 85, 0.4);
  border-radius: var(--hgt-radius-full);
  background: rgba(196, 154, 85, 0.12);
  color: var(--hgt-text);
  font-size: 12px;
  line-height: 1.4;
}
.risk-dialog-empty {
  color: var(--hgt-text-2);
  font-size: 13px;
}
.risk-dialog-note {
  color: var(--hgt-text);
  font-size: 13px;
  line-height: 1.7;
}
.risk-dialog-tip {
  color: var(--hgt-text-3);
  font-size: 12px;
  line-height: 1.6;
}

.actions {
  display: flex;
  margin-top: 8px;
  gap: 12px;
}
.btn-primary {
  display: flex;
  min-width: 180px;
  height: 48px;
  margin: 0;
  padding: 0 28px;
  border: 0;
  border-radius: var(--hgt-radius-sm);
  align-items: center;
  justify-content: center;
  background: var(--hgt-brand);
  color: var(--hgt-on-brand);
  font-size: 15px;
  font-weight: 600;
  letter-spacing: 0.08em;
}
.btn-primary::after {
  border: 0;
}
.btn-main-cta {
  min-width: 220px;
  box-shadow: var(--hgt-shadow-md);
}
.btn-ghost {
  display: flex;
  height: 48px;
  margin: 0;
  padding: 0 22px;
  border: 1px solid var(--hgt-border-soft);
  border-radius: var(--hgt-radius-sm);
  align-items: center;
  justify-content: center;
  background: transparent;
  color: var(--hgt-text);
  font-size: 14px;
}
.btn-ghost::after {
  border: 0;
}
.room-actions {
  display: flex;
  flex: 1;
}
.room-actions .btn-ghost {
  width: 100%;
}
.invite-note {
  color: var(--hgt-text-3);
  font-size: 12px;
}

.empty {
  display: flex;
  position: relative;
  z-index: 1;
  min-height: 320px;
  align-items: center;
  justify-content: center;
  flex-direction: column;
  gap: 12px;
  color: var(--hgt-text-2);
}
.empty .empty-img {
  width: 180px;
  height: 180px;
  border-radius: var(--hgt-radius-lg);
  filter: drop-shadow(0 6px 18px rgba(4, 12, 14, 0.35));
}

/* 桌面：封面偏右，正文区保持可读 */
@media (min-width: 768px) {
  .page-bg-img {
    left: auto;
    right: 0;
    width: min(100%, 68%);
  }
  .page-bg-fade {
    background:
      linear-gradient(
        90deg,
        var(--hgt-bg) 0%,
        color-mix(in srgb, var(--hgt-bg) 88%, transparent) 34%,
        color-mix(in srgb, var(--hgt-bg) 42%, transparent) 62%,
        color-mix(in srgb, var(--hgt-bg) 12%, transparent) 100%
      ),
      linear-gradient(
        180deg,
        transparent 0%,
        color-mix(in srgb, var(--hgt-bg) 28%, transparent) 40%,
        color-mix(in srgb, var(--hgt-bg) 82%, transparent) 72%,
        var(--hgt-bg) 100%
      );
  }
}

@media (max-width: 1199px) {
  .detail-grid {
    grid-template-columns: minmax(0, 1fr) minmax(0, 0.55fr);
  }
}
@media (max-width: 767px) {
  .detail-page {
    padding: 24px 16px 40px;
  }
  .detail-grid {
    grid-template-columns: 1fr;
  }
  .detail-title {
    font-size: 24px;
  }
  .paper-inner {
    min-height: 200px;
    padding: 22px 28px 22px 20px;
  }
  .paper-tag {
    width: 28px;
    height: 70px;
  }
  .paper-body {
    font-size: 16px;
  }
  .actions {
    flex-direction: column;
  }
  .btn-main-cta {
    width: 100%;
  }
  .page-bg-img {
    height: min(100%, 70vh);
  }
}
</style>
