<script setup lang="ts">
/* eslint-disable style/max-statements-per-line */
import type { RoomSnapshot } from '@/types/game'
import { gameApi, roomApi, TurtleApiError } from '@/api/turtle'
import { useGameSocket } from '@/composables/useGameSocket'
import { useGameStore } from '@/store/gameStore'
import { usePlayerStore } from '@/store/playerStore'

definePage({ name: 'rooms', layout: 'tabbar', style: { navigationStyle: 'custom' } })
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
    await player.restore(); if (!player.user)
      return router.replace({ name: 'player-login', query: inviteCode.value ? { redirect: `/pages/rooms/index?invite_code=${encodeURIComponent(inviteCode.value)}` } : {} }); if (inviteCode.value) {
      await join()
      return
    } await load()
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
      <button class="public-entry hgt-mono" @click="router.push({ name: 'public-rooms' })">
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
.mini-label{font-size:9px;letter-spacing:.16em;color:var(--muted-foreground)}.choice-group{display:flex;gap:9px;flex-direction:column}.choice-row{display:flex;gap:7px;flex-wrap:wrap}.choice{display:flex;height:38px;margin:0;padding:0 15px;border:1px solid var(--border);border-radius:0;align-items:center;justify-content:center;background:transparent;color:var(--muted-foreground);font-size:11px;line-height:1}.choice.active{border-color:var(--foreground);background:var(--foreground);color:var(--background)}.choice.visibility{min-width:128px}.choice::after{border:0}
.rooms-page{min-height:100vh}.page-head{padding:34px 48px;border-bottom:1px solid var(--border);display:flex;flex-direction:column;gap:8px}.eyebrow,.meta,.empty{font-size:11px;color:var(--muted-foreground);letter-spacing:.15em}.title{font-size:38px}.loading-state{min-height:420px;display:flex;gap:14px;align-items:center;justify-content:center;color:var(--muted-foreground);font-size:11px}.room-content{padding:40px 48px;max-width:1000px;display:flex;flex-direction:column;gap:34px}.create-panel,.join-panel{border:1px solid var(--border);background:var(--card);padding:24px;display:flex;flex-direction:column;gap:14px}.panel-title{display:block;font-size:20px;margin-bottom:12px}.field{height:46px;line-height:46px;border:1px solid var(--border);padding:0 15px;color:var(--foreground);font-size:13px}.row{display:flex;gap:10px}.flex-one{flex:1}.primary,.small,.public-entry{margin:0;border-radius:0;background:var(--foreground);color:var(--background);font-size:11px;letter-spacing:.12em}.primary{height:46px;line-height:46px}.small{height:38px;line-height:38px;padding:0 20px}.public-entry{display:flex;width:100%;height:46px;padding:0;align-items:center;justify-content:center}button::after{display:none}.room-section{display:flex;flex-direction:column}.room-grid{display:grid;grid-template-columns:repeat(2,1fr);border-left:1px solid var(--border);border-top:1px solid var(--border)}.room-card{padding:20px;border-right:1px solid var(--border);border-bottom:1px solid var(--border);display:flex;flex-direction:column;gap:12px}.empty{padding:25px}
.back-question{display:flex;width:max-content;height:30px;margin:0 0 4px;padding:0;border:0;align-items:center;background:transparent;color:var(--muted-foreground);font-size:11px;line-height:1;letter-spacing:.12em}.back-question:hover{color:var(--foreground)}.room-question{font-size:13px;color:var(--foreground)}
@media(max-width:767px){.page-head,.room-content{padding-left:28px;padding-right:28px}.room-grid{grid-template-columns:1fr}.row{flex-wrap:wrap}}
</style>
