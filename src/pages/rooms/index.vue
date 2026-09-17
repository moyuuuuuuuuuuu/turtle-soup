<script setup lang="ts">
import type { RoomSnapshot } from '@/types/game'
import { gameApi, roomApi, TurtleApiError } from '@/api/turtle'
import { useGameSocket } from '@/composables/useGameSocket'
import { useGameStore } from '@/store/gameStore'
import { usePlayerStore } from '@/store/playerStore'
import { supportsPublicRooms } from '@/utils/platform'

definePage({ name: 'rooms', layout: 'tabbar', style: { 'navigationStyle': 'custom', 'mp-toutiao': { navigationStyle: 'default' } } })
const route = useRoute()
const router = useRouter()
const player = usePlayerStore()
const gameStore = useGameStore()
const socket = useGameSocket()
const mine = ref<RoomSnapshot[]>([])
const inviteCode = ref('')
const fallbackQuestionId = ref('')
const loading = ref(true)
onLoad((options) => {
  inviteCode.value = String(options?.invite_code || route.query.invite_code || '')
  fallbackQuestionId.value = String(options?.question_id || route.query.question_id || '')
})
async function load() {
  mine.value = await roomApi.mine()
}
function backToQuestion() {
  router.back()
}
function enterRoomGame(room: RoomSnapshot) {
  if (!room.game_id)
    throw new Error('房间尚未关联游戏，请稍后重试')
  socket.adoptRoom(room)
  router.push({ name: 'game', params: { id: room.game_id } })
}
async function startFallbackGame(questionId: string) {
  gameStore.setGame(await gameApi.create(questionId))
  uni.showToast({ title: '房间已结束，已切换为单人模式', icon: 'none' })
  router.replace({ name: 'game', params: { id: gameStore.current!.id } })
}
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
async function join(id?: string, questionId = fallbackQuestionId.value) {
  try {
    const room = await roomApi.join(id ? { id } : { invite_code: inviteCode.value })
    enterRoomGame(room)
  }
  catch (error) {
    if (error instanceof TurtleApiError && error.code === 'room.status_invalid') {
      const fallbackId = questionId || (await roomApi.resolveQuestion(inviteCode.value)).question_id
      await startFallbackGame(fallbackId)
      return
    }
    throw error
  }
}
onMounted(async () => {
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
    await load()
  }
  catch (error) { uni.showToast({ title: (error as Error).message, icon: 'none' }) }
  finally { loading.value = false }
})
</script>

<template>
  <view class="rooms-page">
    <view class="page-head">
      <button class="back-question hgt-mono" @click="backToQuestion">
        ← 返回题目
      </button>
      <text class="hgt-mono eyebrow">
        ◉ 多人推理
      </text><text class="hgt-display title">
        房间
      </text>
    </view>
    <view v-if="loading" class="loading-state">
      <wd-loading /><text class="hgt-mono">
        正在获取房间信息…
      </text>
    </view>
    <view v-else class="room-content">
      <view class="join-panel">
        <text class="hgt-display panel-title">
          邀请码加入
        </text><view class="row">
          <input v-model="inviteCode" class="field flex-one hgt-mono" :maxlength="8" placeholder="8 位邀请码"><button class="small hgt-mono" @click="join()">
            加入
          </button>
        </view>
      </view>
      <button v-if="supportsPublicRooms" class="public-entry hgt-mono" @click="router.push({ name: 'public-rooms' })">
        浏览公开房间 →
      </button>
      <view v-if="mine.length" class="room-section">
        <text class="hgt-display panel-title">
          我的房间
        </text><view class="room-grid">
          <view v-for="room in mine" :key="room.id" class="room-card" @click="enterRoomGame(room)">
            <text class="hgt-display">
              {{ room.name }}
            </text><text class="room-question">
              {{ room.question?.title || '题目准备中' }}
            </text><text class="meta hgt-mono">
              {{ room.member_count }}/{{ room.max_players }} · {{ room.status }} · {{ room.invite_code }}
            </text>
          </view>
        </view>
      </view>
    </view>
  </view>
</template>

<style scoped>
.rooms-page {
  min-height: 100%;
  padding-bottom: 40px;
  background: var(--hgt-bg);
  color: var(--hgt-text);
}
.page-head {
  display: flex;
  padding: 32px 48px 24px;
  border-bottom: 1px solid var(--hgt-border);
  gap: 8px;
  flex-direction: column;
}
.back-question {
  display: flex;
  width: max-content;
  height: 30px;
  margin: 0 0 4px;
  padding: 0;
  border: 0;
  align-items: center;
  background: transparent;
  color: var(--hgt-text-2);
  font-size: 12px;
  line-height: 1;
}
.back-question::after {
  border: 0;
}
.eyebrow {
  color: var(--hgt-brand);
  font-size: 11px;
  letter-spacing: 0.22em;
}
.title {
  color: var(--hgt-text);
  font-family: var(--hgt-font-display);
  font-size: 28px;
  font-weight: 600;
  letter-spacing: 0.06em;
}
.loading-state {
  display: flex;
  min-height: 280px;
  gap: 12px;
  align-items: center;
  justify-content: center;
  color: var(--hgt-text-2);
  font-size: 13px;
}
.room-content {
  display: flex;
  box-sizing: border-box;
  width: min(var(--hgt-content-max), 100%);
  margin: 0 auto;
  padding: 28px 48px;
  gap: 24px;
  flex-direction: column;
}
.join-panel,
.create-panel {
  display: flex;
  padding: 20px;
  border: 1px solid var(--hgt-border);
  border-radius: var(--hgt-radius-md);
  gap: 12px;
  flex-direction: column;
  background: var(--hgt-card);
}
.panel-title {
  display: block;
  margin-bottom: 4px;
  color: var(--hgt-text);
  font-family: var(--hgt-font-display);
  font-size: 18px;
  font-weight: 600;
}
.field {
  box-sizing: border-box;
  height: 44px;
  padding: 0 14px;
  border: 1px solid var(--hgt-border);
  border-radius: var(--hgt-radius-sm);
  background: var(--hgt-card-2);
  color: var(--hgt-text);
  font-size: 14px;
  line-height: 44px;
}
.row {
  display: flex;
  gap: 10px;
}
.flex-one {
  flex: 1;
  min-width: 0;
}
.small,
.public-entry {
  display: flex;
  height: 44px;
  margin: 0;
  padding: 0 18px;
  border: 0;
  border-radius: var(--hgt-radius-sm);
  align-items: center;
  justify-content: center;
  background: var(--hgt-brand);
  color: var(--hgt-on-brand);
  font-size: 14px;
  font-weight: 600;
  line-height: 1;
}
.small::after,
.public-entry::after {
  border: 0;
}
.public-entry {
  width: 100%;
}
.room-section {
  display: flex;
  gap: 12px;
  flex-direction: column;
}
.room-grid {
  display: grid;
  gap: 14px;
  grid-template-columns: repeat(2, minmax(0, 1fr));
}
.room-card {
  display: flex;
  padding: 18px;
  border: 1px solid var(--hgt-border);
  border-radius: var(--hgt-radius-md);
  gap: 10px;
  flex-direction: column;
  background: var(--hgt-card);
  cursor: pointer;
  transition: border-color var(--hgt-dur-fast), transform var(--hgt-dur-fast);
}
.room-card:hover {
  border-color: var(--hgt-border-soft);
  transform: translateY(-1px);
}
.room-question {
  color: var(--hgt-text);
  font-size: 13px;
  padding-left: 10px;
  border-left: 2px solid var(--hgt-brand);
}
.meta {
  color: var(--hgt-text-3);
  font-size: 12px;
  letter-spacing: 0.06em;
}
@media (max-width: 767px) {
  .page-head,
  .room-content {
    padding-right: 16px;
    padding-left: 16px;
  }
  .room-grid {
    grid-template-columns: 1fr;
  }
  .row {
    flex-wrap: wrap;
  }
}
</style>
