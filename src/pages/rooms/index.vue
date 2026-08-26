<script setup lang="ts">
/* eslint-disable style/max-statements-per-line */
import type { PublicQuestion, RoomSnapshot } from '@/types/game'
import { questionApi, roomApi } from '@/api/turtle'
import { usePlayerStore } from '@/store/playerStore'

definePage({ name: 'rooms', layout: 'tabbar', style: { navigationStyle: 'custom' } })
const route = useRoute()
const router = useRouter()
const player = usePlayerStore()
const rooms = ref<RoomSnapshot[]>([])
const mine = ref<RoomSnapshot[]>([])
const questions = ref<PublicQuestion[]>([])
const inviteCode = ref('')
const creating = ref(false)
const form = reactive({ question_id: String(route.query.question_id || ''), name: '', max_players: 4, visibility: 'private' as 'private' | 'public' })
async function load() { rooms.value = await roomApi.list(); mine.value = await roomApi.mine(); questions.value = (await questionApi.list({ pageSize: 100 })).items }
async function create() {
  if (!form.question_id || !form.name.trim())
    return uni.showToast({ title: '请选择题目并填写房间名', icon: 'none' }); creating.value = true; try { const room = await roomApi.create(form); router.push({ name: 'room', params: { id: room.id } }) }
  finally { creating.value = false }
}
async function join(id?: string) { const room = await roomApi.join(id ? { id } : { invite_code: inviteCode.value }); router.push({ name: 'room', params: { id: room.id } }) }
onMounted(async () => {
  await player.restore(); if (!player.user)
    return router.replace({ name: 'player-login' }); await load()
})
</script>

<template>
  <view class="rooms-page">
    <view class="page-head">
      <text class="hgt-mono eyebrow">
        ◉ 多人推理
      </text><text class="hgt-display title">
        房间
      </text>
    </view>
    <view class="room-content">
      <view class="create-panel">
        <text class="hgt-display panel-title">
          创建房间
        </text>
        <picker :range="questions" range-key="title" @change="form.question_id = questions[Number($event.detail.value)]?.id || ''">
          <view class="field">
            {{ questions.find(item => item.id === form.question_id)?.title || '选择题目' }}
          </view>
        </picker>
        <input v-model="form.name" class="field" :maxlength="80" placeholder="房间名称">
        <view class="row">
          <picker :range="[2, 3, 4, 5, 6, 7, 8]" @change="form.max_players = [2, 3, 4, 5, 6, 7, 8][Number($event.detail.value)]">
            <view class="field flex-one">
              {{ form.max_players }} 人
            </view>
          </picker><picker :range="['私密房间', '公开房间']" @change="form.visibility = Number($event.detail.value) === 1 ? 'public' : 'private'">
            <view class="field flex-one">
              {{ form.visibility === 'public' ? '公开房间' : '私密房间' }}
            </view>
          </picker>
        </view>
        <button class="primary hgt-mono" :loading="creating" @click="create">
          创建并邀请队友
        </button>
      </view>
      <view class="join-panel">
        <text class="hgt-display panel-title">
          邀请码加入
        </text><view class="row">
          <input v-model="inviteCode" class="field flex-one hgt-mono" :maxlength="8" placeholder="8 位邀请码"><button class="small hgt-mono" @click="join()">
            加入
          </button>
        </view>
      </view>
      <view v-if="mine.length">
        <text class="hgt-display panel-title">
          我的房间
        </text><view class="room-grid">
          <view v-for="room in mine" :key="room.id" class="room-card" @click="router.push({ name: 'room', params: { id: room.id } })">
            <text class="hgt-display">
              {{ room.name }}
            </text><text class="meta hgt-mono">
              {{ room.member_count }}/{{ room.max_players }} · {{ room.status }} · {{ room.invite_code }}
            </text>
          </view>
        </view>
      </view>
      <view>
        <text class="hgt-display panel-title">
          公开房间
        </text><view class="room-grid">
          <view v-for="room in rooms" :key="room.id" class="room-card">
            <text class="hgt-display">
              {{ room.name }}
            </text><text class="meta hgt-mono">
              {{ room.member_count }}/{{ room.max_players }}
            </text><button class="small hgt-mono" @click="join(room.id)">
              加入
            </button>
          </view><text v-if="!rooms.length" class="empty hgt-mono">
            暂无公开房间
          </text>
        </view>
      </view>
    </view>
  </view>
</template>

<style scoped>
.rooms-page{min-height:100vh}.page-head{padding:34px 48px;border-bottom:1px solid var(--border);display:flex;flex-direction:column;gap:8px}.eyebrow,.meta,.empty{font-size:11px;color:var(--muted-foreground);letter-spacing:.15em}.title{font-size:38px}.room-content{padding:40px 48px;max-width:1000px;display:flex;flex-direction:column;gap:34px}.create-panel,.join-panel{border:1px solid var(--border);background:var(--card);padding:24px;display:flex;flex-direction:column;gap:14px}.panel-title{display:block;font-size:20px;margin-bottom:12px}.field{height:46px;line-height:46px;border:1px solid var(--border);padding:0 15px;color:var(--foreground);font-size:13px}.row{display:flex;gap:10px}.flex-one{flex:1}.primary,.small{margin:0;border-radius:0;background:var(--foreground);color:var(--background);font-size:11px;letter-spacing:.12em}.primary{height:46px;line-height:46px}.small{height:38px;line-height:38px;padding:0 20px}button::after{display:none}.room-grid{display:grid;grid-template-columns:repeat(2,1fr);border-left:1px solid var(--border);border-top:1px solid var(--border)}.room-card{padding:20px;border-right:1px solid var(--border);border-bottom:1px solid var(--border);display:flex;flex-direction:column;gap:12px}.empty{padding:25px}
@media(max-width:767px){.page-head,.room-content{padding-left:28px;padding-right:28px}.room-grid{grid-template-columns:1fr}.row{flex-wrap:wrap}}
</style>
