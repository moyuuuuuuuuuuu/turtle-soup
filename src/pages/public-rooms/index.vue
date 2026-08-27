<script setup lang="ts">
import type { RoomSnapshot } from '@/types/game'
import { gameApi, roomApi, TurtleApiError } from '@/api/turtle'
import { useGameStore } from '@/store/gameStore'
import { usePlayerStore } from '@/store/playerStore'

definePage({ name: 'public-rooms', layout: 'tabbar', style: { navigationStyle: 'custom' } })
const router = useRouter()
const player = usePlayerStore()
const gameStore = useGameStore()
const rooms = ref<RoomSnapshot[]>([])
const loading = ref(true)
const joiningId = ref('')
const inviteCode = ref('')
const joiningCode = ref(false)

function enterRoomGame(room: RoomSnapshot) {
  if (!room.game_id)
    throw new Error('房间尚未关联游戏，请稍后重试')
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
.public-page{min-height:100vh}.page-head{padding:34px 48px;border-bottom:1px solid var(--border);display:flex;flex-direction:column;gap:8px}.back{display:flex;width:max-content;height:30px;margin:0 0 4px;padding:0;border:0;align-items:center;background:transparent;color:var(--muted-foreground);font-size:11px;line-height:1;letter-spacing:.12em}.eyebrow,.meta{font-size:11px;color:var(--muted-foreground);letter-spacing:.14em}.title{font-size:38px}.loading-state{min-height:420px;display:flex;gap:14px;align-items:center;justify-content:center;color:var(--muted-foreground)}.content{box-sizing:border-box;width:min(1100px,100%);padding:40px 48px}.invite-panel{display:flex;margin-bottom:30px;padding:20px;border:1px solid var(--border);align-items:center;justify-content:space-between;background:var(--card);gap:24px}.invite-copy{display:flex;gap:7px;flex-direction:column}.invite-title{font-size:19px}.invite-form{display:flex;min-width:480px}.invite-input{box-sizing:border-box;height:44px;padding:0 15px;border:1px solid var(--border);flex:1;color:var(--foreground);font-size:12px;letter-spacing:.16em}.join-code{display:flex;width:120px;height:44px;margin:0;padding:0;border:1px solid var(--foreground);border-radius:0;align-items:center;justify-content:center;background:var(--foreground);color:var(--background);font-size:11px;letter-spacing:.1em}.toolbar{margin-bottom:20px;display:flex;align-items:center;justify-content:space-between}.refresh,.join{display:flex;height:38px;margin:0;padding:0 20px;border:1px solid var(--border);border-radius:0;align-items:center;justify-content:center;background:transparent;color:var(--foreground);font-size:11px}.room-grid{display:grid;grid-template-columns:repeat(2,1fr)}.room-card{padding:24px;border:1px solid var(--border);display:flex;gap:12px;flex-direction:column}.room-card:nth-child(even){border-left:0}.room-card:nth-child(n+3){border-top:0}.room-name{font-size:21px}.room-question{padding-left:10px;border-left:1px solid var(--foreground);font-size:13px;line-height:1.6}.join{width:100%;margin-top:8px;background:var(--foreground);color:var(--background)}.empty{min-height:260px;border:1px solid var(--border);display:flex;gap:14px;align-items:center;justify-content:center;flex-direction:column}button::after{display:none}@media(max-width:767px){.page-head,.content{padding-left:28px;padding-right:28px}.invite-panel{align-items:stretch;flex-direction:column}.invite-form{min-width:0}.room-grid{grid-template-columns:1fr}.room-card:nth-child(even){border-left:1px solid var(--border)}.room-card:nth-child(n+2){border-top:0}}
.invite-panel{display:grid;grid-template-columns:minmax(190px,1fr) minmax(360px,2fr);gap:32px}
.invite-copy{min-width:0}
.invite-title{display:block;line-height:1.35;white-space:nowrap}
.invite-copy .meta{display:block;line-height:1.6;letter-spacing:.08em;white-space:nowrap}
.invite-form{width:100%;min-width:0}
.invite-input{min-width:0}
@media(max-width:767px){.invite-panel{display:flex}.invite-title,.invite-copy .meta{white-space:normal}}
</style>
