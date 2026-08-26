<script setup lang="ts">
/* eslint-disable style/max-statements-per-line */
import type { RoomSnapshot } from '@/types/game'
import { roomApi } from '@/api/turtle'
import { useGameSocket } from '@/composables/useGameSocket'

definePage({ name: 'room', layout: 'tabbar', style: { navigationStyle: 'custom' } })
const route = useRoute()
const router = useRouter()
const socket = useGameSocket()
const typingMembers = socket.typingMembers
const room = ref<RoomSnapshot | null>(null)
const chat = ref('')
const roomId = computed(() => String(route.params.id || route.query.id || ''))
watch(socket.roomSnapshot, (value) => {
  if (value?.id === roomId.value)
    room.value = value; if (value?.status === 'playing' && value.game_id)
    router.replace({ name: 'game', params: { id: value.game_id } })
})
let typingTimer: ReturnType<typeof setTimeout> | undefined
function typing() {
  socket.typing(roomId.value, true); if (typingTimer)
    clearTimeout(typingTimer); typingTimer = setTimeout(() => socket.typing(roomId.value, false), 1200)
}
async function sendChat() {
  if (!chat.value.trim())
    return; await socket.roomChat(roomId.value, chat.value); chat.value = ''; await socket.typing(roomId.value, false)
}
async function toggleReady() { const me = room.value?.members.find(item => item.is_self); room.value = await roomApi.ready(roomId.value, !me?.is_ready) }
async function start() {
  room.value = await roomApi.start(roomId.value); if (room.value.game_id)
    router.replace({ name: 'game', params: { id: room.value.game_id } })
}
async function leave() {
  await roomApi.leave(roomId.value); router.replace({ name: 'rooms' })
}
onMounted(async () => { room.value = await roomApi.read(roomId.value); await socket.roomJoin(roomId.value) })
onUnmounted(() => typingTimer && clearTimeout(typingTimer))
</script>

<template>
  <view v-if="room" class="room-page">
    <view class="room-head">
      <view>
        <text class="hgt-mono eyebrow">
          邀请码 {{ room.invite_code }}
        </text><text class="hgt-display title">
          {{ room.name }}
        </text>
      </view><button class="hgt-mono outline" @click="leave">
        {{ room.is_owner ? '退出并转让' : '退出房间' }}
      </button>
    </view>
    <view class="lobby-grid">
      <view class="members">
        <text class="hgt-mono section-label">
          队伍 {{ room.member_count }}/{{ room.max_players }}
        </text><view v-for="member in room.members" :key="member.user_id" class="member">
          <image v-if="member.avatar_url" :src="member.avatar_url" class="avatar" /><view v-else class="avatar fallback">
            {{ member.username.slice(0, 1) }}
          </view><view class="member-info">
            <text>{{ member.username }}</text><text class="hgt-mono meta">
              {{ member.role === 'owner' ? '房主' : member.is_ready ? '已准备' : '未准备' }}
            </text>
          </view><text v-if="member.is_ready" class="ready">
            ✓
          </text>
        </view><button v-if="!room.is_owner" class="primary hgt-mono" @click="toggleReady">
          切换准备状态
        </button><button v-else class="primary hgt-mono" @click="start">
          开始游戏
        </button>
      </view>
      <view class="discussion">
        <text class="hgt-mono section-label">
          队伍讨论 · 仅队友可见
        </text><scroll-view scroll-y class="messages">
          <view v-for="message in room.messages" :key="message.sequence" class="message">
            <text class="hgt-mono meta">
              {{ message.username }}
            </text><text>{{ message.content }}</text>
          </view>
        </scroll-view><text v-if="typingMembers.length" class="typing hgt-mono">
          {{ typingMembers.map(item => item.username).join('、') }} 正在输入…
        </text><view class="chat-row">
          <input v-model="chat" class="chat-input" placeholder="和队友讨论，不会发送给裁判" @input="typing"><button class="send hgt-mono" @click="sendChat">
            发送
          </button>
        </view>
      </view>
    </view>
  </view>
</template>

<style scoped>
.room-page{min-height:100vh}.room-head{padding:32px 48px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center}.eyebrow,.section-label,.meta,.typing{font-size:11px;color:var(--muted-foreground);letter-spacing:.13em}.title{font-size:34px;display:block;margin-top:8px}.outline,.primary,.send{margin:0;border-radius:0;font-size:11px;letter-spacing:.12em}.outline{border:1px solid var(--border);background:transparent;color:var(--muted-foreground)}.lobby-grid{display:grid;grid-template-columns:320px 1fr;min-height:calc(100vh - 110px)}.members{border-right:1px solid var(--border);padding:28px}.member{display:flex;align-items:center;gap:12px;padding:14px 0;border-bottom:1px solid var(--border)}.avatar{width:38px;height:38px;border-radius:50%}.fallback{display:flex;align-items:center;justify-content:center;background:var(--secondary)}.member-info{display:flex;flex:1;flex-direction:column;gap:4px}.ready{color:#4ade80}.primary{margin-top:20px;width:100%;background:var(--foreground);color:var(--background)}.discussion{padding:28px;display:flex;min-height:0;flex-direction:column}.messages{height:0;flex:1;margin:20px 0}.message{padding:12px 0;border-bottom:1px solid var(--border);display:flex;flex-direction:column;gap:7px}.typing{height:24px}.chat-row{display:flex}.chat-input{height:46px;flex:1;border:1px solid var(--border);padding:0 14px}.send{width:80px;background:var(--foreground);color:var(--background)}button::after{display:none}
@media(max-width:767px){.room-head{padding:25px 28px}.lobby-grid{grid-template-columns:1fr}.members{border-right:0;border-bottom:1px solid var(--border)}.discussion{min-height:440px}}
</style>
