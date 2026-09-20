<script setup lang="ts">
import type { RoomSnapshot } from '@/types/game'
import { gameApi, roomApi, TurtleApiError } from '@/api/turtle'
import { useGameSocket } from '@/composables/useGameSocket'
import { useGameStore } from '@/store/gameStore'
import { usePlayerStore } from '@/store/playerStore'
import { resolveAssetUrl } from '@/utils/assetUrl'
import { supportsPublicRooms } from '@/utils/platform'

definePage({ name: 'public-rooms', layout: 'tabbar', style: { 'navigationStyle': 'custom', 'mp-toutiao': { navigationStyle: 'default' } } })

const heroBgSrc = resolveAssetUrl('/static/hgt/bg/bg_deep_ocean.jpg')

const ROOM_STATUS_LABEL: Record<string, string> = {
  waiting: '等待中',
  playing: '推理中',
  finished: '已结束',
  closed: '已关闭',
}

const router = useRouter()
const player = usePlayerStore()
const gameStore = useGameStore()
const socket = useGameSocket()

const unavailable = ref(false)
const loading = ref(true)
const rooms = ref<RoomSnapshot[]>([])
const inviteCode = ref('')
const joiningId = ref('')
const joiningCode = ref(false)
const refreshing = ref(false)
const joinOpen = ref(false)

const hasRoomReturnFab = computed(() => {
  const room = socket.roomSnapshot.value
  return Boolean(player.user && room && ['waiting', 'playing'].includes(room.status) && room.game_id)
})

function openJoinModal() {
  joinOpen.value = true
}

function closeJoinModal() {
  joinOpen.value = false
}

async function joinFromModal() {
  const code = inviteCode.value.trim().toUpperCase()
  if (!code) {
    uni.showToast({ title: '请输入邀请码', icon: 'none' })
    return
  }
  joiningCode.value = true
  try {
    const room = await roomApi.join({ invite_code: code })
    closeJoinModal()
    enterRoomGame(room)
  }
  catch (error) {
    if (error instanceof TurtleApiError && error.code === 'room.status_invalid') {
      const questionId = (await roomApi.resolveQuestion(code)).question_id
      closeJoinModal()
      await startSingleFromQuestion(questionId)
      return
    }
    uni.showToast({ title: (error as Error).message, icon: 'none' })
  }
  finally {
    joiningCode.value = false
  }
}

const openRooms = computed(() => rooms.value.filter(item => item.status === 'waiting' || item.status === 'playing'))
const roomStatusLabel = (status: string) => ROOM_STATUS_LABEL[status] || status || '未知'

function enterRoomGame(room: RoomSnapshot) {
  if (!room.game_id) {
    uni.showToast({ title: '房间尚未关联游戏，请稍后重试', icon: 'none' })
    return
  }
  socket.adoptRoom(room)
  router.push({ name: 'game', params: { id: room.game_id } })
}

async function load() {
  refreshing.value = true
  try {
    rooms.value = await roomApi.list()
  }
  catch (error) {
    uni.showToast({ title: (error as Error).message, icon: 'none' })
  }
  finally {
    refreshing.value = false
  }
}

async function startSingleFromQuestion(questionId: string) {
  gameStore.setGame(await gameApi.create(questionId))
  uni.showToast({ title: '房间已结束，已切换为单人模式', icon: 'none' })
  router.replace({ name: 'game', params: { id: gameStore.current!.id } })
}

async function join(id?: string) {
  const code = inviteCode.value.trim().toUpperCase()
  if (!id && !code) {
    uni.showToast({ title: '请输入邀请码', icon: 'none' })
    return
  }
  if (id)
    joiningId.value = id
  else
    joiningCode.value = true
  try {
    const room = await roomApi.join(id ? { id } : { invite_code: code })
    enterRoomGame(room)
  }
  catch (error) {
    const listedRoom = id ? rooms.value.find(item => item.id === id) : null
    if (error instanceof TurtleApiError && error.code === 'room.status_invalid') {
      const questionId = listedRoom?.question_id || (await roomApi.resolveQuestion(code)).question_id
      await startSingleFromQuestion(questionId)
      return
    }
    uni.showToast({ title: (error as Error).message, icon: 'none' })
  }
  finally {
    joiningId.value = ''
    joiningCode.value = false
  }
}

onMounted(async () => {
  if (!supportsPublicRooms) {
    unavailable.value = true
    loading.value = false
    return
  }
  await player.restore()
  if (!player.user) {
    router.replace({ name: 'player-login', query: { redirect: '/pages/public-rooms/index' } })
    return
  }
  await load()
  loading.value = false
})
</script>

<template>
  <view class="public-page">
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
            用邀请码加入好友，或直接加入下方公开房间。
          </text>
        </view>
      </section>

      <view v-if="loading" class="loading-state">
        <wd-loading />
        <text>正在获取公开房间…</text>
      </view>

      <view v-else class="content">
        <view class="panel join-panel-inline">
          <text class="panel-title">
            加入房间
          </text>
          <text class="panel-hint">
            输入队友分享的 8 位邀请码
          </text>
          <input
            v-model="inviteCode"
            class="field-input"
            :maxlength="8"
            confirm-type="go"
            placeholder="8 位邀请码"
            @confirm="join()"
          >
          <view class="join-actions">
            <button
              class="btn-primary"
              :loading="joiningCode"
              :disabled="joiningCode"
              @click="join()"
            >
              加入
            </button>
          </view>
        </view>

        <view class="room-section">
          <view class="toolbar">
            <text class="panel-title">
              公开房间
            </text>
            <text class="meta">
              {{ openRooms.length }} 个可加入
            </text>
            <button class="btn-ghost sm" :loading="refreshing" @click="load">
              刷新
            </button>
          </view>

          <view v-if="rooms.length" class="room-list">
            <view v-for="room in rooms" :key="room.id" class="room-card">
              <view class="room-main">
                <text class="room-name">
                  {{ room.name || '未命名房间' }}
                </text>
                <text class="room-question">
                  {{ room.question?.title || '题目准备中' }}
                </text>
                <text class="meta">
                  {{ room.member_count }}/{{ room.max_players }} · {{ roomStatusLabel(room.status) }}
                </text>
              </view>
              <button
                class="btn-primary sm"
                :loading="joiningId === room.id"
                :disabled="room.status !== 'waiting' && room.status !== 'playing'"
                @click="join(room.id)"
              >
                {{ room.status === 'waiting' || room.status === 'playing' ? '加入' : roomStatusLabel(room.status) }}
              </button>
            </view>
          </view>
          <view v-else class="empty">
            <text>暂无公开房间</text>
            <text class="meta empty-hint-pc">
              点击右下角「加入房间」，用邀请码加入好友房间。
            </text>
            <text class="meta empty-hint-mobile">
              可用邀请码加入好友房间。
            </text>
          </view>
        </view>
      </view>

      <button
        v-if="!loading"
        class="join-fab"
        :class="{ 'has-room-return': hasRoomReturnFab }"
        @click="openJoinModal"
      >
        <text class="join-fab-icon">
          +
        </text>
        <text class="join-fab-label">
          加入房间
        </text>
      </button>

      <view v-if="joinOpen && !loading" class="join-modal-mask" @tap="closeJoinModal">
        <view class="join-modal" @tap.stop>
          <view class="join-modal-top">
            <text class="join-modal-eyebrow">
              INVITE
            </text>
            <button class="join-modal-close" @tap="closeJoinModal">
              ✕
            </button>
          </view>
          <text class="panel-title join-modal-title">
            加入房间
          </text>
          <text class="panel-hint">
            输入队友分享的 8 位邀请码
          </text>
          <input
            v-model="inviteCode"
            class="field-input"
            :maxlength="8"
            confirm-type="go"
            placeholder="8 位邀请码"
            @confirm="joinFromModal"
          >
          <view class="join-actions">
            <button class="btn-ghost" @click="closeJoinModal">
              取消
            </button>
            <button
              class="btn-primary"
              :loading="joiningCode"
              :disabled="joiningCode"
              @click="joinFromModal"
            >
              加入
            </button>
          </view>
        </view>
      </view>
    </template>
  </view>
</template>

<style scoped>
.public-page {
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
.content {
  box-sizing: border-box;
  width: min(1120px, 100%);
  margin: 0 auto;
  padding: 8px 24px 24px;
}
.content {
  display: grid;
  gap: 28px;
  grid-template-columns: 1fr;
  align-items: start;
}
.panel,
.room-section {
  min-width: 0;
}
.join-fab {
  display: none;
}
.empty-hint-pc {
  display: none;
}
.empty-hint-mobile {
  display: block;
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
.loading-state {
  display: flex;
  min-height: 200px;
  gap: 10px;
  align-items: center;
  justify-content: center;
  color: var(--hgt-text-2);
  font-size: 13px;
}
.panel,
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
.btn-primary::after,
.btn-ghost::after {
  border: 0;
}
.btn-ghost.sm,
.btn-primary.sm {
  height: 36px;
  padding: 0 12px;
  font-size: 13px;
  flex: none;
}
.join-actions {
  display: flex;
  gap: 10px;
}
.join-actions .btn-primary {
  flex: 1;
}
.join-actions .btn-ghost {
  flex: none;
  min-width: 88px;
}
.join-panel-inline .join-actions .btn-primary {
  width: 100%;
}
.join-panel-inline .join-actions .btn-ghost {
  display: none;
}
.toolbar {
  display: flex;
  align-items: center;
  gap: 8px 12px;
  flex-wrap: wrap;
}
.toolbar .panel-title {
  margin-right: auto;
}
.meta {
  color: var(--hgt-text-3);
  font-family: var(--hgt-font-mono);
  font-size: 12px;
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
/* PC 悬浮加入按钮 + 弹层 */
.join-fab {
  position: fixed;
  z-index: 24;
  right: 28px;
  bottom: 40px;
  box-sizing: border-box;
  height: 52px;
  margin: 0;
  padding: 0 20px 0 16px;
  border: 0;
  border-radius: var(--hgt-radius-sm);
  align-items: center;
  gap: 10px;
  background: var(--hgt-brand);
  color: var(--hgt-on-brand);
  box-shadow: var(--hgt-shadow-float, 0 12px 32px rgba(0, 0, 0, 0.35));
  font-family: var(--hgt-font-display);
  font-size: 14px;
  font-weight: 600;
  letter-spacing: 0.08em;
  transition: transform var(--hgt-dur-fast, 0.16s) ease, filter 0.16s ease;
}
.join-fab:hover {
  filter: brightness(1.06);
  transform: translateY(-2px);
}
.join-fab::after {
  border: 0;
}
.join-fab-icon {
  font-family: var(--hgt-font-mono);
  font-size: 18px;
  line-height: 1;
}
.join-modal-mask {
  position: fixed;
  z-index: 10010;
  inset: 0;
  display: flex;
  padding: 24px 16px;
  align-items: center;
  justify-content: center;
  background: rgba(3, 14, 18, 0.72);
  backdrop-filter: blur(6px);
  -webkit-backdrop-filter: blur(6px);
  animation: join-modal-fade 0.18s ease-out;
}
.join-modal {
  box-sizing: border-box;
  display: flex;
  width: min(400px, 100%);
  padding: 22px 22px 20px;
  border: 1px solid rgba(117, 220, 211, 0.18);
  border-radius: 12px;
  gap: 12px;
  flex-direction: column;
  background:
    linear-gradient(180deg, rgba(22, 62, 66, 0.2), transparent 42%),
    rgba(8, 28, 34, 0.96);
  color: var(--hgt-text);
  box-shadow: 0 24px 72px rgba(0, 0, 0, 0.48);
  animation: join-modal-in 0.22s cubic-bezier(0.22, 1, 0.36, 1);
}
.join-modal-top {
  display: flex;
  padding-bottom: 10px;
  border-bottom: 1px solid var(--hgt-border-soft);
  align-items: center;
  justify-content: space-between;
}
.join-modal-eyebrow {
  color: var(--hgt-text-3);
  font-family: var(--hgt-font-mono);
  font-size: 10px;
  letter-spacing: 0.28em;
}
.join-modal-close {
  box-sizing: border-box;
  width: 28px;
  height: 28px;
  margin: 0;
  padding: 0;
  border: 0;
  border-radius: 6px;
  background: transparent;
  color: var(--hgt-text-3);
  font-size: 14px;
  line-height: 1;
}
.join-modal-close::after {
  border: 0;
}
.join-modal-title {
  margin-top: 4px;
  font-size: 22px;
  color: var(--hgt-text-bright);
}
@keyframes join-modal-fade {
  from { opacity: 0; }
  to { opacity: 1; }
}
@keyframes join-modal-in {
  from {
    opacity: 0;
    transform: scale(0.96) translateY(8px);
  }
  to {
    opacity: 1;
    transform: none;
  }
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
.empty {
  display: flex;
  min-height: 160px;
  gap: 8px;
  align-items: center;
  justify-content: center;
  flex-direction: column;
  color: var(--hgt-text-2);
  font-family: var(--hgt-font-display);
  font-size: 14px;
}
@media (max-width: 480px) {
  .room-card {
    flex-direction: column;
    align-items: stretch;
  }
  .btn-primary.sm {
    width: 100%;
  }
  .content {
    padding-left: 16px;
    padding-right: 16px;
  }
}

@media (min-width: 768px) {
  .rooms-hero {
    padding: 64px 32px 72px;
  }
  .unavailable,
  .content {
    width: min(1200px, 100%);
    padding: 12px 32px 32px;
  }
  .content {
    grid-template-columns: 1fr;
    gap: 20px;
  }
  /* PC：加入房间改为右下角悬浮按钮 + 弹层，列表占满整宽 */
  .join-panel-inline {
    display: none;
  }
  .join-fab {
    display: flex;
    right: 28px;
    bottom: 40px;
  }
  .join-fab.has-room-return {
    right: 220px;
  }
  .room-section {
    width: 100%;
    padding: 22px 24px 24px;
    gap: 16px;
    min-height: 320px;
  }
  .room-list {
    display: grid;
    gap: 12px;
    grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
  }
  .room-card {
    padding: 16px 14px;
  }
  .room-question {
    white-space: normal;
    display: -webkit-box;
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 2;
  }
  .empty {
    min-height: 240px;
  }
  .empty-hint-pc {
    display: block;
  }
  .empty-hint-mobile {
    display: none;
  }
}
</style>
