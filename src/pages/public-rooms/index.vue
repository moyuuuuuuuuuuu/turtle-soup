<script setup lang="ts">
import type { RoomSnapshot } from '@/types/game'
import { gameApi, roomApi, TurtleApiError } from '@/api/turtle'
import { useGameSocket } from '@/composables/useGameSocket'
import { useGameStore } from '@/store/gameStore'
import { usePlayerStore } from '@/store/playerStore'
import { supportsPublicRooms } from '@/utils/platform'

definePage({ name: 'public-rooms', layout: 'tabbar', style: { 'navigationStyle': 'custom', 'mp-toutiao': { navigationStyle: 'default' } } })
const router = useRouter()
const player = usePlayerStore()
const gameStore = useGameStore()
const socket = useGameSocket()
const rooms = ref<RoomSnapshot[]>([])
const loading = ref(true)
const joiningId = ref('')
const inviteCode = ref('')
const joiningCode = ref(false)

function enterRoomGame(room: RoomSnapshot) {
  if (!room.game_id)
    throw new Error('房间尚未关联游戏，请稍后重试')
  socket.adoptRoom(room)
  router.push({ name: 'game', params: { id: room.game_id } })
}

async function load() {
  loading.value = true
  try {
    rooms.value = await roomApi.list()
  }
  catch (error) {
    uni.showToast({ title: (error as Error).message, icon: 'none' })
  }
  finally {
    loading.value = false
  }
}
async function join(id: string) {
  joiningId.value = id
  try {
    const room = await roomApi.join({ id })
    enterRoomGame(room)
  }
  catch (error) {
    const listedRoom = rooms.value.find(item => item.id === id)
    if (error instanceof TurtleApiError && error.code === 'room.status_invalid' && listedRoom?.question_id) {
      gameStore.setGame(await gameApi.create(listedRoom.question_id))
      uni.showToast({ title: '房间已结束，已切换为单人模式', icon: 'none' })
      router.replace({ name: 'game', params: { id: gameStore.current!.id } })
      return
    }
    uni.showToast({ title: (error as Error).message, icon: 'none' })
  }
  finally {
    joiningId.value = ''
  }
}
async function joinByCode() {
  const code = inviteCode.value.trim().toUpperCase()
  if (!code) {
    uni.showToast({ title: '请输入邀请码', icon: 'none' })
    return
  }
  joiningCode.value = true
  try {
    const room = await roomApi.join({ invite_code: code })
    enterRoomGame(room)
  }
  catch (error) {
    if (error instanceof TurtleApiError && error.code === 'room.status_invalid') {
      const resolved = await roomApi.resolveQuestion(code)
      gameStore.setGame(await gameApi.create(resolved.question_id))
      uni.showToast({ title: '房间已结束，已切换为单人模式', icon: 'none' })
      router.replace({ name: 'game', params: { id: gameStore.current!.id } })
      return
    }
    uni.showToast({ title: (error as Error).message, icon: 'none' })
  }
  finally {
    joiningCode.value = false
  }
}
onMounted(async () => {
  if (!supportsPublicRooms) {
    router.replace({ name: 'home' })
    return
  }
  await player.restore()
  if (!player.user) {
    router.replace({ name: 'player-login', query: { redirect: '/pages/public-rooms/index' } })
    return
  }
  await load()
})
</script>

<template>
  <view class="public-page">
    <view class="page-head">
      <button class="back hgt-mono" @click="router.back()">
        ← 返回房间
      </button><text class="hgt-mono eyebrow">
        ◉ 多人推理
      </text><text class="hgt-display title">
        公开房间
      </text>
    </view>
    <view v-if="loading" class="loading-state">
      <wd-loading /><text class="hgt-mono">
        正在获取公开房间…
      </text>
    </view>
    <view v-else class="content">
      <view class="invite-panel">
        <view class="invite-copy">
          <text class="hgt-display invite-title">
            使用邀请码加入
          </text><text class="hgt-mono meta">
            输入队友分享的 8 位邀请码
          </text>
        </view><view class="invite-form">
          <input v-model="inviteCode" class="invite-input hgt-mono" :maxlength="8" confirm-type="go" placeholder="8 位邀请码" @confirm="joinByCode"><button class="join-code hgt-mono" :loading="joiningCode" :disabled="joiningCode" @click="joinByCode">
            加入房间
          </button>
        </view>
      </view>
      <view class="toolbar">
        <text class="hgt-mono meta">
          当前 {{ rooms.length }} 个可加入房间
        </text><button class="refresh hgt-mono" @click="load">
          刷新
        </button>
      </view>
      <view v-if="rooms.length" class="room-grid">
        <view v-for="room in rooms" :key="room.id" class="room-card">
          <text class="hgt-display room-name">
            {{ room.name }}
          </text><text class="room-question">
            {{ room.question?.title || '题目准备中' }}
          </text><text class="hgt-mono meta">
            {{ room.member_count }}/{{ room.max_players }} · 等待中
          </text><button class="join hgt-mono" :loading="joiningId === room.id" @click="join(room.id)">
            加入房间
          </button>
        </view>
      </view>
      <view v-else class="empty">
        <text class="hgt-display">
          暂无公开房间
        </text><text class="hgt-mono meta">
          可以返回创建一个公开房间
        </text>
      </view>
    </view>
  </view>
</template>

<style scoped>
.public-page {
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
.back {
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
.back::after {
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
}
.content {
  box-sizing: border-box;
  width: min(var(--hgt-content-max), 100%);
  margin: 0 auto;
  padding: 28px 48px;
}
.invite-panel {
  display: grid;
  margin-bottom: 24px;
  padding: 20px;
  border: 1px solid var(--hgt-border);
  border-radius: var(--hgt-radius-md);
  gap: 20px;
  align-items: center;
  background: var(--hgt-card);
  grid-template-columns: minmax(160px, 1fr) minmax(280px, 2fr);
}
.invite-copy {
  display: flex;
  min-width: 0;
  gap: 6px;
  flex-direction: column;
}
.invite-title {
  color: var(--hgt-text);
  font-family: var(--hgt-font-display);
  font-size: 18px;
  font-weight: 600;
  line-height: 1.35;
}
.meta {
  color: var(--hgt-text-3);
  font-size: 12px;
  letter-spacing: 0.08em;
}
.invite-form {
  display: flex;
  width: 100%;
  min-width: 0;
  gap: 8px;
}
.invite-input {
  box-sizing: border-box;
  height: 44px;
  padding: 0 14px;
  border: 1px solid var(--hgt-border);
  border-radius: var(--hgt-radius-sm);
  flex: 1;
  min-width: 0;
  background: var(--hgt-card-2);
  color: var(--hgt-text);
  font-size: 14px;
  letter-spacing: 0.12em;
}
.join-code {
  display: flex;
  height: 44px;
  margin: 0;
  padding: 0 18px;
  border: 0;
  border-radius: var(--hgt-radius-sm);
  flex: none;
  align-items: center;
  justify-content: center;
  background: var(--hgt-brand);
  color: var(--hgt-on-brand);
  font-size: 14px;
  font-weight: 600;
  white-space: nowrap;
}
.join-code::after {
  border: 0;
}
.toolbar {
  display: flex;
  margin-bottom: 16px;
  align-items: center;
  justify-content: space-between;
}
.refresh,
.join {
  display: flex;
  height: 38px;
  margin: 0;
  padding: 0 16px;
  border: 1px solid var(--hgt-border);
  border-radius: var(--hgt-radius-sm);
  align-items: center;
  justify-content: center;
  background: transparent;
  color: var(--hgt-text);
  font-size: 13px;
  line-height: 1;
}
.refresh::after,
.join::after {
  border: 0;
}
.join {
  border-color: transparent;
  background: var(--hgt-brand);
  color: var(--hgt-on-brand);
  font-weight: 600;
}
.room-grid {
  display: grid;
  gap: 14px;
  grid-template-columns: repeat(2, minmax(0, 1fr));
}
.room-card {
  display: flex;
  padding: 20px;
  border: 1px solid var(--hgt-border);
  border-radius: var(--hgt-radius-md);
  gap: 10px;
  flex-direction: column;
  background: var(--hgt-card);
}
.room-name {
  color: var(--hgt-text);
  font-family: var(--hgt-font-display);
  font-size: 18px;
  font-weight: 600;
}
.room-question {
  padding-left: 10px;
  border-left: 2px solid var(--hgt-brand);
  color: var(--hgt-text-2);
  font-size: 13px;
  line-height: 1.5;
}
.empty {
  display: flex;
  min-height: 200px;
  gap: 8px;
  align-items: center;
  justify-content: center;
  flex-direction: column;
  color: var(--hgt-text-2);
}
@media (max-width: 767px) {
  .page-head,
  .content {
    padding-right: 16px;
    padding-left: 16px;
  }
  .invite-panel {
    display: flex;
    flex-direction: column;
    align-items: stretch;
  }
  .invite-form {
    flex-direction: column;
  }
  .room-grid {
    grid-template-columns: 1fr;
  }
}
</style>
