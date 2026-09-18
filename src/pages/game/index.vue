<script setup lang="ts">
/* eslint-disable style/max-statements-per-line */
import { gameApi, roomApi, TurtleApiError } from '@/api/turtle'
import { useGameSocket } from '@/composables/useGameSocket'
import { resolveShareUrl } from '@/config/endpoints'
import { useGameStore } from '@/store/gameStore'
import { usePlayerStore } from '@/store/playerStore'
import { supportsPublicRooms } from '@/utils/platform'
import { paperTextureUrl } from '@/utils/questionCover'
import { openQuestionDetail } from '@/utils/questionRoute'

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
const clueTab = ref<'clues' | 'mood' | 'notes'>('clues')
const clueBoardCollapsed = ref(false)
const localNotes = ref('')
const moodTags = ref<string[]>([])
const customClues = ref<string[]>([])
const newClue = ref('')
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
const mobileClueOpen = ref(false)
const mobileActionOpen = ref(false)
const mobileActionPosition = reactive({ x: 0, y: 0 })
const mobileActionStyle = computed<Record<string, string>>(() => ({ left: `${mobileActionPosition.x}px`, top: `${mobileActionPosition.y}px` }))
const mobileActionPositionKey = 'turtle_mobile_game_action_position_v2'
let mobileActionDragStart = { x: 0, y: 0, left: 0, top: 0 }
let mobileActionDragged = false
/** 线索板浮层位置（可拖到屏内任意处） */
const cluePanelPosition = reactive({ x: 0, y: 0 })
const cluePanelStyle = computed<Record<string, string>>(() => ({ left: `${cluePanelPosition.x}px`, top: `${cluePanelPosition.y}px` }))
const cluePanelPositionKey = 'turtle_mobile_game_clue_panel_v1'
let cluePanelDragStart = { x: 0, y: 0, left: 0, top: 0 }
let cluePanelDragged = false
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
  customClues.value = []
  newClue.value = ''
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
    pageError.value = '题目链接无效，请重新选择题目'
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
    pageError.value = (error as Error).message || '题目加载失败'
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
  catch (error) { errorMessage.value = (error as Error).message; uni.showToast({ title: '判定失败，可原样重试', icon: 'none' }) }
  finally { busy.value = false }
}
function addCustomClue() {
  const text = newClue.value.trim()
  if (!text)
    return
  customClues.value = [...customClues.value, text]
  newClue.value = ''
}
function removeCustomClue(index: number) {
  customClues.value = customClues.value.filter((_, i) => i !== index)
}
function toggleMood(tag: string) {
  moodTags.value = moodTags.value.includes(tag)
    ? moodTags.value.filter(item => item !== tag)
    : [...moodTags.value, tag]
}
/** 多人房间：线索板仅 Socket 同步，不落库；应用远端数据时避免回声广播 */
let applyingRemoteClues = false
function isMultiplayerRoomSync() {
  return game.value?.mode === 'multiplayer' && Boolean(room.value?.id)
}
function broadcastClueBoard(clues: string[]) {
  if (!isMultiplayerRoomSync())
    return
  void socket.roomClueSync(room.value!.id, clues).catch(() => {})
}
watch(customClues, (clues) => {
  if (applyingRemoteClues) {
    applyingRemoteClues = false
    return
  }
  broadcastClueBoard(clues)
})
watch(() => socket.roomClueBoard.value?.nonce, () => {
  const payload = socket.roomClueBoard.value
  if (!payload || !isMultiplayerRoomSync() || payload.room_id !== room.value?.id)
    return
  const selfId = room.value?.members.find(item => item.is_self)?.user_id
  if (selfId !== undefined && payload.user_id === selfId)
    return
  applyingRemoteClues = true
  customClues.value = [...payload.clues]
})
const moodOptions = ['紧张', '诡异', '悲伤', '荒诞', '温馨', '恐怖', '反转']
const discoveredClues = computed(() => game.value?.discovered_points || [])
const progressPercent = computed(() => {
  if (!game.value?.question_limit)
    return 0
  return Math.min(100, Math.round((game.value.question_count / game.value.question_limit) * 100))
})
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
  if (action) {
    try {
      await action()
    }
    catch (error) {
      uni.showToast({ title: (error as Error).message || '操作失败，请稍后重试', icon: 'none' })
    }
  }
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
    x: Math.min(Math.max(8, x), Math.max(8, windowWidth - 52)),
    y: Math.min(Math.max(8, y), Math.max(8, windowHeight - 50)),
  }
}
function restoreMobileActionPosition() {
  const stored = uni.getStorageSync(mobileActionPositionKey) as unknown
  const saved = stored && typeof stored === 'object' ? stored as { x?: number, y?: number } : {}
  const { windowWidth, windowHeight } = mobileWindowInfo()
  const position = clampMobileActionPosition(Number(saved?.x ?? windowWidth - 58), Number(saved?.y ?? windowHeight - 220))
  Object.assign(mobileActionPosition, position)
}
function toggleCluePanel() {
  mobileClueOpen.value = !mobileClueOpen.value
  if (mobileClueOpen.value)
    restoreCluePanelPosition()
}
function clampCluePanelPosition(x: number, y: number) {
  const { windowWidth, windowHeight } = mobileWindowInfo()
  const panelW = Math.min(320, windowWidth - 24)
  const panelH = Math.min(360, windowHeight * 0.5)
  return {
    x: Math.min(Math.max(8, x), Math.max(8, windowWidth - panelW - 8)),
    y: Math.min(Math.max(8, y), Math.max(8, windowHeight - panelH - 80)),
  }
}
function restoreCluePanelPosition() {
  const stored = uni.getStorageSync(cluePanelPositionKey) as unknown
  const saved = stored && typeof stored === 'object' ? stored as { x?: number, y?: number } : {}
  const { windowWidth, windowHeight } = mobileWindowInfo()
  Object.assign(cluePanelPosition, clampCluePanelPosition(
    Number(saved?.x ?? windowWidth - 328),
    Number(saved?.y ?? windowHeight - 420),
  ))
}
function startCluePanelDrag(event: MobileActionTouchEvent) {
  const point = mobileActionPoint(event)
  cluePanelDragged = false
  cluePanelDragStart = { x: point.x, y: point.y, left: cluePanelPosition.x, top: cluePanelPosition.y }
}
function dragCluePanel(event: MobileActionTouchEvent) {
  const point = mobileActionPoint(event)
  const deltaX = point.x - cluePanelDragStart.x
  const deltaY = point.y - cluePanelDragStart.y
  if (Math.abs(deltaX) + Math.abs(deltaY) > 4)
    cluePanelDragged = true
  if (!cluePanelDragged)
    return
  Object.assign(cluePanelPosition, clampCluePanelPosition(cluePanelDragStart.left + deltaX, cluePanelDragStart.top + deltaY))
}
function finishCluePanelDrag() {
  if (!cluePanelDragged)
    return
  uni.setStorageSync(cluePanelPositionKey, { ...cluePanelPosition })
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
      const created = await roomApi.create({ game_id: game.value!.id, max_players: 6, visibility: supportsPublicRooms ? 'public' : 'private' })
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
    void openQuestionDetail({ id: game.value.question_id })
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
    <view class="game-page" :class="{ 'clue-collapsed': clueBoardCollapsed }">
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
            <text>{{ !supportsPublicRooms || room.visibility === 'private' ? '仅可通过邀请码加入' : '会展示在公开房间列表' }}</text>
          </view>
          <wd-switch v-if="supportsPublicRooms && room.is_owner" :model-value="room.visibility === 'private'" :loading="roomPrivacyUpdating" size="18" shape="square" active-color="var(--foreground)" inactive-color="var(--border)" @change="updateRoomPrivacy" />
          <text v-else class="metadata-chip">
            {{ !supportsPublicRooms || room.visibility === 'private' ? '私密' : '公开' }}
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
                  <text>{{ !supportsPublicRooms || room.visibility === 'private' ? '仅可通过邀请码加入' : '会展示在公开房间列表' }}</text>
                </view>
                <wd-switch v-if="supportsPublicRooms && room.is_owner" :model-value="room.visibility === 'private'" :loading="roomPrivacyUpdating" size="18" shape="square" active-color="var(--foreground)" inactive-color="var(--border)" @change="updateRoomPrivacy" />
                <text v-else class="metadata-chip">
                  {{ !supportsPublicRooms || room.visibility === 'private' ? '私密' : '公开' }}
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
        <view
          class="mobile-action-fab"
          :class="{ open: mobileActionOpen }"
          :style="mobileActionStyle"
          @touchstart="startMobileActionDrag"
          @touchmove.stop.prevent="dragMobileAction"
          @touchend="finishMobileActionDrag"
        >
          <view v-if="mobileActionOpen" class="mobile-action-pill">
            <button class="mobile-fab-option clue" :class="{ active: mobileClueOpen }" aria-label="线索板" title="线索板" @click.stop="toggleCluePanel">
              线索 {{ discoveredClues.length + customClues.length }}
            </button>
            <button v-if="!room || room.member_count < room.max_players" class="mobile-fab-option" :disabled="creatingRoom" aria-label="邀请队友" title="邀请队友" @click.stop="mobileInvite">
              {{ creatingRoom ? '创建中' : '分享' }}
            </button>
            <button v-if="game.mode === 'multiplayer' && room" class="mobile-fab-option leave" aria-label="退出房间" title="退出房间" @click.stop="mobileLeaveRoom">
              退出
            </button>
            <button v-if="game.mode === 'single' || room?.is_owner" class="mobile-fab-option danger" aria-label="放弃游戏" title="放弃游戏" @click.stop="mobileAbandon">
              放弃
            </button>
            <button class="mobile-fab-option close" aria-label="收起" title="收起" @click.stop="toggleMobileActionMenu">
              ×
            </button>
          </view>
          <button v-else class="mobile-fab-trigger" aria-label="展开游戏操作" :aria-expanded="mobileActionOpen" @click.stop="toggleMobileActionMenu">
            <wd-icon name="more" size="21px" />
          </button>
        </view>
        <!-- #endif -->
        <!-- #ifndef H5 -->
        <cover-view class="mobile-action-fab mini-cover-fab" :class="{ open: mobileActionOpen }" :style="mobileActionStyle" @touchstart="startMobileActionDrag" @touchmove.stop.prevent="dragMobileAction" @touchend="finishMobileActionDrag">
          <cover-view v-if="mobileActionOpen" class="mobile-action-pill">
            <cover-view class="mobile-fab-option clue" @click="toggleCluePanel">
              线索
            </cover-view>
            <cover-view v-if="!room || room.member_count < room.max_players" class="mobile-fab-option" @click="mobileInvite">
              分享
            </cover-view>
            <cover-view v-if="game.mode === 'multiplayer' && room" class="mobile-fab-option leave" @click="mobileLeaveRoom">
              退出
            </cover-view>
            <cover-view v-if="game.mode === 'single' || room?.is_owner" class="mobile-fab-option danger" @click="mobileAbandon">
              放弃
            </cover-view>
            <cover-view class="mobile-fab-option close" @click="toggleMobileActionMenu">
              ×
            </cover-view>
          </cover-view>
          <cover-view v-else class="mobile-fab-trigger" @click="toggleMobileActionMenu">
            •••
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
              ◈ 裁判在线
            </text>
          </view>
          <!-- #endif -->
          <!-- #ifndef H5 -->
          <view class="solo-head chat-resize-handle" role="slider" aria-label="调整对话区域高度" aria-valuemin="38" aria-valuemax="92" :aria-valuenow="Math.round(mobileChatHeight)" tabindex="0" @touchstart="startMobileResize" @touchmove.stop.prevent="resizeMobileChat">
            <view class="chat-grip" /><text class="hgt-mono">
              ◈ 裁判在线
            </text>
          </view>
          <!-- #endif -->
          <template v-if="tab === 'judge' || game.mode === 'single'">
            <scroll-view scroll-y :scroll-into-view="judgeScrollTarget" scroll-with-animation class="messages">
              <view v-if="!game.messages?.length" class="chat-empty">
                <text class="chat-empty-title">
                  与 AI 主持人对话
                </text>
                <text class="chat-empty-copy">
                  你可以向主持人提出任何与汤面有关的问题，<br>我会回答「是」「不是」或「不重要」。
                </text>
              </view>
              <view v-for="message in game.messages" :id="judgeMessageId(message.sequence)" :key="message.sequence" class="message" :class="message.role">
                <view class="message-author">
                  <image v-if="message.role === 'player' && messageSender(message).avatar_url" :src="messageSender(message).avatar_url!" class="message-avatar" /><view v-else-if="message.role === 'player'" class="message-avatar avatar-fallback">
                    {{ messageSender(message).username.slice(0, 1) }}
                  </view><text class="message-role hgt-mono">
                    {{ message.role === 'host' ? 'AI 主持人' : messageSender(message).username }}
                  </text>
                </view><text>{{ message.content }}</text>
              </view>
            </scroll-view>
            <view class="composer">
              <view v-if="errorMessage" class="error">
                上次问题未扣次数：{{ errorMessage }}
              </view>
              <view class="hints">
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
              <view v-if="!(room?.messages || []).length" class="chat-empty">
                <text class="chat-empty-title">
                  队伍讨论
                </text>
                <text class="chat-empty-copy">
                  这里的消息仅队友可见，不会进入裁判判定。
                </text>
              </view>
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
      <!-- PC 线索板（可折叠） -->
      <aside class="clue-board" :class="{ 'is-collapsed': clueBoardCollapsed }">
        <view v-if="clueBoardCollapsed" class="clue-collapsed-rail">
          <button class="clue-icon-btn" aria-label="展开线索板" title="展开线索板" @click="clueBoardCollapsed = false">
            ‹
          </button>
          <text class="clue-collapsed-count">
            {{ discoveredClues.length + customClues.length }}
          </text>
          <text class="clue-collapsed-label">
            线索
          </text>
        </view>
        <template v-else>
          <view class="clue-board-header">
            <view class="clue-tabs">
              <button :class="{ active: clueTab === 'clues' }" @click="clueTab = 'clues'">
                线索 <text class="tab-count">
                  {{ discoveredClues.length + customClues.length }}
                </text>
              </button>
              <button :class="{ active: clueTab === 'mood' }" @click="clueTab = 'mood'">
                情绪
              </button>
              <button :class="{ active: clueTab === 'notes' }" @click="clueTab = 'notes'">
                笔记
              </button>
            </view>
            <button class="clue-icon-btn" aria-label="收起线索板" title="收起线索板" @click="clueBoardCollapsed = true">
              »
            </button>
          </view>

          <scroll-view scroll-y class="clue-body">
            <template v-if="clueTab === 'clues'">
              <view v-if="!discoveredClues.length && !customClues.length" class="clue-empty">
                {{ game.mode === 'multiplayer' && room ? '还没有线索，添加后会同队友实时同步' : '还没有确认的线索，继续提问吧' }}
              </view>
              <view v-for="(point, index) in discoveredClues" :key="`d-${index}`" class="clue-item found">
                <text class="clue-mark">
                  ★
                </text>
                <text class="clue-text">
                  {{ point }}
                </text>
              </view>
              <view v-for="(clue, index) in customClues" :key="`c-${index}`" class="clue-item custom">
                <image class="clue-tag-icon" src="/static/hgt/paper/paper_tag.png" mode="aspectFit" />
                <text class="clue-text">
                  {{ clue }}
                </text>
                <button class="clue-remove" @click="removeCustomClue(index)">
                  ×
                </button>
              </view>
              <view class="clue-add">
                <input v-model="newClue" placeholder="添加线索…" @confirm="addCustomClue">
                <button @click="addCustomClue">
                  +
                </button>
              </view>
            </template>

            <template v-else-if="clueTab === 'mood'">
              <view class="mood-grid">
                <view
                  v-for="mood in moodOptions"
                  :key="mood"
                  class="mood-chip"
                  :class="{ active: moodTags.includes(mood) }"
                  @click="toggleMood(mood)"
                >
                  {{ mood }}
                </view>
              </view>
              <text class="clue-hint">
                标记当前氛围，帮助回忆推理脉络
              </text>
            </template>

            <template v-else>
              <textarea
                v-model="localNotes"
                class="notes-area"
                placeholder="记下你的推理假设…"
                :maxlength="2000"
              />
            </template>
          </scroll-view>

          <view class="clue-foot">
            <view class="clue-progress-label">
              <text>推理进度</text>
              <text class="clue-progress-num">
                {{ game.question_count }}/{{ game.question_limit }}
              </text>
            </view>
            <view class="clue-progress">
              <view :style="{ width: `${progressPercent}%` }" />
            </view>
            <button class="btn-submit-truth" :disabled="inputMode === 'bottom'" @click="inputMode = 'bottom'">
              提交真相
            </button>
          </view>
        </template>
      </aside>
      <!-- 手机/平板：可拖拽线索板浮层 -->
      <view v-if="mobileClueOpen" class="mobile-clue-sheet">
        <view class="mobile-clue-panel" :style="cluePanelStyle">
          <view
            class="mobile-clue-head clue-drag-handle"
            @touchstart="startCluePanelDrag"
            @touchmove.stop.prevent="dragCluePanel"
            @touchend="finishCluePanelDrag"
          >
            <text class="mobile-clue-title">
              线索板 · 可拖动
            </text>
            <button class="mobile-clue-close" @click="mobileClueOpen = false">
              ×
            </button>
          </view>
          <view class="clue-tabs">
            <button :class="{ active: clueTab === 'clues' }" @click="clueTab = 'clues'">
              线索
            </button>
            <button :class="{ active: clueTab === 'mood' }" @click="clueTab = 'mood'">
              情绪
            </button>
            <button :class="{ active: clueTab === 'notes' }" @click="clueTab = 'notes'">
              笔记
            </button>
          </view>
          <scroll-view scroll-y class="clue-body mobile-clue-body">
            <template v-if="clueTab === 'clues'">
              <view v-if="!discoveredClues.length && !customClues.length" class="clue-empty">
                还没有确认的线索
              </view>
              <view v-for="(point, index) in discoveredClues" :key="`md-${index}`" class="clue-item found">
                <text class="clue-mark">
                  ★
                </text>
                <text class="clue-text">
                  {{ point }}
                </text>
              </view>
              <view v-for="(clue, index) in customClues" :key="`mc-${index}`" class="clue-item custom">
                <image class="clue-tag-icon" src="/static/hgt/paper/paper_tag.png" mode="aspectFit" />
                <text class="clue-text">
                  {{ clue }}
                </text>
                <button class="clue-remove" @click="removeCustomClue(index)">
                  ×
                </button>
              </view>
              <view class="clue-add">
                <input v-model="newClue" placeholder="添加线索…" @confirm="addCustomClue">
                <button @click="addCustomClue">
                  +
                </button>
              </view>
            </template>
            <template v-else-if="clueTab === 'mood'">
              <view class="mood-grid">
                <view
                  v-for="mood in moodOptions"
                  :key="mood"
                  class="mood-chip"
                  :class="{ active: moodTags.includes(mood) }"
                  @click="toggleMood(mood)"
                >
                  {{ mood }}
                </view>
              </view>
            </template>
            <template v-else>
              <textarea v-model="localNotes" class="notes-area" placeholder="记下你的推理假设…" :maxlength="2000" />
            </template>
          </scroll-view>
          <button class="btn-submit-truth" @click="inputMode = 'bottom'; mobileClueOpen = false">
            提交真相
          </button>
        </view>
      </view>
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
        <image class="result-bg" src="/static/hgt/bg/bg_lighthouse.jpg" mode="aspectFill" />
        <image class="result-splash" src="/static/hgt/ui/water_splash.png" mode="aspectFit" />
        <view class="result-veil" />
        <view class="result-content">
          <text class="result-kicker">
            TRUTH REVEALED
          </text>
          <text class="result-heading">
            真相，已浮出水面
          </text>
          <text class="result-sub">
            所有的疑问，终于有了答案
          </text>
          <view class="result-paper">
            <image class="result-paper-texture" :src="paperTextureUrl(game.id)" mode="aspectFill" />
            <view class="result-paper-veil" />
            <view class="result-paper-inner">
              <text class="result-paper-label">
                汤底
              </text>
              <text class="result-bottom">
                {{ game.bottom }}
              </text>
            </view>
            <image class="result-stamp-img" src="/static/hgt/ui/stamp_truth.png" mode="aspectFit" />
          </view>
          <view v-if="game.points?.length" class="result-points">
            <text class="result-points-label">
              关键推理点
            </text>
            <text v-for="point in game.points" :key="point.key" class="result-point">
              {{ point.content }}
            </text>
          </view>
          <view class="result-actions">
            <button class="btn-ghost-result" @click="goHome">
              返回首页
            </button>
            <button class="btn-primary-result" :disabled="busy" @click="continuePlaying">
              再来一碗
            </button>
          </view>
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
  <view v-else-if="pageError" class="game-load-state">
    <image class="game-load-img" src="/static/hgt/empty/empty_network.png" mode="aspectFit" />
    <text class="hgt-mono game-load-eyebrow">
      GAME UNAVAILABLE
    </text>
    <text class="hgt-display game-load-title">
      无法进入题目
    </text>
    <text class="game-load-copy">
      {{ pageError }}
    </text>
    <button class="hgt-mono game-load-action" @click="returnToQuestionLibrary">
      返回题库
    </button>
  </view>
  <view v-else class="game-load-state">
    <image class="game-load-img" src="/static/hgt/empty/empty_loading.png" mode="aspectFit" />
    <text class="hgt-mono game-load-eyebrow">
      LOADING
    </text>
    <text class="hgt-display game-load-title">
      正在进入题目
    </text>
    <text class="game-load-copy">
      正在读取题目…
    </text>
  </view>
</template>

<style scoped>
.game-page {
  position: relative;
  display: grid;
  height: calc(100vh - var(--hgt-header-h, 64px));
  height: calc(100dvh - var(--hgt-header-h, 64px));
  overflow: hidden;
  grid-template-columns: 300px minmax(0, 1fr) 280px;
  background: var(--hgt-bg);
  color: var(--hgt-text);
}
.game-page.clue-collapsed {
  grid-template-columns: 300px minmax(0, 1fr) 48px;
}

.back-question {
  display: flex;
  width: max-content;
  height: 30px;
  margin: 0 0 14px;
  padding: 0;
  border: 0;
  align-items: center;
  background: transparent;
  color: var(--hgt-text-2);
  font-size: 12px;
}
.back-question::after { border: 0; }

.game-load-state {
  display: flex;
  box-sizing: border-box;
  min-height: 100vh;
  padding: 48px 24px;
  align-items: center;
  justify-content: center;
  flex-direction: column;
  text-align: center;
  background: var(--hgt-bg);
}
.game-load-img {
  width: min(240px, 70vw);
  height: 180px;
  margin-bottom: 8px;
  border-radius: var(--hgt-radius-lg);
  filter: drop-shadow(0 8px 24px rgba(4, 12, 14, 0.4));
}
.game-load-eyebrow {
  color: var(--hgt-brand);
  font-family: var(--hgt-font-mono);
  font-size: 11px;
  letter-spacing: 0.28em;
}
.game-load-title {
  margin-top: 16px;
  color: var(--hgt-text);
  font-family: var(--hgt-font-display);
  font-size: 28px;
  font-weight: 600;
}
.game-load-copy {
  max-width: 420px;
  margin-top: 12px;
  color: var(--hgt-text-2);
  font-size: 14px;
  line-height: 1.7;
}
.game-load-action {
  display: flex;
  width: 180px;
  height: 44px;
  margin: 28px 0 0;
  padding: 0;
  border: 0;
  border-radius: var(--hgt-radius-sm);
  align-items: center;
  justify-content: center;
  background: var(--hgt-brand);
  color: var(--hgt-on-brand);
  font-size: 14px;
}
.game-load-action::after { border: 0; }

/* ===== Left panel ===== */
.puzzle-panel {
  display: flex;
  padding: 24px 20px;
  border-right: 1px solid var(--hgt-border);
  flex-direction: column;
  overflow-y: auto;
  background: var(--hgt-card);
}
.puzzle-id {
  color: var(--hgt-brand);
  font-size: 11px;
  letter-spacing: 0.16em;
}
.puzzle-title {
  margin: 10px 0 8px;
  color: var(--hgt-text);
  font-family: var(--hgt-font-display);
  font-size: 22px;
  font-weight: 600;
}
.surface {
  padding-bottom: 16px;
  border-bottom: 1px solid var(--hgt-border);
  color: var(--hgt-text-2);
  font-size: 13px;
  line-height: 1.75;
}
.label {
  color: var(--hgt-text-3);
  font-size: 11px;
  letter-spacing: 0.12em;
}
.puzzle-metadata {
  display: flex;
  padding: 14px 0;
  border-bottom: 1px solid var(--hgt-border);
  gap: 10px;
  flex-direction: column;
}
.metadata-group {
  display: flex;
  gap: 6px;
  flex-direction: column;
}
.metadata-label {
  color: var(--hgt-text-3);
  font-size: 10px;
  letter-spacing: 0.12em;
}
.metadata-items {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}
.metadata-chip {
  padding: 3px 8px;
  border: 1px solid var(--hgt-border);
  border-radius: var(--hgt-radius-xs);
  color: var(--hgt-text-2);
  font-size: 11px;
}
.risk-chip {
  border-color: rgba(196, 154, 85, 0.5);
  color: var(--hgt-warning);
}
.room-privacy-row {
  display: flex;
  padding: 12px 0 4px;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}
.room-privacy-copy {
  display: flex;
  min-width: 0;
  gap: 3px;
  flex-direction: column;
}
.room-privacy-copy > text:first-child {
  color: var(--hgt-text);
  font-size: 11px;
}
.room-privacy-copy > text:last-child {
  color: var(--hgt-text-3);
  font-size: 11px;
  line-height: 1.45;
}
.team-block,
.question-count {
  padding: 16px 0;
  border-bottom: 1px solid var(--hgt-border);
}
.section-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
}
.member {
  display: flex;
  margin-top: 12px;
  align-items: center;
  gap: 10px;
  font-size: 13px;
}
.member-info {
  display: flex;
  min-width: 0;
  gap: 2px;
  flex-direction: column;
}
.member-avatar-wrap {
  position: relative;
  display: flex;
  width: 32px;
  height: 32px;
  flex: none;
}
.avatar {
  width: 32px;
  height: 32px;
  border-radius: 50%;
}
.avatar-fallback {
  display: flex;
  align-items: center;
  justify-content: center;
  background: var(--hgt-card-2);
  color: var(--hgt-brand);
}
.member-muted-badge {
  position: absolute;
  right: -3px;
  bottom: -3px;
  display: flex;
  box-sizing: border-box;
  width: 15px;
  height: 15px;
  border: 2px solid var(--hgt-card);
  border-radius: 50%;
  align-items: center;
  justify-content: center;
  background: var(--hgt-danger);
  color: #fff;
}
.member-role {
  margin-left: auto;
  color: var(--hgt-brand);
  font-size: 10px;
}
.member-actions {
  display: flex;
  gap: 4px;
}
.member-actions button {
  display: flex;
  height: 24px;
  margin: 0;
  padding: 0 8px;
  border: 1px solid var(--hgt-border);
  border-radius: var(--hgt-radius-xs);
  align-items: center;
  background: transparent;
  color: var(--hgt-text-2);
  font-size: 11px;
}
.member-actions .kick {
  color: var(--hgt-danger);
}
.count {
  color: var(--hgt-text);
  font-family: var(--hgt-font-display);
  font-size: 18px;
  font-weight: 600;
}
.progress,
.clue-progress {
  height: 4px;
  margin-top: 10px;
  border-radius: 2px;
  background: var(--hgt-border);
  overflow: hidden;
}
.progress > view,
.clue-progress > view {
  height: 100%;
  border-radius: 2px;
  background: var(--hgt-brand);
  transition: width var(--hgt-dur-base) var(--hgt-ease-out);
}
.panel-actions {
  display: flex;
  margin-top: auto;
  padding-top: 16px;
  gap: 8px;
  flex-direction: column;
}
.outline,
.danger {
  height: 40px;
  margin: 0;
  border: 1px solid var(--hgt-border);
  border-radius: var(--hgt-radius-sm);
  background: transparent;
  color: var(--hgt-text-2);
  font-size: 13px;
}
.outline::after,
.danger::after { border: 0; }
.danger {
  border-color: rgba(201, 74, 85, 0.4);
  color: var(--hgt-danger);
}

/* ===== Conversation ===== */
.conversation {
  position: relative;
  display: flex;
  min-width: 0;
  flex-direction: column;
  overflow: hidden;
  background: var(--hgt-bg);
}
.mobile-puzzle-summary {
  display: none;
}
.mobile-action-fab { display: none; }
.mobile-action-fab { overflow: visible; }
.mobile-clue-bar { display: none; }
.mobile-clue-sheet { display: none; }

.chat-panel {
  display: flex;
  min-height: 0;
  flex: 1;
  flex-direction: column;
  background: var(--hgt-bg);
}
.mobile-chat-dragbar { display: none; }
.chat-grip {
  position: absolute;
  top: 5px;
  left: 50%;
  display: block;
  width: 38px;
  height: 3px;
  border-radius: 2px;
  background: var(--hgt-border);
  transform: translateX(-50%);
}
.tabs {
  display: flex;
  flex: none;
  border-bottom: 1px solid var(--hgt-border);
  background: var(--hgt-bg-deep);
}
.tabs button {
  position: relative;
  display: flex;
  box-sizing: border-box;
  flex: 1;
  height: 52px;
  margin: 0;
  padding: 0 12px;
  border: 0;
  align-items: center;
  justify-content: center;
  background: transparent;
  color: var(--hgt-text-2);
  font-size: 13px;
  line-height: 1;
}
.tabs button::after { border: 0; }
.tabs button.active {
  color: var(--hgt-brand);
}
.tabs button.active::after {
  position: absolute;
  right: 20%;
  bottom: 0;
  left: 20%;
  height: 2px;
  background: var(--hgt-brand);
  content: '';
}
.solo-head {
  position: relative;
  display: flex;
  height: 44px;
  padding: 0 16px;
  border-bottom: 1px solid var(--hgt-border);
  align-items: center;
  justify-content: center;
  background: var(--hgt-bg-deep);
  color: var(--hgt-text-2);
  font-size: 12px;
  letter-spacing: 0.12em;
}
.messages {
  flex: 1;
  min-height: 0;
  padding: 18px 20px;
  box-sizing: border-box;
}
.chat-empty {
  display: flex;
  height: 100%;
  min-height: 200px;
  padding: 24px;
  align-items: center;
  justify-content: center;
  gap: 10px;
  flex-direction: column;
  text-align: center;
}
.chat-empty-title {
  color: var(--hgt-text);
  font-family: var(--hgt-font-display);
  font-size: 18px;
  font-weight: 600;
}
.chat-empty-copy {
  color: var(--hgt-text-2);
  font-size: 13px;
  line-height: 1.7;
}
.message {
  display: flex;
  max-width: min(72%, 560px);
  margin-bottom: 14px;
  padding: 12px 14px;
  border: 1px solid var(--hgt-border);
  border-radius: var(--hgt-radius-md);
  gap: 8px;
  flex-direction: column;
  background: var(--hgt-card);
  box-shadow: var(--hgt-shadow-sm);
  font-size: 14px;
  line-height: 1.65;
}
.message.host {
  align-self: flex-start;
  border-color: var(--hgt-border-soft);
  background: var(--hgt-card-2);
}
.message.player {
  align-self: flex-end;
  border-color: rgba(91, 200, 189, 0.35);
  background: var(--hgt-brand-soft);
}
.message.team {
  max-width: 80%;
}
.message-author {
  display: flex;
  align-items: center;
  gap: 8px;
}
.message-avatar {
  width: 22px;
  height: 22px;
  border-radius: 50%;
}
.message-role {
  color: var(--hgt-text-3);
  font-size: 11px;
  letter-spacing: 0.08em;
}
.message.player .message-role {
  color: var(--hgt-brand);
}

.composer {
  flex: none;
  padding: 12px 16px 16px;
  border-top: 1px solid var(--hgt-border);
  background: var(--hgt-bg-deep);
}
.error {
  margin-bottom: 8px;
  padding: 8px 10px;
  border-radius: var(--hgt-radius-xs);
  background: rgba(201, 74, 85, 0.12);
  color: var(--hgt-danger);
  font-size: 12px;
}
.hints {
  display: flex;
  margin-bottom: 10px;
  gap: 8px;
  flex-wrap: wrap;
}
.hints button {
  display: flex;
  box-sizing: border-box;
  height: 30px;
  margin: 0;
  padding: 0 12px;
  border: 1px solid var(--hgt-border);
  border-radius: var(--hgt-radius-full);
  align-items: center;
  justify-content: center;
  background: transparent;
  color: var(--hgt-text-2);
  font-size: 12px;
  line-height: 1;
}
.hints button::after { border: 0; }
.hints button.bottom-mode.active {
  border-color: var(--hgt-warning);
  color: var(--hgt-warning);
  background: rgba(196, 154, 85, 0.12);
}
.input-row {
  display: flex;
  gap: 8px;
}
.input-row input {
  flex: 1;
  height: 44px;
  padding: 0 14px;
  border: 1px solid var(--hgt-border);
  border-radius: var(--hgt-radius-sm);
  background: var(--hgt-card);
  color: var(--hgt-text);
  font-size: 14px;
}
.input-row button {
  display: flex;
  width: 96px;
  flex: none;
  height: 44px;
  margin: 0;
  padding: 0;
  border: 0;
  border-radius: var(--hgt-radius-sm);
  align-items: center;
  justify-content: center;
  background: var(--hgt-brand);
  color: var(--hgt-on-brand);
  font-size: 14px;
  font-weight: 600;
}
.input-row button::after { border: 0; }
.typing {
  margin-bottom: 6px;
  color: var(--hgt-text-3);
  font-size: 11px;
}

/* ===== Clue board (PC) ===== */
.clue-board {
  display: flex;
  min-height: 0;
  border-left: 1px solid var(--hgt-border);
  flex-direction: column;
  background: var(--hgt-card);
}
.clue-board.is-collapsed {
  align-items: stretch;
}
.clue-board-header {
  display: flex;
  flex: none;
  border-bottom: 1px solid var(--hgt-border);
  align-items: center;
}
.clue-tabs {
  display: flex;
  flex: 1;
  min-width: 0;
  align-items: center;
  justify-content: center;
}
.clue-icon-btn {
  display: flex;
  flex: none;
  box-sizing: border-box;
  width: 48px;
  height: 52px;
  margin: 0;
  padding: 0;
  border: 0;
  align-items: center;
  justify-content: center;
  background: transparent;
  color: var(--hgt-text-2);
  font-size: 20px;
  line-height: 1;
}
.clue-icon-btn::after { border: 0; }
.clue-icon-btn:hover {
  color: var(--hgt-brand);
}
.clue-collapsed-rail {
  display: flex;
  flex: 1;
  min-height: 0;
  padding: 10px 0 16px;
  align-items: center;
  flex-direction: column;
  gap: 10px;
}
.clue-collapsed-rail .clue-icon-btn {
  width: 44px;
  height: 44px;
  border: 1px solid var(--hgt-border);
  border-radius: var(--hgt-radius-sm);
  color: var(--hgt-brand);
  font-size: 20px;
}
.clue-collapsed-count {
  display: flex;
  box-sizing: border-box;
  min-width: 28px;
  height: 28px;
  padding: 0 6px;
  border-radius: var(--hgt-radius-full);
  align-items: center;
  justify-content: center;
  background: var(--hgt-brand-soft);
  color: var(--hgt-brand);
  font-family: var(--hgt-font-mono);
  font-size: 12px;
  line-height: 1;
}
.clue-collapsed-label {
  color: var(--hgt-text-3);
  font-size: 11px;
  letter-spacing: 0.12em;
  writing-mode: vertical-rl;
}
.clue-tabs button {
  position: relative;
  display: flex;
  flex: 1;
  box-sizing: border-box;
  min-width: 0;
  height: 52px;
  margin: 0;
  padding: 0 8px;
  border: 0;
  align-items: center;
  justify-content: center;
  flex-direction: row;
  background: transparent;
  color: var(--hgt-text-2);
  font-size: 15px;
  font-weight: 500;
  line-height: 1;
  text-align: center;
}
.clue-tabs button::after { border: 0; }
.clue-tabs button.active {
  color: var(--hgt-brand);
}
.clue-tabs button.active::after {
  position: absolute;
  right: 28%;
  bottom: 0;
  left: 28%;
  height: 2px;
  border-radius: 1px 1px 0 0;
  background: var(--hgt-brand);
  content: '';
}
.tab-count {
  margin-left: 4px;
  color: var(--hgt-brand);
  font-size: 11px;
}
.clue-item.custom .clue-tag-icon {
  flex: none;
  width: 16px;
  height: 20px;
  margin-right: 4px;
  opacity: 0.75;
}
.clue-body {
  flex: 1;
  min-height: 0;
  padding: 14px;
  box-sizing: border-box;
}
.clue-empty {
  padding: 24px 8px;
  color: var(--hgt-text-3);
  font-size: 13px;
  text-align: center;
  line-height: 1.6;
}
.clue-item {
  display: flex;
  margin-bottom: 8px;
  padding: 10px 12px;
  border: 1px solid var(--hgt-border);
  border-radius: var(--hgt-radius-sm);
  align-items: flex-start;
  gap: 8px;
  background: var(--hgt-card-2);
  font-size: 13px;
  line-height: 1.5;
}
.clue-item.found {
  border-color: rgba(91, 200, 189, 0.35);
}
.clue-mark {
  flex: none;
  color: var(--hgt-brand);
}
.clue-text {
  flex: 1;
  color: var(--hgt-text);
}
.clue-remove {
  display: flex;
  flex: none;
  box-sizing: border-box;
  width: 22px;
  height: 22px;
  margin: 0;
  padding: 0;
  border: 0;
  align-items: center;
  justify-content: center;
  background: transparent;
  color: var(--hgt-text-3);
  font-size: 16px;
  line-height: 1;
}
.clue-remove::after { border: 0; }
.clue-add {
  display: flex;
  margin-top: 10px;
  gap: 6px;
}
.clue-add input {
  flex: 1;
  height: 36px;
  padding: 0 10px;
  border: 1px solid var(--hgt-border);
  border-radius: var(--hgt-radius-sm);
  background: var(--hgt-bg);
  color: var(--hgt-text);
  font-size: 13px;
}
.clue-add button {
  display: flex;
  flex: none;
  box-sizing: border-box;
  width: 36px;
  height: 36px;
  margin: 0;
  padding: 0;
  border: 0;
  border-radius: var(--hgt-radius-sm);
  align-items: center;
  justify-content: center;
  background: var(--hgt-brand);
  color: var(--hgt-on-brand);
  font-size: 18px;
  line-height: 1;
}
.clue-add button::after { border: 0; }
.mood-grid {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}
.mood-chip {
  padding: 8px 12px;
  border: 1px solid var(--hgt-border);
  border-radius: var(--hgt-radius-full);
  color: var(--hgt-text-2);
  font-size: 13px;
}
.mood-chip.active {
  border-color: var(--hgt-brand);
  background: var(--hgt-brand-soft);
  color: var(--hgt-brand);
}
.clue-hint {
  display: block;
  margin-top: 14px;
  color: var(--hgt-text-3);
  font-size: 12px;
  line-height: 1.5;
}
.notes-area {
  box-sizing: border-box;
  width: 100%;
  min-height: 220px;
  padding: 12px;
  border: 1px solid var(--hgt-border);
  border-radius: var(--hgt-radius-sm);
  background: var(--hgt-bg);
  color: var(--hgt-text);
  font-size: 13px;
  line-height: 1.6;
}
.clue-foot {
  flex: none;
  padding: 14px;
  border-top: 1px solid var(--hgt-border);
}
.clue-progress-label {
  display: flex;
  margin-bottom: 8px;
  align-items: center;
  justify-content: space-between;
  color: var(--hgt-text-2);
  font-size: 12px;
}
.clue-progress-num {
  color: var(--hgt-text);
  font-family: var(--hgt-font-mono);
}
.btn-submit-truth {
  display: flex;
  box-sizing: border-box;
  width: 100%;
  height: 44px;
  margin: 14px 0 0;
  padding: 0;
  border: 0;
  border-radius: var(--hgt-radius-sm);
  align-items: center;
  justify-content: center;
  background: var(--hgt-brand);
  color: var(--hgt-on-brand);
  font-size: 15px;
  font-weight: 600;
  line-height: 1;
}
.btn-submit-truth::after { border: 0; }
.btn-submit-truth:disabled {
  opacity: 0.55;
}

/* ===== Invite / result popups ===== */
.invite-modal {
  display: flex;
  box-sizing: border-box;
  width: 100%;
  padding: 28px;
  gap: 12px;
  flex-direction: column;
  background: var(--hgt-card);
  color: var(--hgt-text);
}
.invite-heading {
  color: var(--hgt-text);
  font-family: var(--hgt-font-display);
  font-size: 22px;
  font-weight: 600;
}
.invite-link-row {
  display: flex;
  gap: 8px;
  align-items: center;
  flex-wrap: wrap;
}
.invite-code {
  display: flex;
  box-sizing: border-box;
  flex: 1;
  min-width: 0;
  height: 40px;
  padding: 0 12px;
  border: 1px dashed var(--hgt-border-soft);
  border-radius: var(--hgt-radius-sm);
  align-items: center;
  color: var(--hgt-brand);
  font-size: 13px;
}
.copy-button,
.close-invite {
  display: flex;
  box-sizing: border-box;
  height: 40px;
  margin: 0;
  padding: 0 16px;
  border: 0;
  border-radius: var(--hgt-radius-sm);
  align-items: center;
  justify-content: center;
  background: var(--hgt-brand);
  color: var(--hgt-on-brand);
  font-size: 13px;
  line-height: 1;
}
.copy-button::after,
.close-invite::after { border: 0; }
.invite-members-title {
  margin-top: 8px;
  color: var(--hgt-text-3);
  font-size: 12px;
}
.invite-member {
  display: flex;
  padding: 8px 0;
  border-bottom: 1px solid var(--hgt-border);
  align-items: center;
  gap: 10px;
  font-size: 13px;
}
.invite-member .avatar {
  display: flex;
  width: 28px;
  height: 28px;
  align-items: center;
  justify-content: center;
}
.invite-member .member-role {
  margin-left: auto;
}

.result-modal {
  position: relative;
  display: flex;
  box-sizing: border-box;
  width: min(560px, calc(100vw - 32px));
  max-height: 88vh;
  border-radius: var(--hgt-radius-lg);
  overflow: hidden;
  flex-direction: column;
  background: var(--hgt-bg-deep);
  color: var(--hgt-text);
  box-shadow: var(--hgt-shadow-float);
}
.result-bg {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
}
.result-splash {
  position: absolute;
  z-index: 0;
  top: -20px;
  right: -10px;
  width: min(280px, 45%);
  opacity: 0.28;
  pointer-events: none;
}
.result-veil {
  position: absolute;
  inset: 0;
  background: linear-gradient(180deg, rgba(7, 20, 24, 0.55), rgba(12, 32, 39, 0.92));
}
.result-content {
  position: relative;
  z-index: 1;
  display: flex;
  padding: 32px 28px;
  gap: 12px;
  flex-direction: column;
  overflow-y: auto;
}
.result-kicker {
  color: var(--hgt-brand);
  font-family: var(--hgt-font-mono);
  font-size: 11px;
  letter-spacing: 0.28em;
}
.result-heading {
  color: var(--hgt-text);
  font-family: var(--hgt-font-display);
  font-size: 28px;
  font-weight: 600;
  letter-spacing: 0.06em;
}
.result-sub {
  color: var(--hgt-text-2);
  font-size: 13px;
}
.result-paper {
  position: relative;
  margin-top: 8px;
  border-radius: var(--hgt-radius-md);
  overflow: hidden;
  background: var(--hgt-paper);
  box-shadow: var(--hgt-shadow-md);
}
.result-paper-texture {
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  opacity: 0.92;
}
.result-paper-veil {
  position: absolute;
  inset: 0;
  pointer-events: none;
  background: linear-gradient(
    160deg,
    color-mix(in srgb, var(--hgt-paper) 22%, transparent),
    color-mix(in srgb, var(--hgt-paper) 48%, transparent)
  );
}
.result-paper-inner {
  position: relative;
  z-index: 1;
  display: flex;
  padding: 22px 24px;
  gap: 10px;
  flex-direction: column;
}
.result-paper-label {
  color: #5a5e48;
  font-size: 12px;
  letter-spacing: 0.28em;
}
.result-bottom {
  color: var(--hgt-paper-ink);
  font-family: var(--hgt-font-display);
  font-size: 15px;
  line-height: 1.85;
  white-space: pre-wrap;
}
.result-stamp-img {
  position: absolute;
  z-index: 2;
  top: 8px;
  right: 4px;
  width: 120px;
  height: 120px;
  transform: rotate(-12deg);
  opacity: 0.92;
  pointer-events: none;
}
.result-points {
  display: flex;
  gap: 8px;
  flex-direction: column;
}
.result-points-label {
  color: var(--hgt-text-3);
  font-size: 12px;
  letter-spacing: 0.12em;
}
.result-point {
  padding: 8px 0;
  border-bottom: 1px solid var(--hgt-border);
  color: var(--hgt-text-2);
  font-size: 13px;
  line-height: 1.55;
}
.result-actions {
  display: flex;
  margin-top: 12px;
  gap: 10px;
}
.btn-ghost-result,
.btn-primary-result {
  display: flex;
  height: 44px;
  margin: 0;
  padding: 0 20px;
  border-radius: var(--hgt-radius-sm);
  align-items: center;
  justify-content: center;
  flex: 1;
  font-size: 14px;
  font-weight: 600;
}
.btn-ghost-result {
  border: 1px solid var(--hgt-border-soft);
  background: transparent;
  color: var(--hgt-text);
}
.btn-primary-result {
  border: 0;
  background: var(--hgt-brand);
  color: var(--hgt-on-brand);
}
.btn-ghost-result::after,
.btn-primary-result::after { border: 0; }

:deep(.invite-popup),
:deep(.result-popup) {
  box-sizing: border-box;
  width: min(520px, calc(100vw - 32px));
  border: 1px solid var(--hgt-border);
  border-radius: var(--hgt-radius-lg);
  background: var(--hgt-card);
  color: var(--hgt-text);
  overflow: hidden;
}
:deep(.result-popup) {
  width: min(560px, calc(100vw - 32px));
  background: transparent;
  border: 0;
}
:deep(.result-popup) .result-modal {
  width: 100%;
}

/* ===== Tablet ===== */
@media (max-width: 1199px) {
  .game-page {
    grid-template-columns: 260px minmax(0, 1fr);
  }
  .clue-board {
    display: none;
  }
  .mobile-clue-bar {
    position: fixed;
    z-index: 46;
    display: block;
  }
  .mobile-clue-btn {
    display: flex;
    box-sizing: border-box;
    height: 42px;
    margin: 0;
    padding: 0 14px;
    border: 0;
    border-radius: var(--hgt-radius-full);
    align-items: center;
    justify-content: center;
    background: var(--hgt-brand);
    color: var(--hgt-on-brand);
    font-size: 12px;
    font-weight: 600;
    line-height: 1;
    box-shadow: var(--hgt-shadow-float);
    white-space: nowrap;
  }
  .mobile-clue-btn::after { border: 0; }
  .mobile-clue-sheet {
    position: fixed;
    z-index: 50;
    inset: 0;
    display: block;
    pointer-events: none;
  }
  .mobile-clue-panel {
    position: fixed;
    display: flex;
    box-sizing: border-box;
    width: min(320px, calc(100vw - 24px));
    max-height: min(360px, 50vh);
    border: 1px solid var(--hgt-border);
    border-radius: var(--hgt-radius-lg);
    flex-direction: column;
    background: var(--hgt-card);
    box-shadow: var(--hgt-shadow-float);
    overflow: hidden;
    pointer-events: auto;
  }
  .mobile-clue-head {
    display: flex;
    padding: 12px 14px;
    border-bottom: 1px solid var(--hgt-border);
    align-items: center;
    justify-content: space-between;
    background: var(--hgt-card-2);
    touch-action: none;
    cursor: grab;
    user-select: none;
  }
  .mobile-clue-panel .clue-tabs {
    border-bottom: 1px solid var(--hgt-border);
  }
  .mobile-clue-title {
    color: var(--hgt-text);
    font-family: var(--hgt-font-display);
    font-size: 15px;
    font-weight: 600;
  }
  .mobile-clue-close {
    display: flex;
    width: 30px;
    height: 30px;
    margin: 0;
    padding: 0;
    border: 1px solid var(--hgt-border);
    border-radius: var(--hgt-radius-sm);
    align-items: center;
    justify-content: center;
    background: transparent;
    color: var(--hgt-text-2);
    font-size: 16px;
    line-height: 1;
  }
  .mobile-clue-close::after { border: 0; }
  .mobile-clue-body {
    max-height: 220px;
    pointer-events: auto;
  }
  .mobile-clue-panel .btn-submit-truth {
    margin: 0;
    border-radius: 0;
    flex: none;
  }
}

/* ===== Mobile ===== */
@media (max-width: 767px) {
  .game-page {
    height: calc(100vh - var(--hgt-mobile-header-offset, 56px) - 64px - env(safe-area-inset-bottom));
    height: calc(100dvh - var(--hgt-mobile-header-offset, 56px) - 64px - env(safe-area-inset-bottom));
    grid-template-columns: 1fr;
  }
  .puzzle-panel,
  .clue-board {
    display: none;
  }
  .mobile-puzzle-summary {
    display: block;
    max-height: 42%;
    overflow-y: auto;
    border-bottom: 1px solid var(--hgt-border);
    background: var(--hgt-card);
  }
  .mobile-puzzle-row {
    display: flex;
    height: 48px;
    padding: 0 14px;
    align-items: center;
    gap: 12px;
  }
  .mobile-help,
  .mobile-expand {
    display: flex;
    width: 30px;
    height: 30px;
    margin: 0;
    padding: 0;
    border: 1px solid var(--hgt-border);
    border-radius: var(--hgt-radius-xs);
    align-items: center;
    justify-content: center;
    background: transparent;
    color: var(--hgt-text);
  }
  .mobile-help::after,
  .mobile-expand::after { border: 0; }
  .mobile-puzzle-title {
    overflow: hidden;
    flex: 1;
    color: var(--hgt-text);
    font-family: var(--hgt-font-display);
    font-size: 17px;
    font-weight: 600;
    text-overflow: ellipsis;
    white-space: nowrap;
  }
  .mobile-question-count {
    color: var(--hgt-text-2);
    font-size: 12px;
  }
  .mobile-surface-wrap {
    position: relative;
    padding: 8px 48px 12px 16px;
    border-top: 1px solid var(--hgt-border);
  }
  .mobile-surface {
    display: -webkit-box;
    padding: 0;
    border: 0;
    overflow: hidden;
    color: var(--hgt-text-2);
    font-size: 13px;
    line-height: 1.6;
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 3;
  }
  .mobile-surface.expanded {
    display: block;
    overflow: visible;
  }
  .mobile-surface-wrap .mobile-expand {
    position: absolute;
    right: 12px;
    bottom: 12px;
  }
  .mobile-team {
    padding: 0 16px;
    border-top: 1px solid var(--hgt-border);
  }
  .mobile-team-toggle {
    display: flex;
    width: 100%;
    height: 40px;
    margin: 0;
    padding: 0;
    border: 0;
    align-items: center;
    gap: 12px;
    background: transparent;
    color: var(--hgt-text);
  }
  .mobile-team-toggle::after { border: 0; }
  .mobile-team-toggle .section-row {
    flex: 1;
  }
  .mobile-team-details {
    padding: 0 0 12px;
  }
  .mobile-clue-bar {
    position: fixed;
    z-index: 46;
    display: block;
  }
  .mobile-clue-btn {
    display: flex;
    box-sizing: border-box;
    height: 42px;
    margin: 0;
    padding: 0 14px;
    border: 0;
    border-radius: var(--hgt-radius-full);
    align-items: center;
    justify-content: center;
    background: var(--hgt-brand);
    color: var(--hgt-on-brand);
    font-size: 12px;
    font-weight: 600;
    line-height: 1;
    box-shadow: var(--hgt-shadow-float);
    white-space: nowrap;
  }
  .mobile-clue-btn::after { border: 0; }
  .mobile-clue-sheet {
    position: fixed;
    z-index: 50;
    inset: 0;
    display: block;
    pointer-events: none;
  }
  .mobile-clue-panel {
    position: fixed;
    display: flex;
    box-sizing: border-box;
    width: min(320px, calc(100vw - 24px));
    max-height: min(360px, 50vh);
    border: 1px solid var(--hgt-border);
    border-radius: var(--hgt-radius-lg);
    flex-direction: column;
    background: var(--hgt-card);
    box-shadow: var(--hgt-shadow-float);
    overflow: hidden;
    pointer-events: auto;
  }
  .mobile-clue-head {
    display: flex;
    padding: 12px 14px;
    border-bottom: 1px solid var(--hgt-border);
    align-items: center;
    justify-content: space-between;
    background: var(--hgt-card-2);
    touch-action: none;
    cursor: grab;
    user-select: none;
  }
  .mobile-clue-panel .clue-tabs {
    border-bottom: 1px solid var(--hgt-border);
  }
  .mobile-clue-title {
    color: var(--hgt-text);
    font-family: var(--hgt-font-display);
    font-size: 15px;
    font-weight: 600;
  }
  .mobile-clue-close {
    display: flex;
    width: 30px;
    height: 30px;
    margin: 0;
    padding: 0;
    border: 1px solid var(--hgt-border);
    border-radius: var(--hgt-radius-sm);
    align-items: center;
    justify-content: center;
    background: transparent;
    color: var(--hgt-text-2);
    font-size: 16px;
    line-height: 1;
  }
  .mobile-clue-close::after { border: 0; }
  .mobile-clue-body {
    max-height: 220px;
    pointer-events: auto;
  }
  .mobile-clue-panel .btn-submit-truth {
    margin: 0;
    border-radius: 0;
    flex: none;
  }
  .chat-panel {
    position: absolute;
    z-index: 4;
    right: 0;
    bottom: 0;
    left: 0;
    height: var(--mobile-chat-height);
    min-height: 0;
    box-shadow: 0 -12px 32px rgba(0, 0, 0, 0.2);
  }
  .chat-resize-handle {
    cursor: ns-resize;
    touch-action: none;
    user-select: none;
  }
  .mobile-chat-dragbar {
    position: relative;
    display: flex;
    height: 22px;
    flex: none;
    align-items: center;
    justify-content: center;
    border-bottom: 1px solid var(--hgt-border);
    background: var(--hgt-card);
    color: var(--hgt-text-3);
  }
  .mobile-chat-dragbar text {
    font-size: 10px;
    letter-spacing: 0.12em;
  }
  .mobile-chat-dragbar .chat-grip {
    top: 4px;
  }
  .message {
    max-width: 88%;
  }
  .input-row button {
    width: 80px;
  }
  .mobile-action-fab {
    position: fixed;
    z-index: 40;
    display: block;
    overflow: visible;
  }
  .mobile-action-fab.open {
    /* 锚点仍是触发钮位置，胶囊向左伸出，开合不改动 left/top */
    transform: translateX(calc(-100% + 44px));
    border-radius: var(--hgt-radius-full);
  }
  .mobile-action-pill {
    display: flex;
    box-sizing: border-box;
    max-width: calc(100vw - 16px);
    height: 48px;
    padding: 6px;
    border: 1px solid var(--hgt-border);
    border-radius: var(--hgt-radius-full);
    gap: 4px;
    flex-direction: row;
    align-items: center;
    background: var(--hgt-bg-deep);
    box-shadow: var(--hgt-shadow-float);
    white-space: nowrap;
  }
  .mobile-fab-trigger {
    display: flex;
    box-sizing: border-box;
    width: 44px;
    height: 44px;
    margin: 0;
    padding: 0;
    border: 0;
    border-radius: 50%;
    align-items: center;
    justify-content: center;
    background: var(--hgt-bg-deep);
    border: 1px solid var(--hgt-border);
    color: var(--hgt-text);
    line-height: 1;
    box-shadow: var(--hgt-shadow-float);
  }
  .mobile-fab-trigger::after { border: 0; }
  .mobile-fab-option {
    display: flex;
    height: 36px;
    margin: 0;
    padding: 0 12px;
    border: 0;
    border-radius: var(--hgt-radius-full);
    align-items: center;
    justify-content: center;
    background: transparent;
    color: var(--hgt-text);
    font-size: 12px;
    font-weight: 600;
    line-height: 1;
  }
  .mobile-fab-option::after { border: 0; }
  .mobile-fab-option.danger {
    color: var(--hgt-danger);
  }
  .mobile-fab-option.leave {
    color: var(--hgt-text-2);
  }
  .mobile-fab-option.close {
    width: 36px;
    padding: 0;
    color: var(--hgt-text-2);
    font-size: 18px;
    font-weight: 400;
  }
  .mobile-fab-option.clue {
    color: var(--hgt-brand);
    background: var(--hgt-brand-soft);
  }
  .mobile-fab-option.clue.active {
    background: var(--hgt-brand);
    color: var(--hgt-on-brand);
  }
}

/* #ifdef MP-WEIXIN */
@media (max-width: 767px) {
  .game-page {
    height: calc(100vh - var(--hgt-mobile-header-offset, 56px) - 64px - env(safe-area-inset-bottom));
    height: calc(100dvh - var(--hgt-mobile-header-offset, 56px) - 64px - env(safe-area-inset-bottom));
  }
  .mobile-action-fab {
    z-index: 100;
  }
}
/* #endif */
/* #ifdef MP-TOUTIAO */
@media (max-width: 767px) {
  .game-page {
    height: calc(100vh - 64px - env(safe-area-inset-bottom));
    height: calc(100dvh - 64px - env(safe-area-inset-bottom));
  }
  .mobile-action-fab {
    z-index: 100;
  }
}
/* #endif */
</style>
