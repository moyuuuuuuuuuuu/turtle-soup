<script setup lang="ts">
/* eslint-disable style/max-statements-per-line */
import { gameApi } from '@/api/turtle'
import { useGameSocket } from '@/composables/useGameSocket'
import { useGameStore } from '@/store/gameStore'

definePage({ name: 'game', layout: 'tabbar', style: { navigationStyle: 'custom' } })
const route = useRoute()
const router = useRouter()
const store = useGameStore()
const socket = useGameSocket()
const question = ref('')
const teamMessage = ref('')
const tab = ref<'judge' | 'team'>('judge')
const busy = ref(false)
const errorMessage = ref('')
const game = computed(() => store.current)
const room = socket.roomSnapshot
const typingMembers = socket.typingMembers
const gameId = computed(() => String(route.params.id || route.query.id || ''))
watch(socket.gameSnapshot, value => value && store.setGame(value))
async function refresh() {
  try { store.setGame(await socket.join(gameId.value)) }
  catch { store.setGame(await gameApi.read(gameId.value)) }
  if (game.value?.mode === 'multiplayer' && game.value.room_id)
    await socket.roomJoin(game.value.room_id)
}
async function ask() {
  if (!question.value.trim())
    return
  busy.value = true; errorMessage.value = ''
  try { store.setGame(await socket.ask(game.value!.id, question.value)); question.value = '' }
  catch (error) { errorMessage.value = (error as Error).message; uni.showToast({ title: 'AI 判定失败，可原样重试', icon: 'none' }) }
  finally { busy.value = false }
}
async function hint(level: number) {
  try { store.setGame(await socket.hint(game.value!.id, level)) }
  catch (error) { uni.showToast({ title: (error as Error).message, icon: 'none' }) }
}
async function sendTeam() {
  if (!teamMessage.value.trim() || !game.value?.room_id)
    return; await socket.roomChat(game.value.room_id, teamMessage.value); teamMessage.value = ''; await socket.typing(game.value.room_id, false)
}
let typingTimer: ReturnType<typeof setTimeout> | undefined
function teamTyping() {
  if (!game.value?.room_id)
    return; socket.typing(game.value.room_id, true); if (typingTimer)
    clearTimeout(typingTimer); typingTimer = setTimeout(() => socket.typing(game.value!.room_id!, false), 1200)
}
function abandon() { uni.showModal({ title: '确认放弃？', content: '放弃后会立即展示汤底。多人房间仅房主可以放弃。', success: async (result) => { if (result.confirm) { store.setGame(await gameApi.abandon(game.value!.id)); router.replace({ name: 'result', params: { id: game.value!.id } }) } } }) }
onMounted(refresh)
onUnmounted(() => typingTimer && clearTimeout(typingTimer))
</script>

<template>
  <view v-if="game" class="game-page">
    <aside class="puzzle-panel">
      <text class="hgt-mono puzzle-id">
        ◉ {{ game.mode === 'multiplayer' ? '多人房间' : '单人推理' }}
      </text><text class="hgt-display puzzle-title">
        {{ game.title }}
      </text><text class="surface">
        {{ game.surface }}
      </text>
      <view v-if="game.mode === 'multiplayer' && room" class="team-block">
        <view class="section-row">
          <text class="hgt-mono label">
            队伍
          </text><text class="hgt-mono label">
            {{ room.member_count }}/{{ room.max_players }}
          </text>
        </view><view v-for="member in room.members" :key="member.user_id" class="member">
          <image v-if="member.avatar_url" :src="member.avatar_url" class="avatar" /><view v-else class="avatar avatar-fallback">
            {{ member.username.slice(0, 1) }}
          </view><text>{{ member.username }}</text><text class="member-role hgt-mono">
            {{ member.role === 'owner' ? '房主' : '' }}
          </text>
        </view>
      </view>
      <view class="question-count">
        <view class="section-row">
          <text class="hgt-mono label">
            提问次数
          </text><text class="hgt-display count">
            {{ game.question_count }}/{{ game.question_limit }}
          </text>
        </view><view class="progress">
          <view :style="{ width: `${game.question_count / game.question_limit * 100}%` }" />
        </view>
      </view>
      <view class="panel-actions">
        <button class="hgt-mono outline" @click="router.push({ name: 'guess', params: { id: game.id } })">
          最终猜测
        </button><button class="danger hgt-mono" @click="abandon">
          放弃游戏
        </button>
      </view>
    </aside>
    <main class="conversation">
      <view v-if="game.mode === 'multiplayer'" class="tabs">
        <button :class="{ active: tab === 'judge' }" @click="tab = 'judge'">
          问答记录 <text>裁判可见</text>
        </button><button :class="{ active: tab === 'team' }" @click="tab = 'team'">
          队伍讨论 <text>仅队友可见</text>
        </button>
      </view>
      <view v-else class="solo-head">
        <text class="hgt-mono">
          ◈ AI 裁判在线
        </text>
      </view>
      <template v-if="tab === 'judge' || game.mode === 'single'">
        <scroll-view scroll-y class="messages">
          <view v-for="message in game.messages" :key="message.sequence" class="message" :class="message.role">
            <text class="message-role hgt-mono">
              {{ message.role === 'host' ? '裁判' : '玩家' }}
            </text><text>{{ message.content }}</text>
          </view>
        </scroll-view>
        <view class="composer">
          <view v-if="errorMessage" class="error">
            上次问题未扣次数：{{ errorMessage }}
          </view><view class="hints">
            <button v-for="level in [1, 2, 3]" :key="level" :disabled="game.used_hints.includes(level)" @click="hint(level)">
              提示 {{ level }}
            </button>
          </view><view class="input-row">
            <input v-model="question" :disabled="game.remaining_questions === 0" placeholder="输入只能用是/否回答的问题"><button :loading="busy" :disabled="game.remaining_questions === 0" @click="ask">
              提问
            </button>
          </view>
        </view>
      </template>
      <template v-else>
        <scroll-view scroll-y class="messages">
          <view v-for="message in room?.messages || []" :key="message.sequence" class="message team">
            <text class="message-role hgt-mono">
              {{ message.username }}
            </text><text>{{ message.content }}</text>
          </view>
        </scroll-view><view class="composer">
          <text v-if="typingMembers.length" class="typing hgt-mono">
            {{ typingMembers.map(item => item.username).join('、') }} 正在输入…
          </text><view class="input-row">
            <input v-model="teamMessage" placeholder="队伍内部讨论" @input="teamTyping"><button @click="sendTeam">
              发送
            </button>
          </view>
        </view>
      </template>
    </main>
  </view>
</template>

<style scoped>
.game-page{height:100vh;display:grid;grid-template-columns:330px 1fr}.puzzle-panel{border-right:1px solid var(--border);background:var(--card);padding:32px;display:flex;flex-direction:column}.puzzle-id,.label,.message-role,.typing{font-size:10px;color:var(--muted-foreground);letter-spacing:.15em}.puzzle-title{font-size:28px;margin:16px 0}.surface{font-size:14px;line-height:1.9;color:var(--accent);padding-bottom:28px;border-bottom:1px solid var(--border)}.team-block,.question-count{padding:25px 0;border-bottom:1px solid var(--border)}.section-row{display:flex;justify-content:space-between;align-items:center}.member{display:flex;align-items:center;gap:10px;margin-top:14px;font-size:12px}.avatar{width:32px;height:32px;border-radius:50%}.avatar-fallback{display:flex;align-items:center;justify-content:center;background:var(--secondary)}.member-role{margin-left:auto;font-size:9px;color:var(--muted-foreground)}.count{font-size:20px}.progress{height:2px;background:var(--border);margin-top:12px}.progress>view{height:100%;background:var(--foreground)}.panel-actions{margin-top:auto;display:flex;flex-direction:column;gap:10px}.outline,.danger{margin:0;border-radius:0;background:transparent;border:1px solid var(--border);color:var(--muted-foreground);font-size:11px}.danger{color:#f87171}.conversation{min-width:0;display:flex;flex-direction:column}.tabs{height:65px;border-bottom:1px solid var(--border);display:flex}.tabs button{margin:0;padding:0 28px;border-radius:0;background:transparent;color:var(--muted-foreground);font-size:13px}.tabs button text{font-size:9px;display:block}.tabs button.active{color:var(--foreground);border-bottom:1px solid var(--foreground)}button::after{display:none}.solo-head{height:64px;border-bottom:1px solid var(--border);display:flex;align-items:center;padding:0 28px;color:#4ade80}.messages{height:0;flex:1;padding:24px;box-sizing:border-box}.message{max-width:75%;padding:14px 16px;margin-bottom:14px;border:1px solid var(--border);display:flex;flex-direction:column;gap:7px;line-height:1.6;font-size:13px}.message.player{margin-left:auto;background:var(--secondary)}.message.host,.message.team{background:var(--card)}.composer{border-top:1px solid var(--border);padding:16px 22px;background:var(--card)}.error{color:#facc15;font-size:11px;margin-bottom:10px}.hints{display:flex;gap:8px;margin-bottom:10px}.hints button{margin:0;padding:0 12px;height:30px;line-height:28px;border-radius:0;border:1px solid var(--border);background:transparent;color:var(--muted-foreground);font-size:10px}.input-row{display:flex}.input-row input{height:46px;flex:1;border:1px solid var(--border);padding:0 15px}.input-row button{margin:0;width:86px;border-radius:0;background:var(--foreground);color:var(--background)}
@media(max-width:767px){.game-page{height:calc(100vh - 56px);grid-template-columns:1fr}.puzzle-panel{display:none}.tabs{height:58px}.messages{padding:16px}.message{max-width:88%}.composer{padding:12px}}
</style>
