<script setup lang="ts">
import type { PublicQuestion, RoomSnapshot } from '@/types/game'
import { gameApi, questionApi, roomApi, TurtleApiError } from '@/api/turtle'
import { useGameSocket } from '@/composables/useGameSocket'
import { useGameStore } from '@/store/gameStore'
import { usePlayerStore } from '@/store/playerStore'
import { resolveAssetUrl } from '@/utils/assetUrl'
import { supportsPublicRooms } from '@/utils/platform'

definePage({ name: 'rooms', layout: 'tabbar', style: { 'navigationStyle': 'custom', 'mp-toutiao': { navigationStyle: 'default' } } })

const heroBgSrc = resolveAssetUrl('/static/hgt/bg/bg_deep_ocean.jpg')

type Screen = 'actions' | 'join' | 'create' | 'created'

const ROOM_STATUS_LABEL: Record<string, string> = {
  waiting: '等待中',
  playing: '推理中',
  finished: '已结束',
  closed: '已关闭',
}

const route = useRoute()
const router = useRouter()
const player = usePlayerStore()
const gameStore = useGameStore()
const socket = useGameSocket()

const unavailable = ref(false)
const loading = ref(true)
const mine = ref<RoomSnapshot[]>([])
const inviteCode = ref('')
const fallbackQuestionId = ref('')
const joining = ref(false)

const screen = ref<Screen>('actions')
const createBusy = ref(false)
const questions = ref<PublicQuestion[]>([])
const questionsLoading = ref(false)
const selectedQuestionId = ref('')
const maxPlayers = ref(6)
const visibility = ref<'private' | 'public'>(supportsPublicRooms ? 'public' : 'private')
const createdRoom = ref<RoomSnapshot | null>(null)

const roomStatusLabel = (status: string) => ROOM_STATUS_LABEL[status] || status || '未知'

onLoad((options) => {
  inviteCode.value = String(options?.invite_code || route.query.invite_code || '')
  fallbackQuestionId.value = String(options?.question_id || route.query.question_id || '')
})

function inviteRedirectPath() {
  const query = [`invite_code=${encodeURIComponent(inviteCode.value)}`]
  if (fallbackQuestionId.value)
    query.push(`question_id=${encodeURIComponent(fallbackQuestionId.value)}`)
  return `/pages/rooms/index?${query.join('&')}`
}

function goLoginForInvite() {
  if (inviteCode.value)
    uni.showToast({ title: '登录后即可加入房间一起玩', icon: 'none' })
  const loginUrl = inviteCode.value
    ? `/pages/login/index?redirect=${encodeURIComponent(inviteRedirectPath())}`
    : '/pages/login/index'
  // #ifdef H5
  router.replace({
    path: '/pages/login/index',
    query: inviteCode.value ? { redirect: encodeURIComponent(inviteRedirectPath()) } : {},
  })
  // #endif
  // #ifndef H5
  uni.redirectTo({
    url: loginUrl,
    fail: () => uni.showToast({ title: '无法打开登录页', icon: 'none' }),
  })
  // #endif
}

function enterRoomGame(room: RoomSnapshot) {
  if (!room.game_id) {
    uni.showToast({ title: '房间尚未关联游戏，请稍后重试', icon: 'none' })
    return
  }
  socket.adoptRoom(room)
  router.push({ name: 'game', params: { id: room.game_id } })
}

async function startFallbackGame(questionId: string) {
  gameStore.setGame(await gameApi.create(questionId))
  uni.showToast({ title: '房间已结束，已切换为单人模式', icon: 'none' })
  router.replace({ name: 'game', params: { id: gameStore.current!.id } })
}

async function loadMine() {
  mine.value = await roomApi.mine()
}

async function join(id?: string) {
  if (joining.value)
    return
  joining.value = true
  try {
    const room = await roomApi.join(id ? { id } : { invite_code: inviteCode.value.trim().toUpperCase() })
    await loadMine().catch(() => {})
    enterRoomGame(room)
  }
  catch (error) {
    if (error instanceof TurtleApiError && error.code === 'room.status_invalid') {
      const fallbackId = fallbackQuestionId.value || (await roomApi.resolveQuestion(inviteCode.value.trim())).question_id
      await startFallbackGame(fallbackId)
      return
    }
    uni.showToast({ title: (error as Error).message, icon: 'none' })
  }
  finally {
    joining.value = false
  }
}

async function joinByCode() {
  const code = inviteCode.value.trim().toUpperCase()
  if (!code) {
    uni.showToast({ title: '请输入邀请码', icon: 'none' })
    return
  }
  inviteCode.value = code
  await join()
}

async function loadQuestions() {
  questionsLoading.value = true
  try {
    const result = await questionApi.list({ page: 1, page_size: 20 })
    questions.value = result.items || []
    if (!selectedQuestionId.value && questions.value[0])
      selectedQuestionId.value = questions.value[0].id
  }
  catch (error) {
    uni.showToast({ title: (error as Error).message || '题目加载失败', icon: 'none' })
  }
  finally {
    questionsLoading.value = false
  }
}

async function openCreate() {
  screen.value = 'create'
  if (!questions.value.length)
    await loadQuestions()
}

function copyInvite() {
  const code = createdRoom.value?.invite_code
  if (!code)
    return
  uni.setClipboardData({
    data: code,
    success: () => uni.showToast({ title: '邀请码已复制', icon: 'success' }),
  })
}

async function createRoom() {
  if (createBusy.value)
    return
  if (!selectedQuestionId.value) {
    uni.showToast({ title: '请先选择题目', icon: 'none' })
    return
  }
  createBusy.value = true
  try {
    const game = await gameApi.create(selectedQuestionId.value)
    const room = await roomApi.create({
      game_id: game.id,
      max_players: Number(maxPlayers.value) || 6,
      visibility: supportsPublicRooms ? visibility.value : 'private',
    })
    createdRoom.value = room
    screen.value = 'created'
    await loadMine().catch(() => {})
  }
  catch (error) {
    if (error instanceof TurtleApiError && ['auth.login_required', 'auth.token_invalid'].includes(error.code)) {
      player.clear()
      uni.showToast({ title: '请先登录', icon: 'none' })
      router.push({ name: 'player-login', query: { redirect: '/pages/rooms/index' } })
      return
    }
    uni.showToast({ title: (error as Error).message || '创建房间失败', icon: 'none' })
  }
  finally {
    createBusy.value = false
  }
}

onMounted(async () => {
  if (!supportsPublicRooms) {
    unavailable.value = true
    loading.value = false
    return
  }
  try {
    await player.restore()
    if (!player.user) {
      goLoginForInvite()
      return
    }
    if (inviteCode.value) {
      await join()
      return
    }
    await loadMine()
  }
  catch (error) {
    uni.showToast({ title: (error as Error).message, icon: 'none' })
  }
  finally {
    loading.value = false
  }
})
</script>

<template>
  <view class="rooms-page">
    <view v-if="unavailable" class="unavailable">
      <text class="page-title">
        多人推理
      </text>
      <text class="unavailable-text">
        当前平台暂不开放多人房间。
      </text>
      <button class="btn-ghost" @click="router.replace({ name: 'home' })">
        返回首页
      </button>
    </view>

    <template v-else>
      <section class="rooms-hero">
        <image
          class="hero-bg"
          :src="heroBgSrc"
          mode="aspectFill"
        />
        <view class="hero-veil" />
        <view class="hero-copy">
          <text class="hero-en">
            MULTIPLAYER
          </text>
          <text class="hero-title">
            多人推理
          </text>
          <text class="hero-quote">
            和朋友一起，从同一碗汤开始推理。
          </text>
          <text class="hero-sub">
            创建房间，或用邀请码加入好友的推理局。
          </text>
        </view>
      </section>

      <view v-if="loading" class="loading-state">
        <wd-loading />
        <text>正在获取房间信息…</text>
      </view>

      <view v-else class="room-content">
        <!-- First screen: two actions only -->
        <template v-if="screen === 'actions'">
          <view class="action-grid">
            <button class="action-btn primary" @click="openCreate">
              创建房间
            </button>
            <button class="action-btn" @click="screen = 'join'">
              加入房间
            </button>
          </view>
        </template>

        <template v-else-if="screen === 'join'">
          <view class="join-panel">
            <text class="panel-title">
              加入房间
            </text>
            <text class="panel-hint">
              输入队友分享的 8 位邀请码
            </text>
            <input v-model="inviteCode" class="field-input" :maxlength="8" confirm-type="go" placeholder="8 位邀请码" @confirm="joinByCode">
            <view class="create-actions">
              <button class="btn-ghost" @click="screen = 'actions'">
                返回
              </button>
              <button class="btn-primary" :loading="joining" :disabled="joining" @click="joinByCode">
                加入
              </button>
            </view>
          </view>
        </template>

        <template v-else-if="screen === 'create'">
          <view class="create-panel">
            <text class="panel-title">
              创建房间 · 选择题目
            </text>
            <view v-if="questionsLoading" class="loading-inline">
              <wd-loading /><text>正在加载题目…</text>
            </view>
            <template v-else>
              <scroll-view scroll-y class="question-list">
                <view
                  v-for="item in questions"
                  :key="item.id"
                  class="question-option"
                  :class="{ active: selectedQuestionId === item.id }"
                  @click="selectedQuestionId = item.id"
                >
                  <text class="question-title">
                    {{ item.title }}
                  </text>
                  <text class="question-meta">
                    {{ item.difficulty }} · {{ item.play_count || 0 }} 人推理过
                  </text>
                </view>
                <text v-if="!questions.length" class="empty-line">
                  暂无可选题目
                </text>
              </scroll-view>

              <view class="create-options">
                <view class="option-field">
                  <text class="field-label">
                    人数上限
                  </text>
                  <view class="chip-row">
                    <button
                      v-for="n in [2, 4, 6, 8]"
                      :key="n"
                      class="chip"
                      :class="{ active: maxPlayers === n }"
                      @click="maxPlayers = n"
                    >
                      {{ n }}
                    </button>
                  </view>
                </view>
                <view v-if="supportsPublicRooms" class="option-field">
                  <text class="field-label">
                    可见性
                  </text>
                  <view class="chip-row">
                    <button class="chip" :class="{ active: visibility === 'private' }" @click="visibility = 'private'">
                      私密
                    </button>
                    <button class="chip" :class="{ active: visibility === 'public' }" @click="visibility = 'public'">
                      公开
                    </button>
                  </view>
                </view>
              </view>

              <view class="create-actions">
                <button class="btn-ghost" @click="screen = 'actions'">
                  取消
                </button>
                <button class="btn-primary" :loading="createBusy" :disabled="createBusy || !selectedQuestionId" @click="createRoom">
                  {{ createBusy ? '创建中…' : '创建房间' }}
                </button>
              </view>
            </template>
          </view>
        </template>

        <template v-else-if="screen === 'created' && createdRoom">
          <view class="created-panel">
            <text class="panel-title">
              房间已创建
            </text>
            <view class="invite-code-box">
              <text class="invite-label">
                邀请码
              </text>
              <text class="invite-code">
                {{ createdRoom.invite_code }}
              </text>
            </view>
            <text class="invite-hint">
              分享邀请码，朋友即可加入。
            </text>
            <view class="create-actions">
              <button class="btn-ghost" @click="copyInvite">
                复制邀请码
              </button>
              <button class="btn-primary" @click="enterRoomGame(createdRoom)">
                进入房间
              </button>
            </view>
            <button class="link-btn" @click="screen = 'actions'">
              返回
            </button>
          </view>
        </template>

        <view v-if="supportsPublicRooms && screen === 'actions'" class="public-entry">
          <button class="btn-ghost full" @click="router.push({ name: 'public-rooms' })">
            浏览公开房间 →
          </button>
        </view>

        <view v-if="mine.length && screen === 'actions'" class="room-section">
          <text class="panel-title">
            我的房间
          </text>
          <view class="room-list">
            <view v-for="room in mine" :key="room.id" class="room-card" @click="enterRoomGame(room)">
              <view class="room-main">
                <text class="room-name">
                  {{ room.name || '未命名房间' }}
                </text>
                <text class="room-question">
                  {{ room.question?.title || '题目准备中' }}
                </text>
                <text class="meta">
                  {{ room.member_count }}/{{ room.max_players }} · {{ roomStatusLabel(room.status) }} · {{ room.invite_code }}
                </text>
              </view>
              <text class="row-arrow">
                →
              </text>
            </view>
          </view>
        </view>
      </view>
    </template>
  </view>
</template>

<style scoped>
.rooms-page {
  min-height: 100%;
  padding-bottom: 40px;
  background: var(--hgt-bg);
  color: var(--hgt-text);
  font-family: var(--hgt-font-body);
}
.rooms-hero {
  position: relative;
  box-sizing: border-box;
  display: flex;
  min-height: clamp(280px, 42vh, 420px);
  padding: 48px 20px 56px;
  align-items: flex-end;
  overflow: hidden;
}
.hero-bg {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  object-fit: cover;
  object-position: center;
  filter: brightness(1.28) contrast(0.94) saturate(1.06);
}
.hero-bg img {
  width: 100%;
  height: 100%;
  object-fit: cover !important;
  object-position: center !important;
}
.hero-veil {
  position: absolute;
  inset: 0;
  background:
    linear-gradient(90deg, rgba(4, 20, 24, 0.12) 0%, rgba(4, 20, 24, 0.04) 35%, transparent 60%),
    linear-gradient(180deg, rgba(6, 26, 32, 0) 0%, rgba(6, 26, 32, 0.04) 50%, rgba(6, 26, 32, 0.18) 78%, rgba(6, 26, 32, 0.42) 92%, var(--hgt-bg) 100%);
}
.hero-copy {
  position: relative;
  z-index: 1;
  display: flex;
  box-sizing: border-box;
  width: min(1120px, 100%);
  margin: 0 auto;
  padding: 0 clamp(8px, 2vw, 24px);
  gap: 10px;
  flex-direction: column;
  align-items: flex-start;
}
.hero-en {
  color: var(--hgt-brand);
  font-family: var(--hgt-font-mono);
  font-size: 12px;
  letter-spacing: 0.22em;
}
.hero-title {
  color: var(--hgt-text-bright);
  font-family: var(--hgt-font-display);
  font-size: 36px;
  font-weight: 600;
  letter-spacing: 0.12em;
}
.hero-quote {
  max-width: 520px;
  color: var(--hgt-text);
  font-family: var(--hgt-font-display);
  font-size: 16px;
  line-height: 1.8;
}
.hero-sub {
  color: var(--hgt-text-3);
  font-family: var(--hgt-font-display);
  font-size: 13px;
}
.unavailable,
.room-content {
  box-sizing: border-box;
  width: min(1120px, 100%);
  margin: 0 auto;
  padding: 8px 24px 24px;
}
.page-title {
  display: block;
  color: var(--hgt-text);
  font-family: var(--hgt-font-display);
  font-size: 24px;
  font-weight: 600;
  letter-spacing: 0.08em;
}
.unavailable {
  display: flex;
  min-height: 50vh;
  gap: 14px;
  align-items: flex-start;
  justify-content: center;
  flex-direction: column;
}
.unavailable-text {
  color: var(--hgt-text-2);
  font-size: 14px;
  line-height: 1.6;
}
.loading-state,
.loading-inline {
  display: flex;
  min-height: 200px;
  gap: 10px;
  align-items: center;
  justify-content: center;
  color: var(--hgt-text-2);
  font-size: 13px;
}
.loading-inline {
  min-height: 120px;
}
.room-content {
  display: flex;
  gap: 20px;
  flex-direction: column;
}
.join-panel,
.create-panel,
.created-panel,
.room-section {
  max-width: 720px;
}
.action-grid {
  display: grid;
  gap: 12px;
  grid-template-columns: 1fr 1fr;
  max-width: 720px;
}
.action-btn {
  display: flex;
  height: 52px;
  margin: 0;
  padding: 0 12px;
  border: 1px solid var(--hgt-border);
  border-radius: var(--hgt-radius-md);
  align-items: center;
  justify-content: center;
  background: var(--hgt-card);
  color: var(--hgt-text);
  font-family: var(--hgt-font-display);
  font-size: 15px;
  letter-spacing: 0.06em;
  line-height: 1;
}
.action-btn.primary {
  border-color: transparent;
  background: var(--hgt-brand);
  color: var(--hgt-on-brand);
  font-weight: 600;
}
.action-btn::after,
.btn-primary::after,
.btn-ghost::after,
.chip::after,
.link-btn::after {
  border: 0;
}
.join-panel,
.create-panel,
.created-panel,
.room-section {
  display: flex;
  padding: 16px;
  border: 1px solid var(--hgt-border);
  border-radius: var(--hgt-radius-md);
  gap: 12px;
  flex-direction: column;
  background: var(--hgt-card);
}
.panel-title {
  color: var(--hgt-text);
  font-family: var(--hgt-font-display);
  font-size: 16px;
  font-weight: 600;
}
.panel-hint {
  color: var(--hgt-text-3);
  font-size: 12px;
}
.field-input {
  box-sizing: border-box;
  width: 100%;
  height: 44px;
  padding: 0 12px;
  border: 1px solid var(--hgt-border);
  border-radius: var(--hgt-radius-sm);
  background: var(--hgt-card-2);
  color: var(--hgt-text);
  font-family: var(--hgt-font-mono);
  font-size: 14px;
  letter-spacing: 0.12em;
}
.btn-primary,
.btn-ghost {
  display: flex;
  height: 42px;
  margin: 0;
  padding: 0 16px;
  border-radius: var(--hgt-radius-sm);
  align-items: center;
  justify-content: center;
  font-size: 14px;
  line-height: 1;
}
.btn-primary {
  border: 0;
  background: var(--hgt-brand);
  color: var(--hgt-on-brand);
  font-weight: 600;
}
.btn-primary:disabled {
  opacity: 0.55;
}
.btn-ghost {
  border: 1px solid var(--hgt-border);
  background: transparent;
  color: var(--hgt-text-2);
}
.btn-ghost.full {
  width: 100%;
}
.question-list {
  max-height: 280px;
  border: 1px solid var(--hgt-border-soft);
  border-radius: var(--hgt-radius-sm);
  background: var(--hgt-card-2);
}
.question-option {
  display: flex;
  padding: 12px 14px;
  border-bottom: 1px solid var(--hgt-border-soft);
  gap: 4px;
  flex-direction: column;
}
.question-option:last-child {
  border-bottom: 0;
}
.question-option.active {
  background: var(--hgt-brand-soft);
}
.question-title {
  color: var(--hgt-text);
  font-family: var(--hgt-font-display);
  font-size: 14px;
  font-weight: 600;
}
.question-meta {
  color: var(--hgt-text-3);
  font-family: var(--hgt-font-mono);
  font-size: 11px;
}
.create-options {
  display: flex;
  gap: 12px;
  flex-direction: column;
}
.option-field {
  display: flex;
  gap: 8px;
  flex-direction: column;
}
.field-label {
  color: var(--hgt-text-2);
  font-size: 12px;
}
.chip-row {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
}
.chip {
  display: flex;
  min-width: 48px;
  height: 32px;
  margin: 0;
  padding: 0 12px;
  border: 1px solid var(--hgt-border);
  border-radius: var(--hgt-radius-full);
  align-items: center;
  justify-content: center;
  background: transparent;
  color: var(--hgt-text-2);
  font-size: 12px;
  line-height: 1;
}
.chip.active {
  border-color: var(--hgt-brand);
  background: var(--hgt-brand-soft);
  color: var(--hgt-brand);
}
.create-actions {
  display: flex;
  gap: 10px;
}
.create-actions .btn-primary,
.create-actions .btn-ghost {
  flex: 1;
}
.invite-code-box {
  display: flex;
  padding: 16px;
  border: 1px solid var(--hgt-border);
  border-radius: var(--hgt-radius-sm);
  gap: 6px;
  align-items: center;
  flex-direction: column;
  background: var(--hgt-card-2);
}
.invite-label {
  color: var(--hgt-text-3);
  font-family: var(--hgt-font-mono);
  font-size: 11px;
  letter-spacing: 0.12em;
}
.invite-code {
  color: var(--hgt-brand);
  font-family: var(--hgt-font-mono);
  font-size: 28px;
  font-weight: 600;
  letter-spacing: 0.28em;
}
.invite-hint {
  color: var(--hgt-text-2);
  font-size: 13px;
  text-align: center;
}
.link-btn {
  margin: 0;
  padding: 0;
  border: 0;
  background: transparent;
  color: var(--hgt-text-3);
  font-family: var(--hgt-font-mono);
  font-size: 12px;
  line-height: 1;
  align-self: center;
}
.public-entry {
  margin-top: 2px;
}
.room-list {
  display: flex;
  gap: 8px;
  flex-direction: column;
}
.room-card {
  display: flex;
  padding: 14px 12px;
  border: 1px solid var(--hgt-border-soft);
  border-radius: var(--hgt-radius-sm);
  align-items: center;
  gap: 10px;
  background: var(--hgt-card-2);
}
.room-main {
  display: flex;
  min-width: 0;
  flex: 1;
  gap: 4px;
  flex-direction: column;
}
.room-name {
  color: var(--hgt-text);
  font-family: var(--hgt-font-display);
  font-size: 14px;
  font-weight: 600;
}
.room-question {
  color: var(--hgt-text-2);
  font-size: 13px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.meta {
  color: var(--hgt-text-3);
  font-family: var(--hgt-font-mono);
  font-size: 11px;
}
.row-arrow {
  color: var(--hgt-text-3);
  font-family: var(--hgt-font-mono);
  font-size: 14px;
  flex: none;
}
.empty-line {
  display: block;
  padding: 20px 12px;
  color: var(--hgt-text-3);
  font-size: 13px;
  text-align: center;
}
@media (max-width: 480px) {
  .action-grid {
    grid-template-columns: 1fr;
    max-width: none;
  }
  .join-panel,
  .create-panel,
  .created-panel,
  .room-section {
    max-width: none;
  }
  .unavailable,
  .room-content {
    padding-left: 16px;
    padding-right: 16px;
  }
}

@media (min-width: 768px) {
  .rooms-hero {
    padding: 64px 32px 72px;
  }
  .unavailable,
  .room-content {
    padding: 12px 32px 32px;
  }
  .join-panel,
  .create-panel,
  .created-panel,
  .room-section {
    padding: 20px;
    gap: 14px;
  }
}
</style>
