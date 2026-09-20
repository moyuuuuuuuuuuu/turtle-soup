<script setup lang="ts">
import type { PublicQuestion } from '@/types/game'
import { ensureAnonymousSession, gameApi, questionApi, roomApi, TurtleApiError } from '@/api/turtle'
import DepthBadge from '@/components/DepthBadge.vue'
import { useGameSocket } from '@/composables/useGameSocket'
import { useGameStore } from '@/store/gameStore'
import { usePlayerStore } from '@/store/playerStore'
import { difficultyLabel, estimateMinutes, formatPlayCount } from '@/utils/depth'
import { hgtConfirm } from '@/utils/feedback'
import { applyPrettyQuestionDetailUrl, openQuestionDetail } from '@/utils/questionRoute'

definePage({ name: 'question-detail', layout: 'tabbar', style: { 'navigationStyle': 'custom', 'mp-toutiao': { navigationStyle: 'default' } } })

const route = useRoute()
const router = useRouter()
const store = useGameStore()
const player = usePlayerStore()
const socket = useGameSocket()

const question = ref<PublicQuestion | null>(null)
const loading = ref(true)
const starting = ref(false)
const randomLoading = ref(false)
const shareLoading = ref(false)

const questionId = computed(() => String(route.query.id || route.params.id || ''))
const roomId = computed(() => String(route.query.room_id || ''))

const metaLine = computed(() => {
  const q = question.value
  if (!q)
    return ''
  const parts: string[] = []
  const minutes = estimateMinutes(q.difficulty, q.play_count)
  const plays = formatPlayCount(q.play_count)
  if (minutes)
    parts.push(minutes)
  if (plays)
    parts.push(plays)
  return parts.join(' · ')
})

const categoryLine = computed(() => {
  const q = question.value
  if (!q)
    return ''
  const parts: string[] = []
  const diff = difficultyLabel(q.difficulty)
  if (diff && diff !== '未知')
    parts.push(diff)
  const tags = (q.tags || []).slice(0, 2).map(tag => tag.name)
  if (tags.length)
    parts.push(tags.join(' · '))
  return parts.join(' · ')
})

const questionNo = computed(() => {
  const id = question.value?.id || questionId.value
  if (!id)
    return ''
  const digits = id.replace(/\D/g, '')
  return digits ? `#${digits.slice(-4)}` : `#${id}`
})

function goBackToLibrary() {
  const pages = getCurrentPages()
  if (pages.length > 1) {
    uni.navigateBack()
    return
  }
  router.replace({ name: 'questions' })
}

async function load() {
  loading.value = true
  try {
    question.value = await questionApi.read(questionId.value)
    applyPrettyQuestionDetailUrl(questionId.value, roomId.value || undefined)
  }
  catch (error) {
    question.value = null
    uni.showToast({ title: (error as Error).message || '题目加载失败', icon: 'none' })
  }
  finally {
    loading.value = false
  }
}

function requestRiskConfirmation(): Promise<boolean> {
  const riskText = question.value?.risk_note || question.value?.risk_warning || ''
  return hgtConfirm({
    eyebrow: 'RISK',
    title: '风险提示',
    description: riskText || '本题题材可能偏沉重，确认后再进入。',
    confirmText: '了解并继续',
    cancelText: '再想想',
    tone: 'warning',
  })
}

async function start() {
  if (!question.value || starting.value)
    return
  let confirmed = false
  if (question.value.risk_level !== 'safe') {
    confirmed = await requestRiskConfirmation()
    if (!confirmed)
      return
  }
  starting.value = true
  try {
    await ensureAnonymousSession()
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

async function loadRandom() {
  if (randomLoading.value)
    return
  randomLoading.value = true
  try {
    const next = await questionApi.random()
    await openQuestionDetail({
      id: next.id,
      roomId: roomId.value || undefined,
      replace: true,
    })
  }
  catch (error) {
    uni.showToast({ title: (error as Error).message || '换题失败', icon: 'none' })
  }
  finally {
    randomLoading.value = false
  }
}

async function shareQuestion() {
  if (!question.value || shareLoading.value)
    return
  shareLoading.value = true
  try {
    const title = question.value.title
    let shareText = title
    // #ifdef H5
    const url = window.location.href
    if (typeof navigator !== 'undefined' && typeof navigator.share === 'function') {
      await navigator.share({ title, url })
      return
    }
    shareText = `${title} ${url}`
    // #endif
    await new Promise<void>((resolve, reject) => {
      uni.setClipboardData({
        data: shareText,
        success: () => resolve(),
        fail: () => reject(new Error('复制失败')),
      })
    })
    uni.showToast({ title: '已复制题目信息', icon: 'none' })
  }
  catch {
    // 用户取消系统分享时静默
  }
  finally {
    shareLoading.value = false
  }
}

onMounted(async () => {
  await Promise.all([load(), player.restore()])
})
</script>

<template>
  <view class="detail-page">
    <view class="water-glow" aria-hidden="true" />

    <view v-if="loading" class="loading-block">
      <view class="sk-line sk-meta" />
      <view class="sk-line sk-title" />
      <view class="sk-line sk-body" />
      <view class="sk-line sk-body short" />
    </view>

    <view v-else-if="question" class="detail-shell">
      <view class="top-bar">
        <button class="back-btn" @click="goBackToLibrary">
          ← 返回题库
        </button>
        <button class="share-btn" @click="shareQuestion">
          分享
        </button>
      </view>

      <view class="detail-body fade-in">
        <view class="depth-block">
          <DepthBadge :difficulty="question.difficulty" />
        </view>

        <text class="detail-title">
          {{ question.title }}
        </text>

        <view class="tag-row">
          <text v-if="categoryLine" class="diff-text">
            {{ categoryLine }}
          </text>
        </view>

        <view class="rule-line" aria-hidden="true" />

        <view class="surface-block">
          <text class="surface-label">
            汤面
          </text>
          <text class="surface-text">
            {{ question.surface }}
          </text>
        </view>

        <view class="rule-line" aria-hidden="true" />

        <text v-if="metaLine" class="meta-line">
          {{ metaLine }}
        </text>

        <view class="cta-block">
          <button class="cta-btn" :loading="starting" :disabled="starting || randomLoading" @click="start">
            {{ starting ? '正在进入…' : roomId ? '与原队伍继续 →' : '开始推理 →' }}
          </button>
          <view class="secondary-row">
            <text class="secondary-label">
              不感兴趣？
            </text>
            <button class="random-btn" :disabled="randomLoading || starting" @click="loadRandom">
              {{ randomLoading ? '寻找中…' : '随机换一题' }}
            </button>
            <text class="host-note host-note-inline">
              ◇ 主持人只会回答「是」「不是」或「无关」
            </text>
          </view>
        </view>
      </view>

      <text class="question-no">
        题目编号 {{ questionNo }}
      </text>
    </view>

    <view v-else class="empty">
      <text class="empty-mark">
        ◇
      </text>
      <text class="empty-title">
        谜题不存在或已下架
      </text>
      <button class="empty-action" @click="goBackToLibrary">
        返回题库
      </button>
    </view>
  </view>
</template>

<style scoped>
.detail-page {
  position: relative;
  box-sizing: border-box;
  min-height: 100%;
  padding: 28px 24px 48px;
  overflow: hidden;
  background-color: #04181d;
  background-image:
    linear-gradient(180deg,
      rgba(4, 24, 29, 0.18) 0%,
      rgba(4, 24, 29, 0.28) 40%,
      rgba(4, 24, 29, 0.55) 72%,
      rgba(4, 24, 29, 0.82) 90%,
      #04181d 100%),
    url('/static/hgt/bg/bg_deep_ocean.jpg');
  background-position: center 22%;
  background-repeat: no-repeat;
  background-size: cover;
  color: var(--hgt-text);
}

.detail-page::after {
  content: '';
  position: absolute;
  inset: 0;
  pointer-events: none;
  background: radial-gradient(
    ellipse 80% 55% at 50% 30%,
    transparent 0%,
    rgba(4, 24, 29, 0.15) 70%,
    rgba(4, 24, 29, 0.35) 100%
  );
}

.water-glow {
  position: absolute;
  top: -80px;
  left: 50%;
  width: min(920px, 140%);
  height: 280px;
  transform: translateX(-50%);
  pointer-events: none;
  background: radial-gradient(
    ellipse 70% 60% at 50% 0%,
    rgba(94, 196, 184, 0.16) 0%,
    rgba(94, 196, 184, 0.05) 42%,
    transparent 72%
  );
}

.detail-shell {
  position: relative;
  z-index: 1;
  display: flex;
  width: min(1120px, 100%);
  min-height: calc(100vh - 120px);
  margin: 0 auto;
  flex-direction: column;
}

.top-bar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 28px;
}

.back-btn,
.share-btn {
  height: 32px;
  margin: 0;
  padding: 0 4px;
  border: 0;
  background: transparent;
  color: var(--hgt-text-3);
  font-size: 13px;
}

.back-btn::after,
.share-btn::after {
  border: 0;
}

.back-btn:active,
.share-btn:active {
  color: var(--hgt-text-2);
}

.detail-body {
  display: flex;
  flex: 1;
  flex-direction: column;
  align-items: flex-start;
}

.fade-in {
  animation: hgt-detail-in 360ms var(--hgt-ease-out) both;
}

@keyframes hgt-detail-in {
  from {
    opacity: 0;
    transform: translateY(6px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.depth-block {
  margin-bottom: 18px;
}

.detail-title {
  color: var(--hgt-text-bright);
  font-family: var(--hgt-font-display);
  font-size: 34px;
  font-weight: 600;
  line-height: 1.3;
  letter-spacing: 0.02em;
}

.tag-row {
  margin-top: 12px;
  min-height: 20px;
}

.diff-text,
.tag-text {
  color: var(--hgt-text-3);
  font-size: 13px;
  letter-spacing: 0.04em;
}

.rule-line {
  width: 100%;
  height: 1px;
  margin: 22px 0;
  background: var(--hgt-border-soft);
}

.surface-block {
  box-sizing: border-box;
  width: 100%;
  max-width: none;
  min-height: 240px;
  padding: 24px 28px;
  border: 1px solid rgba(130, 220, 210, 0.05);
  border-radius: 24px;
  background:
    radial-gradient(ellipse 90% 70% at 30% 20%, rgba(94, 196, 184, 0.06), transparent 55%),
    rgba(12, 40, 46, 0.08);
  box-shadow:
    inset 0 0 48px rgba(4, 20, 24, 0.25),
    0 0 0 1px rgba(4, 20, 24, 0.08);
  backdrop-filter: blur(6px);
  -webkit-backdrop-filter: blur(6px);
}

.surface-label {
  display: block;
  margin-bottom: 14px;
  color: var(--hgt-text-3);
  font-family: var(--hgt-font-mono);
  font-size: 11px;
  letter-spacing: 0.28em;
}

.surface-text {
  color: var(--hgt-text-bright);
  font-family: var(--hgt-font-display);
  font-size: 18px;
  line-height: 2;
  white-space: pre-wrap;
}

.meta-line {
  color: var(--hgt-text-3);
  font-size: 13px;
  letter-spacing: 0.02em;
}

.cta-block {
  display: flex;
  width: 100%;
  margin-top: 28px;
  flex-direction: column;
  align-items: flex-start;
  gap: 12px;
}

.cta-btn {
  display: flex;
  min-width: 200px;
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

.cta-btn::after {
  border: 0;
}

.cta-btn[disabled] {
  opacity: 0.7;
}

.host-note {
  color: var(--hgt-text-3);
  font-size: 12px;
  line-height: 1.6;
}

.host-note-inline {
  margin-left: 8px;
  opacity: 0.85;
}

.secondary-row {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;
}

.secondary-label {
  color: var(--hgt-text-3);
  font-size: 13px;
}

.random-btn {
  height: 32px;
  margin: 0;
  padding: 0 2px;
  border: 0;
  background: transparent;
  color: var(--hgt-brand);
  font-size: 13px;
}

.random-btn::after {
  border: 0;
}

.random-btn[disabled] {
  opacity: 0.5;
}

.question-no {
  margin-top: auto;
  padding-top: 36px;
  color: var(--hgt-text-3);
  font-family: var(--hgt-font-mono);
  font-size: 11px;
  letter-spacing: 0.12em;
  opacity: 0.45;
}

.loading-block {
  position: relative;
  z-index: 1;
  display: flex;
  width: min(1120px, 100%);
  margin: 48px auto 0;
  flex-direction: column;
  gap: 14px;
}

.sk-line {
  height: 12px;
  border-radius: 2px;
  background: linear-gradient(
    90deg,
    rgba(232, 244, 242, 0.05),
    rgba(232, 244, 242, 0.1),
    rgba(232, 244, 242, 0.05)
  );
}

.sk-meta {
  width: 120px;
}

.sk-title {
  width: 70%;
  height: 28px;
  margin-top: 10px;
}

.sk-body {
  width: 100%;
  height: 16px;
  margin-top: 24px;
}

.sk-body.short {
  width: 62%;
  margin-top: 0;
}

.empty {
  position: relative;
  z-index: 1;
  display: flex;
  min-height: 320px;
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
  color: var(--hgt-text-2);
  font-family: var(--hgt-font-display);
  font-size: 16px;
}

.empty-action {
  height: 34px;
  margin-top: 8px;
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

@media (max-width: 767px) {
  .detail-page {
    padding: 20px 16px 40px;
  }

  .detail-title {
    font-size: 26px;
  }

  .surface-block {
    min-height: 200px;
    padding: 18px 16px;
  }

  .surface-text {
    font-size: 16px;
  }

  .cta-btn {
    width: 100%;
    min-width: 0;
  }

  .cta-block {
    width: 100%;
  }
}

@media (min-width: 1024px) {
  .detail-shell {
    min-height: calc(100vh - 140px);
    justify-content: flex-start;
  }

  .detail-body {
    justify-content: flex-start;
  }
}
</style>
