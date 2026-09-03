<script setup lang="ts">
/* eslint-disable style/max-statements-per-line */
import { gameApi, roomApi, TurtleApiError } from '@/api/turtle'
import { useGameSocket } from '@/composables/useGameSocket'
import { resolveShareUrl } from '@/config/endpoints'
import { useGameStore } from '@/store/gameStore'
import { usePlayerStore } from '@/store/playerStore'

definePage({ name: 'game', layout: 'tabbar', style: { 'navigationStyle': 'custom', 'mp-toutiao': { navigationStyle: 'default' } } })
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
const mobileSurfaceOpen = ref(false)
const mobileTeamOpen = ref(false)
const mobileActionOpen = ref(false)
const mobileActionPosition = reactive({ x: 0, y: 0 })
const mobileActionStyle = computed<Record<string, string>>(() => ({ left: `${mobileActionPosition.x}px`, top: `${mobileActionPosition.y}px` }))
const mobileActionPositionKey = 'turtle_mobile_game_action_position_v2'
let mobileActionDragStart = { x: 0, y: 0, left: 0, top: 0 }
let mobileActionDragged = false
const mobileSurfaceRef = ref<HTMLElement | { $el?: HTMLElement } | null>(null)
const mobileSurfaceOverflow = ref(false)
const errorMessage = ref('')
const pageError = ref('')
const unreadTeam = ref(0)
const judgeScrollTarget = ref('')
const teamScrollTarget = ref('')
const mobileChatHeight = ref(68)
const mobileChatStyle = computed<Record<string, string>>(() => ({ '--mobile-chat-height': `${mobileChatHeight.value}%` }))
let mobileResizeStartY = 0
let mobileResizeStartHeight = 68
const game = computed(() => store.current)
const room = socket.roomSnapshot
const sortedRoomMembers = computed(() => [...(room.value?.members || [])].sort((left, right) => {
  if (left.role === right.role)
    return 0
  return left.role === 'owner' ? -1 : 1
}))
const canControlResult = computed(() => game.value?.mode !== 'multiplayer' || room.value?.is_owner === true)
const typingMembers = socket.typingMembers
const routeGameId = computed(() => String(route.query.id || route.params.id || ''))
const gameId = ref(routeGameId.value)
let switchingGameId = ''
const riskTypeLabels: Record<string, string> = { death: '死亡', violence: '暴力', gore: '血腥', self_harm: '自伤', sexual: '性内容', child_safety: '未成年人', discrimination: '歧视', illegal: '违法', substance: '成瘾物', other: '其他' }
const riskTypeLabel = (value: string) => riskTypeLabels[value] || value
const judgeMessageId = (sequence: number) => `judge-message-${sequence}`
const teamMessageId = (sequence: number) => `team-message-${sequence}`
function roomSharePath() {
  if (!room.value)
    return '/pages/index/index'
  const questionId = room.value.question_id || game.value?.question_id || ''
  const query = [`invite_code=${encodeURIComponent(room.value.invite_code)}`]
  if (questionId)
    query.push(`question_id=${encodeURIComponent(questionId)}`)
  return `/pages/rooms/index?${query.join('&')}`
}
const roomShareTitle = computed(() => room.value ? `加入「${room.value.name}」一起玩海龟汤` : '墨鱼海龟汤')
// #ifdef MP-WEIXIN || MP-TOUTIAO
onShareAppMessage(() => {
  inviteOpen.value = false
  return {
    title: roomShareTitle.value,
    path: roomSharePath(),
  }
})
// #endif
async function scrollMessagesTo(target: typeof judgeScrollTarget, id: string) {
  target.value = ''
  await nextTick()
  target.value = id
}
interface MobileResizeEvent {
  touches?: ArrayLike<{ clientY?: number, pageY?: number }>
}
function mobileTouchY(event: MobileResizeEvent) {
  const touch = event.touches?.[0]
  return touch?.clientY ?? touch?.pageY ?? 0
}
function clampMobileChatHeight(value: number) {
  return Math.min(92, Math.max(38, value))
}
function startMobileResize(event: MobileResizeEvent) {
  mobileResizeStartY = mobileTouchY(event)
  mobileResizeStartHeight = mobileChatHeight.value
}
function resizeMobileChat(event: MobileResizeEvent) {
  const currentY = mobileTouchY(event)
  const windowHeight = mobileWindowInfo().windowHeight
  const delta = (mobileResizeStartY - currentY) / Math.max(windowHeight - 120, 1) * 100
  mobileChatHeight.value = clampMobileChatHeight(mobileResizeStartHeight + delta)
}
function resizeMobileChatBy(step: number) {
  mobileChatHeight.value = clampMobileChatHeight(mobileChatHeight.value + step)
}
function toggleMobileChatHeight() {
  mobileChatHeight.value = mobileChatHeight.value >= 85 ? 68 : 92
}
watch(socket.gameSnapshot, (value) => {
  if (!value) {
    return
  }
  const expectedGameId = switchingGameId || gameId.value
  if (value.id !== expectedGameId)
    return
  store.setGame(value)
  if (['solved', 'finished', 'abandoned'].includes(value.status)) {
    resultOpen.value = true
  }
})
async function switchToGame(nextGameId: string) {
  if (!nextGameId || nextGameId === game.value?.id || nextGameId === switchingGameId)
    return
  switchingGameId = nextGameId
  resultOpen.value = false
  errorMessage.value = ''
  question.value = ''
  inputMode.value = 'question'
  try {
    store.setGame(await socket.join(nextGameId))
    gameId.value = nextGameId
    // #ifdef H5
    const url = new URL(window.location.href)
    url.searchParams.set('id', nextGameId)
    url.searchParams.delete('question_id')
    url.searchParams.delete('show_result')
    window.history.replaceState(window.history.state, '', `${url.pathname}${url.search}${url.hash}`)
    // #endif
    // #ifndef H5
    await router.replace({ path: route.path, query: { id: nextGameId } })
    // #endif
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
    void switchToGame(next.game_id)
})
watch(() => socket.gameNextStarted.value?.nonce, () => {
  const next = socket.gameNextStarted.value
  if (next && (!next.room_id || next.room_id === room.value?.id))
    void switchToGame(next.game_id)
})
watch(() => room.value?.game_id, (nextGameId) => {
  if (nextGameId)
    void switchToGame(nextGameId)
})
watch(() => room.value?.messages.length || 0, (count, previous) => {
  if (count > previous && tab.value !== 'team') {
    unreadTeam.value += count - previous
  }
})
watch(() => game.value?.messages[game.value.messages.length - 1]?.sequence, (sequence) => {
  if (sequence !== undefined)
    void scrollMessagesTo(judgeScrollTarget, judgeMessageId(sequence))
}, { flush: 'post', immediate: true })
watch(() => room.value?.messages[room.value.messages.length - 1]?.sequence, (sequence) => {
  if (sequence !== undefined)
    void scrollMessagesTo(teamScrollTarget, teamMessageId(sequence))
}, { flush: 'post', immediate: true })
watch(tab, (value) => {
  if (value === 'team') {
    unreadTeam.value = 0
    const sequence = room.value?.messages[room.value.messages.length - 1]?.sequence
    if (sequence !== undefined)
      void scrollMessagesTo(teamScrollTarget, teamMessageId(sequence))
  }
  else {
    const sequence = game.value?.messages[game.value.messages.length - 1]?.sequence
    if (sequence !== undefined)
      void scrollMessagesTo(judgeScrollTarget, judgeMessageId(sequence))
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
  pageError.value = ''
  store.clear()
  socket.clearRoom()
  if (!gameId.value) {
    pageError.value = '游戏链接无效，请重新选择题目'
    return
  }
  try {
    try { store.setGame(await socket.join(gameId.value)) }
    catch { store.setGame(await gameApi.read(gameId.value)) }
    if (game.value?.mode === 'multiplayer' && game.value.room_id)
      await socket.roomJoin(game.value.room_id)
    if (route.query.show_result === '1' && game.value && ['solved', 'finished', 'abandoned'].includes(game.value.status))
      resultOpen.value = true
  }
  catch (error) {
    store.clear()
    socket.clearRoom()
    pageError.value = (error as Error).message || '游戏加载失败'
  }
}
watch(routeGameId, (nextGameId) => {
  if (!nextGameId || nextGameId === gameId.value)
    return
  gameId.value = nextGameId
  void refresh()
})
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
  try {
    await socket.roomChat(game.value.room_id, teamMessage.value)
    teamMessage.value = ''
    await socket.typing(game.value.room_id, false)
  }
  catch (error) {
    const code = (error as Error).message
    if (['room.not_member', 'room.member_required', 'auth.login_required'].includes(code)) {
      socket.clearRoom()
      tab.value = 'judge'
    }
    uni.showToast({ title: code, icon: 'none' })
  }
}
let typingTimer: ReturnType<typeof setTimeout> | undefined
function teamTyping() {
  if (!game.value?.room_id) {
    return
  }
  void socket.typing(game.value.room_id, true).catch(() => {})
  if (typingTimer) {
    clearTimeout(typingTimer)
  }
  typingTimer = setTimeout(() => { void socket.typing(game.value!.room_id!, false).catch(() => {}) }, 1200)
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
  // #ifdef MP-WEIXIN || MP-TOUTIAO
  uni.showModal({
    title: options.title,
    content: options.description,
    confirmText: '确认',
    cancelText: '取消',
    success: (result) => {
      if (!result.confirm)
        return
      void Promise.resolve(options.action()).catch((error) => {
        uni.showToast({ title: (error as Error).message || '操作失败，请稍后重试', icon: 'none' })
      })
    },
  })
  // #endif
  // #ifdef H5
  confirmTitle.value = options.title
  confirmDescription.value = options.description
  confirmEyebrow.value = options.eyebrow || '请确认'
  confirmTone.value = options.tone || 'default'
  confirmAction = options.action
  confirmOpen.value = true
  // #endif
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
function requestLeaveRoom() {
  if (!room.value)
    return
  if (!room.value.is_owner) {
    void leaveRoom()
    return
  }
  openConfirm({
    eyebrow: '房间管理',
    title: '退出房间',
    description: room.value.member_count > 1 ? '退出后将自动移交房主给其他队员。' : '退出后房间将自动关闭。',
    tone: 'danger',
    action: leaveRoom,
  })
}
function mobileInvite() {
  mobileActionOpen.value = false
  void invite()
}
function mobileLeaveRoom() {
  mobileActionOpen.value = false
  requestLeaveRoom()
}
function mobileAbandon() {
  mobileActionOpen.value = false
  abandon()
}
interface MobileActionTouchEvent {
  touches?: ArrayLike<{ clientX?: number, clientY?: number, pageX?: number, pageY?: number }>
  changedTouches?: ArrayLike<{ clientX?: number, clientY?: number, pageX?: number, pageY?: number }>
}
function mobileActionPoint(event: MobileActionTouchEvent) {
  const touch = event.touches?.[0] || event.changedTouches?.[0]
  return { x: touch?.clientX ?? touch?.pageX ?? 0, y: touch?.clientY ?? touch?.pageY ?? 0 }
}
function mobileWindowInfo() {
  const modern = typeof uni.getWindowInfo === 'function' ? uni.getWindowInfo() : null
  if (modern)
    return { windowWidth: modern.windowWidth, windowHeight: modern.windowHeight }
  const legacy = uni.getSystemInfoSync()
  return { windowWidth: legacy.windowWidth || 375, windowHeight: legacy.windowHeight || 667 }
}
function clampMobileActionPosition(x: number, y: number) {
  const { windowWidth, windowHeight } = mobileWindowInfo()
  return {
    x: Math.min(Math.max(8, x), Math.max(8, windowWidth - 50)),
    y: Math.min(Math.max(8, y), Math.max(8, windowHeight - 50)),
  }
}
function restoreMobileActionPosition() {
  const stored = uni.getStorageSync(mobileActionPositionKey) as unknown
  const saved = stored && typeof stored === 'object' ? stored as { x?: number, y?: number } : {}
  const { windowWidth, windowHeight } = mobileWindowInfo()
  const position = clampMobileActionPosition(Number(saved?.x ?? windowWidth - 58), Number(saved?.y ?? windowHeight - 190))
  Object.assign(mobileActionPosition, position)
}
function startMobileActionDrag(event: MobileActionTouchEvent) {
  const point = mobileActionPoint(event)
  mobileActionDragged = false
  mobileActionDragStart = { x: point.x, y: point.y, left: mobileActionPosition.x, top: mobileActionPosition.y }
}
function dragMobileAction(event: MobileActionTouchEvent) {
  const point = mobileActionPoint(event)
  const deltaX = point.x - mobileActionDragStart.x
  const deltaY = point.y - mobileActionDragStart.y
  if (Math.abs(deltaX) + Math.abs(deltaY) > 5)
    mobileActionDragged = true
  if (!mobileActionDragged)
    return
  mobileActionActionClose()
  Object.assign(mobileActionPosition, clampMobileActionPosition(mobileActionDragStart.left + deltaX, mobileActionDragStart.top + deltaY))
}
function finishMobileActionDrag() {
  if (!mobileActionDragged)
    return
  uni.setStorageSync(mobileActionPositionKey, { ...mobileActionPosition })
}
function mobileActionActionClose() {
  mobileActionOpen.value = false
}
function toggleMobileActionMenu() {
  if (mobileActionDragged) {
    mobileActionDragged = false
    return
  }
  mobileActionOpen.value = !mobileActionOpen.value
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
      if (error instanceof TurtleApiError && ['auth.login_required', 'auth.token_invalid'].includes(error.code)) {
        player.clear()
        uni.showToast({ title: '登录状态已失效，请重新登录', icon: 'none' })
        router.push({ name: 'player-login' })
        return
      }
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
  const link = resolveShareUrl(roomSharePath())
  uni.setClipboardData({ data: link, success: () => uni.showToast({ title: '邀请链接已复制', icon: 'success' }) })
}
function backToQuestion() {
  if (game.value?.question_id)
    uni.navigateTo({ url: `/pages/question-detail/index?id=${encodeURIComponent(game.value.question_id)}` })
  else router.push({ name: 'questions' })
}
function returnToQuestionLibrary() {
  router.replace({ name: 'questions' })
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
    if (game.value?.mode === 'multiplayer' && game.value.room_id) {
      const nextRoom = await roomApi.next(game.value.room_id)
      socket.adoptRoom(nextRoom)
      if (!nextRoom.game_id)
        throw new Error('房间尚未关联新游戏，请稍后重试')
      await socket.roomNextSync(nextRoom.id)
      await switchToGame(nextRoom.game_id)
    }
    else {
      await socket.next(game.value!.id)
    }
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
})
onMounted(async () => {
  restoreMobileActionPosition()
  await player.restore()
  await refresh()
  mobileSurfaceOpen.value = false
  await nextTick()
  measureMobileSurface()
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
  <template v-if="game">
    <view class="game-page">
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
            <view class="member-avatar-wrap">
              <image v-if="member.avatar_url" :src="member.avatar_url" class="avatar" /><view v-else class="avatar avatar-fallback">
                {{ member.username.slice(0, 1) }}
              </view>
              <!-- #ifdef H5 -->
              <text v-if="member.is_muted" class="member-muted-badge" aria-label="已禁言" title="已禁言">
                <wd-icon name="mute" size="9px" />
              </text>
            <!-- #endif -->
            </view><view class="member-info">
              <text>{{ member.username }}</text><text v-if="isTyping(member.user_id)" class="typing hgt-mono">
                正在输入中…
              </text>
            </view><text v-if="member.role === 'owner'" class="member-role hgt-mono">
              房主
            </text>
            <view v-if="room.is_owner && !member.is_self" class="member-actions">
              <!-- #ifdef H5 -->
              <button @click="toggleMute(member)">
                {{ member.is_muted ? '解除禁言' : '禁言' }}
              </button>
              <!-- #endif -->
              <button class="kick" @click="kickMember(member)">
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
          <button v-if="game.mode === 'multiplayer' && room" class="danger hgt-mono" @click="requestLeaveRoom">
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
                <view class="member-avatar-wrap">
                  <image v-if="member.avatar_url" :src="member.avatar_url" class="avatar" /><view v-else class="avatar avatar-fallback">
                    {{ member.username.slice(0, 1) }}
                  </view>
                  <!-- #ifdef H5 -->
                  <text v-if="member.is_muted" class="member-muted-badge" aria-label="已禁言" title="已禁言">
                    <wd-icon name="mute" size="9px" />
                  </text>
                <!-- #endif -->
                </view>
                <view class="member-info">
                  <text>{{ member.username }}</text><text v-if="isTyping(member.user_id)" class="typing hgt-mono">
                    正在输入中…
                  </text>
                </view>
                <text v-if="member.role === 'owner'" class="member-role hgt-mono">
                  房主
                </text>
                <view v-if="room?.is_owner && !member.is_self" class="member-actions mobile-member-actions">
                  <!-- #ifdef H5 -->
                  <button class="mobile-icon-button" :aria-label="member.is_muted ? '解除禁言' : '禁言'" :title="member.is_muted ? '解除禁言' : '禁言'" @click="toggleMute(member)">
                    <wd-icon :name="member.is_muted ? 'sound' : 'mute'" size="16px" />
                  </button>
                  <!-- #endif -->
                  <button class="mobile-icon-button kick" :aria-label="`踢出 ${member.username}`" :title="`踢出 ${member.username}`" @click="kickMember(member)">
                    <wd-icon name="delete" size="16px" />
                  </button>
                </view>
              </view>
            </view>
          </view>
        </view>
        <!-- #ifdef H5 -->
        <view class="mobile-action-fab" :class="{ open: mobileActionOpen }" :style="mobileActionStyle" @touchstart="startMobileActionDrag" @touchmove.stop.prevent="dragMobileAction" @touchend="finishMobileActionDrag">
          <view v-if="mobileActionOpen" class="mobile-action-menu">
            <button v-if="!room || room.member_count < room.max_players" class="mobile-fab-option" :disabled="creatingRoom" aria-label="邀请队友" title="邀请队友" @click="mobileInvite">
              {{ creatingRoom ? '创建中' : '分享' }}
            </button>
            <button v-if="game.mode === 'multiplayer' && room" class="mobile-fab-option leave" aria-label="退出房间" title="退出房间" @click="mobileLeaveRoom">
              退出
            </button>
            <button v-if="game.mode === 'single' || room?.is_owner" class="mobile-fab-option danger" aria-label="放弃游戏" title="放弃游戏" @click="mobileAbandon">
              放弃
            </button>
          </view>
          <button class="mobile-fab-trigger" :aria-label="mobileActionOpen ? '收起游戏操作' : '展开游戏操作'" :aria-expanded="mobileActionOpen" @click="toggleMobileActionMenu">
            <wd-icon :name="mobileActionOpen ? 'close' : 'more'" size="21px" />
          </button>
        </view>
        <!-- #endif -->
        <!-- #ifndef H5 -->
        <cover-view class="mobile-action-fab mini-cover-fab" :class="{ open: mobileActionOpen }" :style="mobileActionStyle" @touchstart="startMobileActionDrag" @touchmove.stop.prevent="dragMobileAction" @touchend="finishMobileActionDrag">
          <cover-view v-if="mobileActionOpen" class="mobile-action-menu">
            <cover-view v-if="!room || room.member_count < room.max_players" class="mobile-fab-option" @click="mobileInvite">
              分享
            </cover-view>
            <cover-view v-if="game.mode === 'multiplayer' && room" class="mobile-fab-option leave" @click="mobileLeaveRoom">
              退出
            </cover-view>
            <cover-view v-if="game.mode === 'single' || room?.is_owner" class="mobile-fab-option danger" @click="mobileAbandon">
              放弃
            </cover-view>
          </cover-view>
          <cover-view class="mobile-fab-trigger" @click="toggleMobileActionMenu">
            {{ mobileActionOpen ? '×' : '•••' }}
          </cover-view>
        </cover-view>
        <!-- #endif -->
        <view class="chat-panel" :style="mobileChatStyle">
          <view v-if="game.mode === 'multiplayer'" class="mobile-chat-dragbar chat-resize-handle" role="slider" aria-label="调整对话区域高度" aria-valuemin="38" aria-valuemax="92" :aria-valuenow="Math.round(mobileChatHeight)" tabindex="0" @touchstart="startMobileResize" @touchmove.stop.prevent="resizeMobileChat" @dblclick="toggleMobileChatHeight" @keydown.up.prevent="resizeMobileChatBy(5)" @keydown.down.prevent="resizeMobileChatBy(-5)">
            <view class="chat-grip" />
            <text class="hgt-mono">
              上下拖动
            </text>
          </view>
          <!-- #ifdef H5 -->
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
          <view v-else class="solo-head chat-resize-handle" role="slider" aria-label="调整对话区域高度" aria-valuemin="38" aria-valuemax="92" :aria-valuenow="Math.round(mobileChatHeight)" tabindex="0" @touchstart="startMobileResize" @touchmove.stop.prevent="resizeMobileChat" @dblclick="toggleMobileChatHeight" @keydown.up.prevent="resizeMobileChatBy(5)" @keydown.down.prevent="resizeMobileChatBy(-5)">
            <view class="chat-grip" /><text class="hgt-mono">
              ◈ AI 裁判在线
            </text>
          </view>
          <!-- #endif -->
          <!-- #ifndef H5 -->
          <view class="solo-head chat-resize-handle" role="slider" aria-label="调整对话区域高度" aria-valuemin="38" aria-valuemax="92" :aria-valuenow="Math.round(mobileChatHeight)" tabindex="0" @touchstart="startMobileResize" @touchmove.stop.prevent="resizeMobileChat">
            <view class="chat-grip" /><text class="hgt-mono">
              ◈ AI 裁判在线
            </text>
          </view>
          <!-- #endif -->
          <template v-if="tab === 'judge' || game.mode === 'single'">
            <scroll-view scroll-y :scroll-into-view="judgeScrollTarget" scroll-with-animation class="messages">
              <view v-for="message in game.messages" :id="judgeMessageId(message.sequence)" :key="message.sequence" class="message" :class="message.role">
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
          <!-- #ifdef H5 -->
          <template v-else>
            <scroll-view scroll-y :scroll-into-view="teamScrollTarget" scroll-with-animation class="messages">
              <view v-for="message in room?.messages || []" :id="teamMessageId(message.sequence)" :key="message.sequence" class="message team">
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
        <!-- #endif -->
        </view>
      </main>
    </view>
    <wd-popup v-if="room && inviteOpen" v-model="inviteOpen" position="center" :root-portal="true" custom-class="invite-popup">
      <view class="invite-modal">
        <text class="hgt-mono label">
          邀请队友
        </text><text class="hgt-display invite-heading">
          分享房间链接
        </text>
        <view class="invite-link-row">
          <text class="hgt-mono invite-code">
            邀请码 {{ room.invite_code }}
          </text>
          <!-- #ifdef H5 -->
          <button class="copy-button hgt-mono" @click="copyInviteLink">
            复制链接
          </button>
          <!-- #endif -->
          <!-- #ifdef MP-WEIXIN || MP-TOUTIAO -->
          <button class="copy-button hgt-mono" open-type="share">
            分享给好友
          </button>
          <!-- #endif -->
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
    <wd-popup v-if="resultOpen" v-model="resultOpen" position="center" :close-on-click-modal="true" :root-portal="true" custom-class="result-popup">
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
      v-if="confirmOpen"
      v-model="confirmOpen"
      :eyebrow="confirmEyebrow"
      :title="confirmTitle"
      :description="confirmDescription"
      :tone="confirmTone"
      confirm-text="确认"
      @confirm="runConfirmAction"
      @cancel="cancelConfirmAction"
    />
  </template>
  <view v-else class="game-load-state">
    <text class="hgt-mono game-load-eyebrow">
      GAME UNAVAILABLE
    </text>
    <text class="hgt-display game-load-title">
      无法进入游戏
    </text>
    <text class="game-load-copy">
      {{ pageError || '正在读取游戏…' }}
    </text>
    <button v-if="pageError" class="hgt-mono game-load-action" @click="returnToQuestionLibrary">
      返回题库
    </button>
  </view>
</template>

<style scoped>
.back-question{display:flex;width:max-content;height:30px;margin:0 0 18px;padding:0;border:0;border-radius:0;align-items:center;background:transparent;color:var(--muted-foreground);font-size:10px;line-height:1;letter-spacing:.12em}.back-question::after{border:0}
.game-load-state{box-sizing:border-box;display:flex;min-height:100vh;padding:48px 24px;align-items:center;justify-content:center;flex-direction:column;text-align:center}.game-load-eyebrow{color:var(--muted-foreground);font-size:10px;letter-spacing:.2em}.game-load-title{margin-top:18px;font-size:32px}.game-load-copy{max-width:420px;margin-top:14px;color:var(--muted-foreground);font-size:13px;line-height:1.8}.game-load-action{display:flex;width:180px;height:44px;margin:28px 0 0;padding:0;border:1px solid var(--foreground);border-radius:0;align-items:center;justify-content:center;background:var(--foreground);color:var(--background);font-size:11px;letter-spacing:.14em}.game-load-action::after{border:0}
.game-page{position:relative;height:100vh;display:grid;overflow:hidden;grid-template-columns:330px 1fr}.puzzle-panel{border-right:1px solid var(--border);background:var(--card);padding:32px;display:flex;flex-direction:column}.puzzle-id,.label,.message-role,.typing{font-size:10px;color:var(--muted-foreground);letter-spacing:.15em}.puzzle-title{font-size:28px;margin:16px 0}.surface{font-size:14px;line-height:1.9;color:var(--accent);padding-bottom:28px;border-bottom:1px solid var(--border)}.team-block,.question-count{padding:25px 0;border-bottom:1px solid var(--border)}.section-row{display:flex;justify-content:space-between;align-items:center}.member{display:flex;align-items:center;gap:10px;margin-top:14px;font-size:12px}.member-info{display:flex;min-width:0;flex-direction:column;gap:3px}.member-avatar-wrap{position:relative;display:flex;width:32px;height:32px;flex:none}.avatar{width:32px;height:32px;border-radius:50%}.avatar-fallback{display:flex;align-items:center;justify-content:center;background:var(--secondary)}.member-muted-badge{position:absolute;right:-3px;bottom:-3px;display:flex;width:15px;height:15px;border:2px solid var(--card);border-radius:50%;align-items:center;justify-content:center;background:#dc2626;color:#fff;box-sizing:border-box}.member-role{margin-left:auto;font-size:9px;color:var(--muted-foreground)}.member-actions{display:flex;gap:4px}.member-actions button{display:flex;height:24px;margin:0;padding:0 7px;border:1px solid var(--border);border-radius:0;align-items:center;background:transparent;color:var(--muted-foreground);font-size:9px}.member-actions .kick{color:#ef4444}.count{font-size:20px}.progress{height:2px;background:var(--border);margin-top:12px}.progress>view{height:100%;background:var(--foreground)}.panel-actions{margin-top:auto;display:flex;flex-direction:column;gap:10px}.outline,.danger{margin:0;border-radius:0;background:transparent;border:1px solid var(--border);color:var(--muted-foreground);font-size:11px}.danger{color:#f87171}.conversation{position:relative;min-width:0;min-height:0;display:flex;overflow:hidden;flex-direction:column}.mobile-puzzle-summary{display:none;flex:none}.chat-panel{display:flex;min-height:0;flex:1;flex-direction:column}.chat-grip{display:none}.tabs{height:65px;border-bottom:1px solid var(--border);display:flex;flex:none}.tabs button{display:flex;margin:0;padding:0 28px;border-radius:0;flex:1;flex-direction:column;align-items:flex-start;justify-content:center;background:transparent;color:var(--muted-foreground);font-size:13px}.tabs button+button{border-left:1px solid var(--border)}.tabs button text{font-size:9px;display:block}.tabs button.active{color:var(--foreground);border-bottom:1px solid var(--foreground)}.tab-title{display:flex;align-items:center;gap:7px}.tab-title .unread-badge{display:inline-flex;min-width:17px;height:17px;padding:0 4px;border-radius:9px;align-items:center;justify-content:center;background:#ef4444;color:#fff;box-sizing:border-box;font-size:9px;line-height:17px}.message-author{display:flex;align-items:center;gap:8px}.message-avatar{width:24px;height:24px;border-radius:50%;font-size:10px}button::after{display:none}.solo-head{height:64px;border-bottom:1px solid var(--border);display:flex;flex:none;align-items:center;padding:0 28px;color:#4ade80}.messages{height:0;min-height:0;flex:1;padding:24px;box-sizing:border-box}.message{max-width:75%;padding:14px 16px;margin-bottom:14px;border:1px solid var(--border);display:flex;flex-direction:column;gap:7px;line-height:1.6;font-size:13px}.message.player{margin-left:auto;background:var(--secondary)}.message.host,.message.team{background:var(--card)}.composer{border-top:1px solid var(--border);padding:16px 22px;background:var(--card);flex:none}.error{color:#facc15;font-size:11px;margin-bottom:10px}.hints{display:flex;gap:8px;margin-bottom:10px}.hints button{margin:0;padding:0 12px;height:30px;line-height:28px;border-radius:0;border:1px solid var(--border);background:transparent;color:var(--muted-foreground);font-size:10px}.hints .bottom-mode{margin-left:auto}.hints .bottom-mode.active{border-color:var(--foreground);background:var(--foreground);color:var(--background)}.input-row{display:flex}.input-row input{height:46px;flex:1;border:1px solid var(--border);padding:0 15px}.input-row button{margin:0;width:100px;border-radius:0;background:var(--foreground);color:var(--background)}.invite-mask{position:fixed;z-index:50;inset:0;display:flex;padding:24px;align-items:center;justify-content:center;background:#000a}.invite-modal{box-sizing:border-box;width:min(520px,100%);padding:28px;border:1px solid var(--border);background:var(--card);box-shadow:0 24px 80px #0008}.invite-heading{display:block;margin:8px 0 22px;font-size:27px}.invite-link-row{display:flex;margin-bottom:24px}.invite-code{display:flex;min-height:44px;padding:0 14px;border:1px solid var(--border);flex:1;align-items:center;color:var(--foreground)}.copy-button,.close-invite{display:flex;height:44px;margin:0;padding:0 18px;border:1px solid var(--foreground);border-radius:0;align-items:center;justify-content:center;background:var(--foreground);color:var(--background);font-size:11px}.invite-members-title{display:block;margin-bottom:10px;color:var(--muted-foreground)}.invite-member{display:flex;padding:11px 0;border-bottom:1px solid var(--border);align-items:center;gap:10px;font-size:12px}.close-invite{width:100%;margin-top:22px;background:transparent;color:var(--foreground)}
.result-mask{position:fixed;z-index:60;inset:0;display:flex;padding:24px;align-items:center;justify-content:center;background:#000b}.result-modal{box-sizing:border-box;width:min(620px,100%);max-height:85vh;padding:34px;border:1px solid var(--border);overflow-y:auto;background:var(--card);box-shadow:0 24px 80px #0008}.result-heading{display:block;margin:10px 0 24px;font-size:32px}.result-bottom{display:block;padding:20px;border-left:2px solid var(--foreground);background:var(--secondary);font-size:15px;line-height:1.9}.result-points{display:flex;margin-top:24px;gap:10px;flex-direction:column}.result-point{padding:10px 0;border-bottom:1px solid var(--border);font-size:12px;line-height:1.6}.result-actions{display:flex;margin-top:28px;gap:12px}.result-actions button{display:flex;height:44px;margin:0;padding:0;flex:1;align-items:center;justify-content:center;border-radius:0}.continue-button{border:1px solid var(--foreground);background:var(--foreground);color:var(--background);font-size:11px}
:deep(.invite-popup){box-sizing:border-box;width:min(520px,calc(100vw - 48px));border:1px solid var(--border);border-radius:0;background:var(--card);color:var(--foreground)}:deep(.result-popup){box-sizing:border-box;width:min(620px,calc(100vw - 48px));border:1px solid var(--border);border-radius:0;background:var(--card);color:var(--foreground)}:deep(.invite-popup) .invite-modal,:deep(.result-popup) .result-modal{width:100%;border:0;box-shadow:none}
.puzzle-metadata{display:flex;padding:18px 0;border-bottom:1px solid var(--border);gap:14px;flex-direction:column}.metadata-group{display:flex;gap:8px;flex-direction:column}.metadata-label{color:var(--muted-foreground);font-size:9px;letter-spacing:.14em}.metadata-items{display:flex;flex-wrap:wrap;gap:6px}.metadata-chip{flex:none;padding:3px 7px;border:1px solid var(--border);color:var(--muted-foreground);font-size:9px;line-height:1.35;white-space:nowrap}.risk-chip{border-color:color-mix(in srgb,#d97706 55%,var(--border));color:#d97706}
.room-privacy-row{display:flex;padding:16px 0 2px;align-items:center;justify-content:space-between;gap:14px}.room-privacy-copy{display:flex;min-width:0;gap:4px;flex-direction:column}.room-privacy-copy>text:first-child{font-size:10px;color:var(--foreground);letter-spacing:.12em}.room-privacy-copy>text:last-child{font-size:9px;color:var(--muted-foreground);line-height:1.5}
@media(max-width:767px){.game-page{box-sizing:border-box;height:calc(100vh - 120px - env(safe-area-inset-bottom));height:calc(100dvh - 120px - env(safe-area-inset-bottom));grid-template-columns:1fr}.puzzle-panel{display:none}.mobile-puzzle-summary{display:block;max-height:62%;overflow-y:auto;border-bottom:1px solid var(--border);background:var(--card)}.chat-panel{position:absolute;z-index:4;right:0;bottom:0;left:0;height:var(--mobile-chat-height);min-height:0;background:var(--background);box-shadow:0 -12px 32px rgba(0,0,0,.14)}.chat-resize-handle{cursor:ns-resize;touch-action:none;user-select:none}.chat-grip{position:absolute;top:5px;left:50%;display:block;width:38px;height:3px;border-radius:2px;background:var(--border);transform:translateX(-50%)}.mobile-puzzle-row{display:flex;height:52px;padding:0 14px;align-items:center;gap:12px}.mobile-help,.mobile-expand{display:flex;width:30px;height:30px;margin:0;padding:0;border:1px solid var(--border);border-radius:0;align-items:center;justify-content:center;background:transparent;color:var(--foreground);line-height:1}.mobile-puzzle-title{overflow:hidden;flex:1;font-size:20px;text-overflow:ellipsis;white-space:nowrap}.mobile-question-count{font-size:11px;color:var(--muted-foreground)}.mobile-surface-wrap{position:relative;padding:10px 52px 11px 16px;border-top:1px solid var(--border)}.mobile-surface{display:-webkit-box;padding:0;border:0;overflow:hidden;-webkit-box-orient:vertical;-webkit-line-clamp:3}.mobile-surface.expanded{display:block;overflow:visible}.mobile-surface-wrap .mobile-expand{position:absolute;right:14px;bottom:14px}.mobile-team{padding:0 16px;border-top:1px solid var(--border)}.mobile-team-toggle{display:flex;width:100%;height:42px;margin:0;padding:0;border:0;border-radius:0;align-items:center;gap:12px;background:transparent;color:var(--foreground)}.mobile-team-toggle .section-row{flex:1}.mobile-team-details{padding:0 0 14px}.mobile-action-fab{position:fixed;z-index:40;width:44px;height:44px;touch-action:none;user-select:none}.mobile-action-menu{position:absolute;top:0;right:54px;display:flex;height:44px;padding:3px;border:1px solid color-mix(in srgb,var(--border) 86%,transparent);border-radius:24px;gap:0;background:color-mix(in srgb,var(--card) 96%,transparent);box-shadow:0 8px 24px rgba(0,0,0,.12);box-sizing:border-box;backdrop-filter:blur(16px);pointer-events:auto;animation:mobile-actions-in .18s cubic-bezier(.2,.8,.2,1)}.mobile-fab-trigger,.mobile-fab-option{display:flex;margin:0;padding:0;align-items:center;justify-content:center;pointer-events:auto}.mobile-fab-trigger{width:44px;height:44px;min-height:44px;border:1px solid color-mix(in srgb,var(--foreground) 86%,transparent);border-radius:50%;background:var(--foreground);color:var(--background);box-shadow:0 8px 24px rgba(0,0,0,.18);transition:background .18s ease,color .18s ease,box-shadow .18s ease}.mobile-action-fab.open .mobile-fab-trigger{border-color:color-mix(in srgb,var(--border) 86%,transparent);background:color-mix(in srgb,var(--card) 96%,transparent);color:var(--foreground);box-shadow:0 8px 24px rgba(0,0,0,.12);backdrop-filter:blur(16px)}.mobile-fab-option{width:auto;min-width:64px;height:36px;min-height:36px;padding:0 15px;border:0;border-radius:18px;background:transparent;color:var(--foreground);box-shadow:none;font-size:12px;font-weight:500;line-height:1;letter-spacing:.08em;white-space:nowrap}.mobile-fab-option+.mobile-fab-option{position:relative}.mobile-fab-option+.mobile-fab-option::before{position:absolute;top:10px;bottom:10px;left:0;width:1px;background:var(--border);content:''}.mobile-fab-option.leave{color:var(--muted-foreground)}.mobile-fab-option.danger{color:#dc5a5a}.mobile-fab-option:active{background:var(--secondary)}.tabs{height:50px}.solo-head{position:relative;height:50px;padding:0 18px}.messages{padding:14px 16px}.message{max-width:88%}.composer{padding:8px 12px 10px}.composer .input-row{height:48px;border:1px solid var(--border);background:var(--background)}.composer .input-row input{box-sizing:border-box;height:100%;min-width:0;padding:0 14px;border:0;background:transparent;color:var(--foreground);font-size:13px}.composer .input-row button{display:flex;width:84px;height:100%;min-height:0;padding:0;border:0;border-left:1px solid var(--border);align-items:center;justify-content:center;background:var(--foreground);color:var(--background);font-size:12px;line-height:1;letter-spacing:.08em}}
@keyframes mobile-actions-in{from{opacity:0;transform:translateX(6px) scale(.96)}to{opacity:1;transform:none}}
@media(max-width:767px){.mobile-team-metadata{padding:12px 0}.mobile-team-metadata .metadata-group{gap:6px}}
@media(max-width:767px){.mobile-room-privacy{padding:12px 0;border-bottom:1px solid var(--border)}}
@media(max-width:767px){.mobile-team-details .member{flex-wrap:nowrap}.mobile-member-actions{width:auto;margin-left:auto}.mobile-member-actions .mobile-icon-button{display:flex;width:30px;height:30px;padding:0;flex:none;align-items:center;justify-content:center}}
.mobile-chat-dragbar{display:none}
@media(max-width:767px){.mobile-chat-dragbar{position:relative;display:flex;height:22px;flex:none;align-items:center;justify-content:center;border-bottom:1px solid var(--border);background:var(--card);color:var(--muted-foreground)}.mobile-chat-dragbar text{font-size:8px;letter-spacing:.14em}.mobile-chat-dragbar .chat-grip{top:4px}}
.input-row button{width:112px;padding-right:12px;padding-left:12px;flex:0 0 112px;white-space:nowrap}
@media(max-width:767px){.composer .input-row button{width:96px;padding-right:8px;padding-left:8px;flex-basis:96px;letter-spacing:.04em;white-space:nowrap}}
/* #ifdef MP-WEIXIN || MP-TOUTIAO */
@media(max-width:767px){.game-page{height:calc(100vh - var(--hgt-mobile-header-offset,56px) - 64px - env(safe-area-inset-bottom));height:calc(100dvh - var(--hgt-mobile-header-offset,56px) - 64px - env(safe-area-inset-bottom))}.mobile-action-fab{z-index:100}.mini-cover-fab .mobile-fab-trigger,.mini-cover-fab .mobile-fab-option{font-size:11px;line-height:38px;text-align:center}.mini-cover-fab .mobile-fab-trigger{font-size:14px;line-height:44px}}
/* #endif */
</style>
