<script setup lang="ts">
/* eslint-disable style/max-statements-per-line */
import { gameApi, questionApi, roomApi } from '@/api/turtle'
import { useGameSocket } from '@/composables/useGameSocket'
import { resolveShareUrl } from '@/config/endpoints'
import { useGameStore } from '@/store/gameStore'
import { usePlayerStore } from '@/store/playerStore'

definePage({ name: 'game', layout: 'tabbar', style: { navigationStyle: 'custom' } })
const route = useRoute()
const router = useRouter()
const store = useGameStore()
const player = usePlayerStore()
const socket = useGameSocket()
const question = ref('')
const teamMessage = ref('')
const tab = ref<'judge' | 'team'>('judge')
const inputMode = ref<'question' | 'bottom'>('question')
const busy = ref(false)
const creatingRoom = ref(false)
const roomPrivacyUpdating = ref(false)
const inviteOpen = ref(false)
const resultOpen = ref(false)
const confirmOpen = ref(false)
const confirmTitle = ref('')
const confirmDescription = ref('')
const confirmEyebrow = ref('请确认')
const confirmTone = ref<'default' | 'warning' | 'danger'>('default')
let confirmAction: (() => void | Promise<void>) | undefined
const mobileSurfaceOpen = ref(true)
const mobileTeamOpen = ref(false)
const mobileSurfaceRef = ref<HTMLElement | { $el?: HTMLElement } | null>(null)
const mobileSurfaceOverflow = ref(false)
const errorMessage = ref('')
const unreadTeam = ref(0)
const game = computed(() => store.current)
const room = socket.roomSnapshot
const sortedRoomMembers = computed(() => [...(room.value?.members || [])].sort((left, right) => {
  if (left.role === right.role)
    return 0
  return left.role === 'owner' ? -1 : 1
}))
const canControlResult = computed(() => game.value?.mode !== 'multiplayer' || room.value?.is_owner === true)
const typingMembers = socket.typingMembers
const gameId = computed(() => String(route.params.id || route.query.id || ''))
let switchingGameId = ''
const riskTypeLabels: Record<string, string> = { death: '死亡', violence: '暴力', gore: '血腥', self_harm: '自伤', sexual: '性内容', child_safety: '未成年人', discrimination: '歧视', illegal: '违法', substance: '成瘾物', other: '其他' }
const riskTypeLabel = (value: string) => riskTypeLabels[value] || value
watch(socket.gameSnapshot, (value) => {
  if (!value) {
    return
  }
  store.setGame(value)
  if (['solved', 'finished', 'abandoned'].includes(value.status)) {
    resultOpen.value = true
  }
})
async function switchToRoomGame(nextGameId: string) {
  if (!nextGameId || nextGameId === game.value?.id || nextGameId === switchingGameId)
    return
  switchingGameId = nextGameId
  resultOpen.value = false
  errorMessage.value = ''
  try {
    store.setGame(await socket.join(nextGameId))
    await router.replace({ name: 'game', params: { id: nextGameId } })
  }
  catch (error) {
    uni.showToast({ title: (error as Error).message, icon: 'none' })
  }
  finally {
    switchingGameId = ''
  }
}
watch(() => socket.roomNextStarted.value?.nonce, () => {
  const next = socket.roomNextStarted.value
  if (next && next.room_id === room.value?.id)
    void switchToRoomGame(next.game_id)
})
watch(() => room.value?.game_id, (nextGameId) => {
  if (nextGameId)
    void switchToRoomGame(nextGameId)
})
watch(() => room.value?.messages.length || 0, (count, previous) => {
  if (count > previous && tab.value !== 'team') {
    unreadTeam.value += count - previous
  }
})
watch(tab, (value) => {
  if (value === 'team') {
    unreadTeam.value = 0
  }
})
watch(socket.kickedRoomId, (value) => {
  if (value && value === game.value?.room_id) {
    uni.showToast({ title: '你已被房主移出房间', icon: 'none' })
    router.replace({ name: 'questions' })
  }
})
watch(socket.memberLeftNotice, (notice) => {
  if (!notice || notice.room_id !== game.value?.room_id)
    return
  const suffix = notice.reason === 'switch_question' ? '，已开始推理其他题目' : ''
  uni.showToast({ title: `${notice.username}已退出房间${suffix}`, icon: 'none' })
})
async function refresh() {
  try { store.setGame(await socket.join(gameId.value)) }
  catch { store.setGame(await gameApi.read(gameId.value)) }
  if (game.value?.mode === 'multiplayer' && game.value.room_id)
    await socket.roomJoin(game.value.room_id)
  if (route.query.show_result === '1' && game.value && ['solved', 'finished', 'abandoned'].includes(game.value.status))
    resultOpen.value = true
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
  if (!teamMessage.value.trim() || !game.value?.room_id) {
    return
  }
  await socket.roomChat(game.value.room_id, teamMessage.value)
  teamMessage.value = ''
  await socket.typing(game.value.room_id, false)
}
let typingTimer: ReturnType<typeof setTimeout> | undefined
function teamTyping() {
  if (!game.value?.room_id) {
    return
  }
  socket.typing(game.value.room_id, true)
  if (typingTimer) {
    clearTimeout(typingTimer)
  }
  typingTimer = setTimeout(() => socket.typing(game.value!.room_id!, false), 1200)
}
function isTyping(userId: number) { return typingMembers.value.some(item => item.user_id === userId) }
function messageSender(message: { user_id?: number | null, username?: string | null, avatar_url?: string | null }) {
  const member = room.value?.members.find(item => item.user_id === message.user_id)
  return { username: message.username || member?.username || '玩家', avatar_url: message.avatar_url || member?.avatar_url }
}
async function toggleMute(member: { user_id: number, is_muted?: boolean }) {
  if (room.value) {
    await socket.roomMute(room.value.id, member.user_id, !member.is_muted)
  }
}
function openConfirm(options: { title: string, description: string, eyebrow?: string, tone?: 'default' | 'warning' | 'danger', action: () => void | Promise<void> }) {
  confirmTitle.value = options.title
  confirmDescription.value = options.description
  confirmEyebrow.value = options.eyebrow || '请确认'
  confirmTone.value = options.tone || 'default'
  confirmAction = options.action
  confirmOpen.value = true
}
async function runConfirmAction() {
  const action = confirmAction
  confirmAction = undefined
  if (action)
    await action()
}
function cancelConfirmAction() {
  confirmAction = undefined
}
function kickMember(member: { user_id: number, username: string }) {
  if (!room.value) {
    return
  }
  openConfirm({
    eyebrow: '房间管理',
    title: '移出队友',
    description: `确认将 ${member.username} 移出房间？`,
    tone: 'danger',
    action: () => socket.roomKick(room.value!.id, member.user_id),
  })
}
async function updateRoomPrivacy(event: { value: boolean | string | number }) {
  if (!room.value?.is_owner || roomPrivacyUpdating.value)
    return
  roomPrivacyUpdating.value = true
  try {
    await socket.roomVisibility(room.value.id, event.value ? 'private' : 'public')
    uni.showToast({ title: event.value ? '已设为私密房间' : '已设为公开房间', icon: 'none' })
  }
  catch (error) {
    uni.showToast({ title: (error as Error).message, icon: 'none' })
  }
  finally {
    roomPrivacyUpdating.value = false
  }
}
async function leaveRoom() {
  if (!room.value) {
    return
  }
  await socket.roomLeave(room.value.id)
  router.replace({ name: 'questions' })
}
async function submitBottom() {
  if (!question.value.trim())
    return
  busy.value = true; errorMessage.value = ''
  try {
    store.setGame(await socket.guess(game.value!.id, question.value))
    question.value = ''
    resultOpen.value = true
  }
  catch (error) {
    errorMessage.value = (error as Error).message
    uni.showToast({ title: (error as Error).message, icon: 'none' })
  }
  finally { busy.value = false }
}
function submitJudgeInput() {
  if (inputMode.value === 'bottom')
    return submitBottom()
  return ask()
}
async function invite() {
  if (!player.user) {
    uni.showToast({ title: '登录后才能邀请队友', icon: 'none' })
    router.push({ name: 'player-login' })
    return
  }
  if (!game.value?.room_id) {
    creatingRoom.value = true
    try {
      const created = await roomApi.create({ game_id: game.value!.id, max_players: 6, visibility: 'public' })
      store.setGame(await gameApi.read(game.value!.id))
      await socket.roomJoin(created.id)
    }
    catch (error) {
      uni.showToast({ title: (error as Error).message, icon: 'none' })
      return
    }
    finally {
      creatingRoom.value = false
    }
  }
  inviteOpen.value = true
}
async function copyInviteLink() {
  const currentRoom = room.value || (game.value?.room_id ? await roomApi.read(game.value.room_id) : null)
  if (!currentRoom)
    return
  const questionId = currentRoom.question_id || game.value?.question_id || ''
  const query = [`invite_code=${encodeURIComponent(currentRoom.invite_code)}`]
  if (questionId)
    query.push(`question_id=${encodeURIComponent(questionId)}`)
  const sharePath = `/pages/rooms/index?${query.join('&')}`
  const link = resolveShareUrl(sharePath)
  uni.setClipboardData({ data: link, success: () => uni.showToast({ title: '邀请链接已复制', icon: 'success' }) })
}
function backToQuestion() {
  if (game.value?.question_id)
    uni.navigateTo({ url: `/pages/question-detail/index?id=${encodeURIComponent(game.value.question_id)}` })
  else router.push({ name: 'questions' })
}
async function goHome() {
  if (game.value?.mode === 'multiplayer' && game.value.room_id && room.value?.is_owner) {
    try {
      await roomApi.close(game.value.room_id)
      socket.clearRoom()
    }
    catch (error) {
      uni.showToast({ title: (error as Error).message, icon: 'none' })
      return
    }
  }
  uni.switchTab({ url: '/pages/index/index' })
}
async function continuePlaying() {
  if (busy.value)
    return
  resultOpen.value = false
  busy.value = true
  try {
    if (game.value?.room_id && room.value?.is_owner) {
      await socket.roomNext(game.value.room_id)
      return
    }
    const nextQuestion = await questionApi.random(undefined, 'safe')
    const nextGame = await gameApi.create(nextQuestion.id)
    store.setGame(nextGame)
    await router.replace({ name: 'game', params: { id: nextGame.id } })
  }
  catch (error) {
    resultOpen.value = true
    uni.showToast({ title: (error as Error).message, icon: 'none' })
  }
  finally {
    busy.value = false
  }
}
function measureMobileSurface() {
  if (mobileSurfaceOpen.value)
    return
  const value = mobileSurfaceRef.value
  const element = typeof HTMLElement !== 'undefined' && value instanceof HTMLElement
    ? value
    : (value as { $el?: HTMLElement } | null)?.$el
  mobileSurfaceOverflow.value = Boolean(element && element.scrollHeight > element.clientHeight + 1)
}
function abandon() {
  if (game.value?.mode === 'multiplayer' && !room.value?.is_owner) {
    uni.showToast({ title: '仅房主可以放弃游戏', icon: 'none' })
    return
  }
  openConfirm({
    eyebrow: '结束本局',
    title: '确认放弃？',
    description: '放弃后会立即结束本局并展示汤底。',
    tone: 'danger',
    action: async () => {
      try {
        store.setGame(game.value?.mode === 'multiplayer'
          ? await socket.abandon(game.value!.id)
          : await gameApi.abandon(game.value!.id))
        resultOpen.value = true
      }
      catch (error) {
        uni.showToast({ title: (error as Error).message, icon: 'none' })
      }
    },
  })
}
watch(() => game.value?.surface, async () => {
  mobileSurfaceOpen.value = false
  await nextTick()
  measureMobileSurface()
  mobileSurfaceOpen.value = true
})
onMounted(async () => {
  await player.restore()
  await refresh()
  mobileSurfaceOpen.value = false
  await nextTick()
  measureMobileSurface()
  mobileSurfaceOpen.value = true
  if (typeof window !== 'undefined')
    window.addEventListener('resize', measureMobileSurface)
})
onUnmounted(() => {
  if (typingTimer)
    clearTimeout(typingTimer)
  if (typeof window !== 'undefined')
    window.removeEventListener('resize', measureMobileSurface)
})
</script>

<template>
  <view v-if="game" class="game-page">
    <aside class="puzzle-panel">
      <button class="back-question hgt-mono" @click="backToQuestion">
        ← 返回题目
      </button>
      <text class="hgt-mono puzzle-id">
        ◉ {{ game.mode === 'multiplayer' ? '多人房间' : '单人推理' }}
      </text><text class="hgt-display puzzle-title">
        {{ game.title }}
      </text><text class="surface">
        {{ game.surface }}
      </text>
      <view v-if="game.risk_types?.length || game.tags?.length" class="puzzle-metadata">
        <view v-if="game.risk_types?.length" class="metadata-group">
          <text class="hgt-mono metadata-label">
            风险类型
          </text>
          <view class="metadata-items">
            <text v-for="riskType in game.risk_types" :key="riskType" class="metadata-chip risk-chip">
              {{ riskTypeLabel(riskType) }}
            </text>
          </view>
        </view>
        <view v-if="game.tags?.length" class="metadata-group">
          <text class="hgt-mono metadata-label">
            标签
          </text>
          <view class="metadata-items">
            <text v-for="tag in game.tags" :key="tag.id" class="metadata-chip">
              {{ tag.name }}
            </text>
          </view>
        </view>
      </view>
      <view v-if="game.mode === 'multiplayer' && room" class="room-privacy-row">
        <view class="room-privacy-copy">
          <text class="hgt-mono">
            私密房间
          </text>
          <text>{{ room.visibility === 'private' ? '仅可通过邀请码加入' : '会展示在公开房间列表' }}</text>
        </view>
        <wd-switch v-if="room.is_owner" :model-value="room.visibility === 'private'" :loading="roomPrivacyUpdating" size="18" shape="square" active-color="var(--foreground)" inactive-color="var(--border)" @change="updateRoomPrivacy" />
        <text v-else class="metadata-chip">
          {{ room.visibility === 'private' ? '私密' : '公开' }}
        </text>
      </view>
      <view v-if="game.mode === 'multiplayer' && room" class="team-block">
        <view class="section-row">
          <text class="hgt-mono label">
            队伍
          </text><text class="hgt-mono label">
            {{ room.member_count }}/{{ room.max_players }}
          </text>
        </view><view v-for="member in sortedRoomMembers" :key="member.user_id" class="member">
          <image v-if="member.avatar_url" :src="member.avatar_url" class="avatar" /><view v-else class="avatar avatar-fallback">
            {{ member.username.slice(0, 1) }}
          </view><view class="member-info">
            <text>{{ member.username }}</text><text v-if="isTyping(member.user_id)" class="typing hgt-mono">
              正在输入中…
            </text>
          </view><text class="member-role hgt-mono">
            {{ member.role === 'owner' ? '房主' : (member.is_muted ? '已禁言' : '') }}
          </text>
          <view v-if="room.is_owner && !member.is_self" class="member-actions">
            <button @click="toggleMute(member)">
              {{ member.is_muted ? '解除禁言' : '禁言' }}
            </button><button class="kick" @click="kickMember(member)">
              踢出
            </button>
          </view>
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
        <button v-if="player.user && (!room || room.member_count < room.max_players)" class="hgt-mono outline" :loading="creatingRoom" @click="invite">
          {{ creatingRoom ? '正在创建房间…' : '+ 邀请队友' }}
        </button>
        <button v-if="game.mode === 'multiplayer' && room && !room.is_owner" class="danger hgt-mono" @click="leaveRoom">
          退出房间
        </button>
        <button v-if="game.mode === 'single' || room?.is_owner" class="danger hgt-mono" @click="abandon">
          放弃游戏
        </button>
      </view>
    </aside>
    <main class="conversation">
      <view class="mobile-puzzle-summary">
        <view class="mobile-puzzle-row">
          <button class="mobile-help hgt-mono" @click="mobileSurfaceOpen = !mobileSurfaceOpen">
            ?
          </button>
          <text class="hgt-display mobile-puzzle-title">
            {{ game.title }}
          </text>
          <text class="hgt-mono mobile-question-count">
            {{ game.question_count }}/{{ game.question_limit }}
          </text>
        </view>
        <view class="mobile-surface-wrap">
          <text ref="mobileSurfaceRef" class="surface mobile-surface" :class="{ expanded: mobileSurfaceOpen }">
            {{ game.surface }}
          </text>
          <button v-if="mobileSurfaceOverflow" class="mobile-expand hgt-mono" :aria-label="mobileSurfaceOpen ? '收起题目内容' : '展开题目内容'" @click="mobileSurfaceOpen = !mobileSurfaceOpen">
            {{ mobileSurfaceOpen ? '▴' : '▾' }}
          </button>
        </view>
        <view v-if="(game.mode === 'multiplayer' && room) || game.risk_types?.length || game.tags?.length" class="mobile-team">
          <button class="mobile-team-toggle" @click="mobileTeamOpen = !mobileTeamOpen">
            <view class="section-row">
              <text class="hgt-mono label">
                {{ game.mode === 'multiplayer' && room ? '队伍与题目信息' : '题目信息' }}
              </text><text v-if="game.mode === 'multiplayer' && room" class="hgt-mono label">
                {{ room.member_count }}/{{ room.max_players }}
              </text>
            </view>
            <text class="hgt-mono">
              {{ mobileTeamOpen ? '▴' : '▾' }}
            </text>
          </button>
          <view v-if="mobileTeamOpen" class="mobile-team-details">
            <view v-if="game.risk_types?.length || game.tags?.length" class="puzzle-metadata mobile-team-metadata">
              <view v-if="game.risk_types?.length" class="metadata-group">
                <text class="hgt-mono metadata-label">
                  风险类型
                </text>
                <view class="metadata-items">
                  <text v-for="riskType in game.risk_types" :key="riskType" class="metadata-chip risk-chip">
                    {{ riskTypeLabel(riskType) }}
                  </text>
                </view>
              </view>
              <view v-if="game.tags?.length" class="metadata-group">
                <text class="hgt-mono metadata-label">
                  标签
                </text>
                <view class="metadata-items">
                  <text v-for="tag in game.tags" :key="tag.id" class="metadata-chip">
                    {{ tag.name }}
                  </text>
                </view>
              </view>
            </view>
            <view v-if="game.mode === 'multiplayer' && room" class="room-privacy-row mobile-room-privacy">
              <view class="room-privacy-copy">
                <text class="hgt-mono">
                  私密房间
                </text>
                <text>{{ room.visibility === 'private' ? '仅可通过邀请码加入' : '会展示在公开房间列表' }}</text>
              </view>
              <wd-switch v-if="room.is_owner" :model-value="room.visibility === 'private'" :loading="roomPrivacyUpdating" size="18" shape="square" active-color="var(--foreground)" inactive-color="var(--border)" @change="updateRoomPrivacy" />
              <text v-else class="metadata-chip">
                {{ room.visibility === 'private' ? '私密' : '公开' }}
              </text>
            </view>
            <view v-for="member in (game.mode === 'multiplayer' && room ? sortedRoomMembers : [])" :key="member.user_id" class="member">
              <image v-if="member.avatar_url" :src="member.avatar_url" class="avatar" /><view v-else class="avatar avatar-fallback">
                {{ member.username.slice(0, 1) }}
              </view>
              <view class="member-info">
                <text>{{ member.username }}</text><text v-if="isTyping(member.user_id)" class="typing hgt-mono">
                  正在输入中…
                </text>
              </view>
              <text class="member-role hgt-mono">
                {{ member.role === 'owner' ? '房主' : (member.is_muted ? '已禁言' : '') }}
              </text>
              <view v-if="room?.is_owner && !member.is_self" class="member-actions mobile-member-actions">
                <button @click="toggleMute(member)">
                  {{ member.is_muted ? '解除禁言' : '禁言' }}
                </button><button class="kick" @click="kickMember(member)">
                  踢出
                </button>
              </view>
            </view>
            <button v-if="game.mode === 'multiplayer' && room && player.user && room.member_count < room.max_players" class="hgt-mono outline" :loading="creatingRoom" @click="invite">
              {{ creatingRoom ? '正在创建房间…' : '+ 邀请队友' }}
            </button>
            <button v-if="game.mode === 'multiplayer' && room && !room.is_owner" class="danger hgt-mono" @click="leaveRoom">
              退出房间
            </button>
          </view>
        </view>
        <view class="mobile-actions">
          <button v-if="game.mode === 'single' && player.user" class="hgt-mono outline" :loading="creatingRoom" @click="invite">
            {{ creatingRoom ? '正在创建房间…' : '+ 邀请队友' }}
          </button>
          <button v-if="game.mode === 'single' || room?.is_owner" class="danger hgt-mono" @click="abandon">
            放弃游戏
          </button>
        </view>
      </view>
      <view v-if="game.mode === 'multiplayer'" class="tabs">
        <button :class="{ active: tab === 'judge' }" @click="tab = 'judge'">
          问答记录 <text>裁判可见</text>
        </button><button :class="{ active: tab === 'team', unread: unreadTeam > 0 }" @click="tab = 'team'">
          <view class="tab-title">
            队伍讨论 <text v-if="unreadTeam" class="unread-badge">
              {{ unreadTeam > 99 ? '99+' : unreadTeam }}
            </text>
          </view><text>仅队友可见</text>
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
            <view class="message-author">
              <image v-if="message.role === 'player' && messageSender(message).avatar_url" :src="messageSender(message).avatar_url!" class="message-avatar" /><view v-else-if="message.role === 'player'" class="message-avatar avatar-fallback">
                {{ messageSender(message).username.slice(0, 1) }}
              </view><text class="message-role hgt-mono">
                {{ message.role === 'host' ? '裁判' : messageSender(message).username }}
              </text>
            </view><text>{{ message.content }}</text>
          </view>
        </scroll-view>
        <view class="composer">
          <view v-if="errorMessage" class="error">
            上次问题未扣次数：{{ errorMessage }}
          </view><view class="hints">
            <button v-for="level in [1, 2, 3]" :key="level" :disabled="game.used_hints.includes(level)" @click="hint(level)">
              提示 {{ level }}
            </button>
            <button class="bottom-mode" :class="{ active: inputMode === 'bottom' }" @click="inputMode = inputMode === 'bottom' ? 'question' : 'bottom'">
              汤底
            </button>
          </view><view class="input-row">
            <input v-model="question" :disabled="inputMode === 'question' && game.remaining_questions === 0" confirm-type="send" :placeholder="inputMode === 'bottom' ? '输入你推理出的汤底' : '输入只能用是/否回答的问题'" @confirm="submitJudgeInput"><button :loading="busy" :disabled="inputMode === 'question' && game.remaining_questions === 0" @click="submitJudgeInput">
              {{ inputMode === 'bottom' ? '提交汤底' : '提问' }}
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
            <input v-model="teamMessage" :disabled="room?.members.find(item => item.is_self)?.is_muted" confirm-type="send" :placeholder="room?.members.find(item => item.is_self)?.is_muted ? '你已被房主禁言' : '队伍内部讨论'" @input="teamTyping" @confirm="sendTeam"><button :disabled="room?.members.find(item => item.is_self)?.is_muted" @click="sendTeam">
              发送
            </button>
          </view>
        </view>
      </template>
    </main>
    <wd-popup v-if="room" v-model="inviteOpen" position="center" :root-portal="true" custom-class="invite-popup">
      <view class="invite-modal">
        <text class="hgt-mono label">
          邀请队友
        </text><text class="hgt-display invite-heading">
          分享房间链接
        </text>
        <view class="invite-link-row">
          <text class="hgt-mono invite-code">
            邀请码 {{ room.invite_code }}
          </text><button class="copy-button hgt-mono" @click="copyInviteLink">
            复制链接
          </button>
        </view>
        <text class="hgt-mono invite-members-title">
          当前队伍 ({{ room.member_count }}/{{ room.max_players }})
        </text>
        <view v-for="member in sortedRoomMembers" :key="member.user_id" class="invite-member">
          <view class="avatar avatar-fallback">
            {{ member.username.slice(0, 1) }}
          </view><text>{{ member.username }}</text><text class="member-role hgt-mono">
            {{ member.role === 'owner' ? '房主' : '在线' }}
          </text>
        </view>
        <button class="close-invite hgt-mono" @click="inviteOpen = false">
          关闭
        </button>
      </view>
    </wd-popup>
    <wd-popup v-model="resultOpen" position="center" :close-on-click-modal="true" :root-portal="true" custom-class="result-popup">
      <view class="result-modal">
        <text class="hgt-mono label">
          本局结束
        </text>
        <text class="hgt-display result-heading">
          汤底揭晓
        </text>
        <text class="result-bottom">
          {{ game.bottom }}
        </text>
        <view v-if="game.points?.length" class="result-points">
          <text class="hgt-mono label">
            关键推理点
          </text>
          <text v-for="point in game.points" :key="point.key" class="result-point">
            {{ point.content }}
          </text>
        </view>
        <view v-if="canControlResult" class="result-actions">
          <button class="hgt-mono outline" @click="goHome">
            返回首页
          </button>
          <button class="hgt-mono continue-button" :disabled="busy" @click="continuePlaying">
            继续游玩
          </button>
        </view>
      </view>
    </wd-popup>
    <HgtConfirmDialog
      v-model="confirmOpen"
      :eyebrow="confirmEyebrow"
      :title="confirmTitle"
      :description="confirmDescription"
      :tone="confirmTone"
      confirm-text="确认"
      @confirm="runConfirmAction"
      @cancel="cancelConfirmAction"
    />
  </view>
</template>

<style scoped>
.back-question{display:flex;width:max-content;height:30px;margin:0 0 18px;padding:0;border:0;border-radius:0;align-items:center;background:transparent;color:var(--muted-foreground);font-size:10px;line-height:1;letter-spacing:.12em}.back-question::after{border:0}
.game-page{position:relative;height:100vh;display:grid;overflow:hidden;grid-template-columns:330px 1fr}.puzzle-panel{border-right:1px solid var(--border);background:var(--card);padding:32px;display:flex;flex-direction:column}.puzzle-id,.label,.message-role,.typing{font-size:10px;color:var(--muted-foreground);letter-spacing:.15em}.puzzle-title{font-size:28px;margin:16px 0}.surface{font-size:14px;line-height:1.9;color:var(--accent);padding-bottom:28px;border-bottom:1px solid var(--border)}.team-block,.question-count{padding:25px 0;border-bottom:1px solid var(--border)}.section-row{display:flex;justify-content:space-between;align-items:center}.member{display:flex;align-items:center;gap:10px;margin-top:14px;font-size:12px}.member-info{display:flex;min-width:0;flex-direction:column;gap:3px}.avatar{width:32px;height:32px;border-radius:50%}.avatar-fallback{display:flex;align-items:center;justify-content:center;background:var(--secondary)}.member-role{margin-left:auto;font-size:9px;color:var(--muted-foreground)}.member-actions{display:flex;gap:4px}.member-actions button{display:flex;height:24px;margin:0;padding:0 7px;border:1px solid var(--border);border-radius:0;align-items:center;background:transparent;color:var(--muted-foreground);font-size:9px}.member-actions .kick{color:#ef4444}.count{font-size:20px}.progress{height:2px;background:var(--border);margin-top:12px}.progress>view{height:100%;background:var(--foreground)}.panel-actions{margin-top:auto;display:flex;flex-direction:column;gap:10px}.outline,.danger{margin:0;border-radius:0;background:transparent;border:1px solid var(--border);color:var(--muted-foreground);font-size:11px}.danger{color:#f87171}.conversation{position:relative;min-width:0;min-height:0;display:flex;overflow:hidden;flex-direction:column}.mobile-puzzle-summary{display:none;flex:none}.tabs{height:65px;border-bottom:1px solid var(--border);display:flex;flex:none}.tabs button{display:flex;margin:0;padding:0 28px;border-radius:0;flex:1;flex-direction:column;align-items:flex-start;justify-content:center;background:transparent;color:var(--muted-foreground);font-size:13px}.tabs button+button{border-left:1px solid var(--border)}.tabs button text{font-size:9px;display:block}.tabs button.active{color:var(--foreground);border-bottom:1px solid var(--foreground)}.tab-title{display:flex;align-items:center;gap:7px}.tab-title .unread-badge{display:inline-flex;min-width:17px;height:17px;padding:0 4px;border-radius:9px;align-items:center;justify-content:center;background:#ef4444;color:#fff;box-sizing:border-box;font-size:9px;line-height:17px}.message-author{display:flex;align-items:center;gap:8px}.message-avatar{width:24px;height:24px;border-radius:50%;font-size:10px}button::after{display:none}.solo-head{height:64px;border-bottom:1px solid var(--border);display:flex;flex:none;align-items:center;padding:0 28px;color:#4ade80}.messages{height:0;min-height:0;flex:1;padding:24px;box-sizing:border-box}.message{max-width:75%;padding:14px 16px;margin-bottom:14px;border:1px solid var(--border);display:flex;flex-direction:column;gap:7px;line-height:1.6;font-size:13px}.message.player{margin-left:auto;background:var(--secondary)}.message.host,.message.team{background:var(--card)}.composer{border-top:1px solid var(--border);padding:16px 22px;background:var(--card);flex:none}.error{color:#facc15;font-size:11px;margin-bottom:10px}.hints{display:flex;gap:8px;margin-bottom:10px}.hints button{margin:0;padding:0 12px;height:30px;line-height:28px;border-radius:0;border:1px solid var(--border);background:transparent;color:var(--muted-foreground);font-size:10px}.hints .bottom-mode{margin-left:auto}.hints .bottom-mode.active{border-color:var(--foreground);background:var(--foreground);color:var(--background)}.input-row{display:flex}.input-row input{height:46px;flex:1;border:1px solid var(--border);padding:0 15px}.input-row button{margin:0;width:100px;border-radius:0;background:var(--foreground);color:var(--background)}.invite-mask{position:fixed;z-index:50;inset:0;display:flex;padding:24px;align-items:center;justify-content:center;background:#000a}.invite-modal{box-sizing:border-box;width:min(520px,100%);padding:28px;border:1px solid var(--border);background:var(--card);box-shadow:0 24px 80px #0008}.invite-heading{display:block;margin:8px 0 22px;font-size:27px}.invite-link-row{display:flex;margin-bottom:24px}.invite-code{display:flex;min-height:44px;padding:0 14px;border:1px solid var(--border);flex:1;align-items:center;color:var(--foreground)}.copy-button,.close-invite{display:flex;height:44px;margin:0;padding:0 18px;border:1px solid var(--foreground);border-radius:0;align-items:center;justify-content:center;background:var(--foreground);color:var(--background);font-size:11px}.invite-members-title{display:block;margin-bottom:10px;color:var(--muted-foreground)}.invite-member{display:flex;padding:11px 0;border-bottom:1px solid var(--border);align-items:center;gap:10px;font-size:12px}.close-invite{width:100%;margin-top:22px;background:transparent;color:var(--foreground)}
.result-mask{position:fixed;z-index:60;inset:0;display:flex;padding:24px;align-items:center;justify-content:center;background:#000b}.result-modal{box-sizing:border-box;width:min(620px,100%);max-height:85vh;padding:34px;border:1px solid var(--border);overflow-y:auto;background:var(--card);box-shadow:0 24px 80px #0008}.result-heading{display:block;margin:10px 0 24px;font-size:32px}.result-bottom{display:block;padding:20px;border-left:2px solid var(--foreground);background:var(--secondary);font-size:15px;line-height:1.9}.result-points{display:flex;margin-top:24px;gap:10px;flex-direction:column}.result-point{padding:10px 0;border-bottom:1px solid var(--border);font-size:12px;line-height:1.6}.result-actions{display:flex;margin-top:28px;gap:12px}.result-actions button{display:flex;height:44px;margin:0;padding:0;flex:1;align-items:center;justify-content:center;border-radius:0}.continue-button{border:1px solid var(--foreground);background:var(--foreground);color:var(--background);font-size:11px}
:deep(.invite-popup){box-sizing:border-box;width:min(520px,calc(100vw - 48px));border:1px solid var(--border);border-radius:0;background:var(--card);color:var(--foreground)}:deep(.result-popup){box-sizing:border-box;width:min(620px,calc(100vw - 48px));border:1px solid var(--border);border-radius:0;background:var(--card);color:var(--foreground)}:deep(.invite-popup) .invite-modal,:deep(.result-popup) .result-modal{width:100%;border:0;box-shadow:none}
.puzzle-metadata{display:flex;padding:18px 0;border-bottom:1px solid var(--border);gap:14px;flex-direction:column}.metadata-group{display:flex;gap:8px;flex-direction:column}.metadata-label{color:var(--muted-foreground);font-size:9px;letter-spacing:.14em}.metadata-items{display:flex;flex-wrap:wrap;gap:6px}.metadata-chip{flex:none;padding:3px 7px;border:1px solid var(--border);color:var(--muted-foreground);font-size:9px;line-height:1.35;white-space:nowrap}.risk-chip{border-color:color-mix(in srgb,#d97706 55%,var(--border));color:#d97706}
.room-privacy-row{display:flex;padding:16px 0 2px;align-items:center;justify-content:space-between;gap:14px}.room-privacy-copy{display:flex;min-width:0;gap:4px;flex-direction:column}.room-privacy-copy>text:first-child{font-size:10px;color:var(--foreground);letter-spacing:.12em}.room-privacy-copy>text:last-child{font-size:9px;color:var(--muted-foreground);line-height:1.5}
@media(max-width:767px){.game-page{box-sizing:border-box;height:calc(100vh - 120px - env(safe-area-inset-bottom));height:calc(100dvh - 120px - env(safe-area-inset-bottom));grid-template-columns:1fr}.puzzle-panel{display:none}.mobile-puzzle-summary{display:block;max-height:55vh;overflow-y:auto;border-bottom:1px solid var(--border);background:var(--card)}.mobile-puzzle-row{display:flex;height:58px;padding:0 14px;align-items:center;gap:12px}.mobile-help,.mobile-expand{display:flex;width:30px;height:30px;margin:0;padding:0;border:1px solid var(--border);border-radius:0;align-items:center;justify-content:center;background:transparent;color:var(--foreground);line-height:1}.mobile-puzzle-title{overflow:hidden;flex:1;font-size:20px;text-overflow:ellipsis;white-space:nowrap}.mobile-question-count{font-size:11px;color:var(--muted-foreground)}.mobile-surface-wrap{position:relative;padding:12px 52px 14px 16px;border-top:1px solid var(--border)}.mobile-surface{display:-webkit-box;padding:0;border:0;overflow:hidden;-webkit-box-orient:vertical;-webkit-line-clamp:3}.mobile-surface.expanded{display:block;overflow:visible}.mobile-surface-wrap .mobile-expand{position:absolute;right:14px;bottom:14px}.mobile-team{padding:0 16px;border-top:1px solid var(--border)}.mobile-team-toggle{display:flex;width:100%;height:46px;margin:0;padding:0;border:0;border-radius:0;align-items:center;gap:12px;background:transparent;color:var(--foreground)}.mobile-team-toggle .section-row{flex:1}.mobile-team-details{padding:0 0 14px}.mobile-team-details>.outline,.mobile-team-details>.danger{display:flex;width:100%;min-height:38px;margin-top:10px;align-items:center;justify-content:center}.mobile-actions{display:flex;padding:12px 16px;border-top:1px solid var(--border);gap:8px}.mobile-actions button{display:flex;min-height:38px;margin:0;padding:0 10px;flex:1;align-items:center;justify-content:center}.tabs{height:58px}.messages{padding:16px}.message{max-width:88%}.composer{padding:10px 12px 12px}.composer .input-row{height:48px;border:1px solid var(--border);background:var(--background)}.composer .input-row input{box-sizing:border-box;height:100%;min-width:0;padding:0 14px;border:0;background:transparent;color:var(--foreground);font-size:13px}.composer .input-row button{display:flex;width:84px;height:100%;min-height:0;padding:0;border:0;border-left:1px solid var(--border);align-items:center;justify-content:center;background:var(--foreground);color:var(--background);font-size:12px;line-height:1;letter-spacing:.08em}}
@media(max-width:767px){.mobile-team-metadata{padding:12px 0}.mobile-team-metadata .metadata-group{gap:6px}}
@media(max-width:767px){.mobile-room-privacy{padding:12px 0;border-bottom:1px solid var(--border)}}
@media(max-width:767px){.mobile-team-details .member{flex-wrap:wrap}.mobile-member-actions{width:100%;margin-left:42px}.mobile-member-actions button{height:30px;padding:0 12px;flex:1;justify-content:center}}
</style>
