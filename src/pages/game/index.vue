<script setup lang="ts">
/* eslint-disable style/max-statements-per-line */
import type { GameMessage } from '@/types/game'
import { gameApi, roomApi, TurtleApiError } from '@/api/turtle'
import { useGameSocket } from '@/composables/useGameSocket'
import { resolveShareUrl } from '@/config/endpoints'
import { useGameStore } from '@/store/gameStore'
import { usePlayerStore } from '@/store/playerStore'
import { resolveDepth } from '@/utils/depth'
import { formatDuration } from '@/utils/gameStatus'
import { resolveShellChromeMetrics } from '@/utils/navSafeArea'
import { supportsPublicRooms } from '@/utils/platform'
import { openQuestionDetail } from '@/utils/questionRoute'

definePage({ name: 'game', layout: 'tabbar', style: { 'navigationStyle': 'custom', 'mp-toutiao': { navigationStyle: 'default' } } })

const route = useRoute()
const router = useRouter()
const store = useGameStore()
const player = usePlayerStore()
const socket = useGameSocket()

/** 小程序沉浸布局：与 shell 同一套系统 px，避免 CSS 变量/100dvh/env 在抖音端失效 */
const shellChrome = ref(resolveShellChromeMetrics())
const gamePageStyle = computed(() => {
  // #ifdef MP
  const metrics = shellChrome.value
  const top = metrics.defaultNav ? 0 : metrics.offset
  const bottom = 64 + metrics.safeBottom
  return {
    // 小程序 slot 样式隔离会阻止 Shell 的 :deep 定位规则命中页面。
    // 由页面自身建立确定的视口高度，避免 auto 高度令 flex 正文坍缩为 0。
    position: 'fixed' as const,
    top: `${top}px`,
    right: '0px',
    bottom: `${bottom}px`,
    left: '0px',
    height: 'auto',
    minHeight: '0',
    maxHeight: 'none',
  }
  // #endif
  // #ifndef MP
  return {}
  // #endif
})

const question = ref('')
const teamMessage = ref('')
const tab = ref<'judge' | 'team'>('judge')
const inputMode = ref<'question' | 'bottom'>('question')
const clueTab = ref<'clues' | 'notes'>('clues')
const localNotes = ref('')
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

/** 线索/笔记右侧抽屉（默认关闭） */
const notesDrawerOpen = ref(false)
/** PC 线索笔记悬浮框；移动端仍用抽屉 */
const notesPanelFloating = ref(false)
/** 移动端汤面底部弹层 */
const mobileSurfaceOpen = ref(false)
const mobileSurfaceRef = ref<HTMLElement | { $el?: HTMLElement } | null>(null)
/** 移动端底栏横向滚动：左右箭头翻页，减少滑动误触 */
const mobileBarScrollLeft = ref(0)
const mobileBarCanLeft = ref(false)
const mobileBarCanRight = ref(false)
const mobileSurfaceOverflow = ref(false)
const surfaceExpanded = ref(false)

const errorMessage = ref('')
const pageError = ref('')
const unreadTeam = ref(0)
const judgeScrollTarget = ref('')
const teamScrollTarget = ref('')
/** 提问后、服务端回包前的本地玩家消息（乐观展示） */
const pendingPlayerMessage = shallowRef<GameMessage | null>(null)

const game = computed(() => store.current)
const displayMessages = computed<GameMessage[]>(() => {
  const base = game.value?.messages || []
  return pendingPlayerMessage.value ? [...base, pendingPlayerMessage.value] : base
})
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

/** 只读回放：history 以 mode=readonly / readonly=1 打开（避开 vue 自动导入的 readonlyMode） */
const readonlyMode = computed(() => {
  const mode = String(route.query.mode || '')
  const flag = route.query.readonly
  const flagText = Array.isArray(flag) ? String(flag[0] || '') : String(flag ?? '')
  return mode === 'readonly' || flagText === '1'
})

const depth = computed(() => resolveDepth(game.value?.difficulty))
const depthLabel = computed(() => depth.value.code)
const elapsedSeconds = ref(0)
let elapsedTimer: ReturnType<typeof setInterval> | undefined
let elapsedBase = Date.now()

const isMultiplayer = computed(() => game.value?.mode === 'multiplayer')
const showCustomClues = computed(() => Boolean(isMultiplayer.value && room.value?.id))
const discoveredClues = computed(() => game.value?.discovered_points || [])
const clueCount = computed(() => discoveredClues.value.length + (showCustomClues.value ? customClues.value.length : 0))
const canInput = computed(() => !readonlyMode.value && game.value && !['solved', 'finished', 'abandoned'].includes(game.value.status))
const showTruthStage = computed(() => inputMode.value === 'bottom' && !readonlyMode.value)
/** 固定提示槽位 1–3 */
const HINT_MAX = 3
const hintUsed = computed(() => (game.value?.used_hints || []).filter(level => level >= 1 && level <= HINT_MAX).length)
const hintLeft = computed(() => Math.max(0, HINT_MAX - hintUsed.value))
const hintLabel = computed(() => `${hintUsed.value}/${HINT_MAX}`)
const statLine = computed(() => {
  if (!game.value)
    return ''
  return `已提问 ${game.value.question_count} 次 · 已进行 ${formatDuration(elapsedSeconds.value)}`
})

const mobilePuzzleExpanded = ref(false)

function judgeMessageKey(message: GameMessage, index: number) {
  if (pendingPlayerMessage.value === message)
    return 'pending-player-msg'
  return `m-${message.sequence}-${message.role}-${index}`
}

const riskTypeLabels: Record<string, string> = { death: '死亡', violence: '暴力', gore: '血腥', self_harm: '自伤', sexual: '性内容', child_safety: '未成年人', discrimination: '歧视', illegal: '违法', substance: '成瘾物', other: '其他' }
const riskTypeLabel = (value: string) => riskTypeLabels[value] || value
const judgeMessageId = (sequence: number) => `judge-message-${sequence}`
const teamMessageId = (sequence: number) => `team-message-${sequence}`

interface HostAnswerView {
  keyword: string
  color: string
  explanation: string
}

/** 展示用判定色；codes 对应后端 metadata.answer 稳定英文码 */
const ANSWER_TONES: Array<{ label: string, color: string, codes: string[] }> = [
  { label: '是', color: '#5EC4B8', codes: ['yes'] },
  { label: '不是', color: '#D05A52', codes: ['no'] },
  { label: '无关', color: '#7A9EB0', codes: ['irrelevant', 'unrelated'] },
  { label: '还差一点', color: '#C9A46A', codes: ['almost', 'close', 'partial'] },
]

function normalizeAnswerToken(raw: string) {
  return raw.trim().toLowerCase().replace(/[\s_-]+/g, '')
}

function matchAnswerTone(raw: string): { label: string, color: string } | undefined {
  const text = String(raw || '').trim()
  if (!text)
    return undefined
  return ANSWER_TONES.find((item) => {
    if (text === item.label || text.startsWith(item.label))
      return true
    const token = normalizeAnswerToken(text)
    return item.codes.some(code => token === code || token.startsWith(code))
  })
}

/**
 * 主持人气泡：metadata.answer 是结构化判定码，content 是面向玩家的说明。
 * 判定码只映射为中文关键词，正文优先展示 content，避免把 yes/no/irrelevant 直接露出。
 */
function parseHostAnswer(message: GameMessage): HostAnswerView | null {
  if (message.role !== 'host')
    return null
  const content = String(message.content || '').trim()
  const meta = (message.metadata || {}) as Record<string, unknown>
  const metaRaw = meta.answer
  const metaText = metaRaw === undefined || metaRaw === null ? '' : String(metaRaw).trim()

  const toneFromMeta = metaText ? matchAnswerTone(metaText) : undefined
  if (toneFromMeta) {
    let explanation = content
    if (!explanation && metaText.startsWith(toneFromMeta.label))
      explanation = metaText.slice(toneFromMeta.label.length).trim()
    return { keyword: toneFromMeta.label, color: toneFromMeta.color, explanation }
  }

  // meta 有值但不是已知判定码：不把英文码当正文
  if (metaText) {
    const toneFromContent = content ? matchAnswerTone(content) : undefined
    if (toneFromContent && (content === toneFromContent.label || content.startsWith(toneFromContent.label))) {
      const rest = content.slice(toneFromContent.label.length).trim()
      return { keyword: toneFromContent.label, color: toneFromContent.color, explanation: rest }
    }
    return { keyword: '', color: '', explanation: content }
  }

  if (!content)
    return null

  const toneFromContent = matchAnswerTone(content)
  if (toneFromContent && (content === toneFromContent.label || content.startsWith(toneFromContent.label))) {
    return {
      keyword: toneFromContent.label,
      color: toneFromContent.color,
      explanation: content === toneFromContent.label ? '' : content.slice(toneFromContent.label.length).trim(),
    }
  }

  // 自由文本（提示、开场等）：原样展示，不编造关键词
  return { keyword: '', color: '', explanation: content }
}

function roomSharePath() {
  if (!room.value)
    return '/pages/index/index'
  const questionId = room.value.question_id || game.value?.question_id || ''
  const query = [`invite_code=${encodeURIComponent(room.value.invite_code)}`]
  if (questionId)
    query.push(`question_id=${encodeURIComponent(questionId)}`)
  return `/pages/rooms/index?${query.join('&')}`
}
const roomShareTitle = computed(() => room.value ? `加入「${room.value.name}」一起玩海龟汤` : 'MOYUU 海龟汤')

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

function startElapsedClock(fromSeconds = 0) {
  elapsedBase = Date.now() - Math.max(0, fromSeconds) * 1000
  elapsedSeconds.value = Math.max(0, Math.floor((Date.now() - elapsedBase) / 1000))
  if (elapsedTimer)
    clearInterval(elapsedTimer)
  elapsedTimer = setInterval(() => {
    elapsedSeconds.value = Math.floor((Date.now() - elapsedBase) / 1000)
  }, 1000)
}

watch(socket.gameSnapshot, (value) => {
  if (!value)
    return
  const expectedGameId = switchingGameId || gameId.value
  if (value.id !== expectedGameId)
    return
  store.setGame(value)
  if (['solved', 'finished', 'abandoned'].includes(value.status))
    resultOpen.value = true
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
  notesDrawerOpen.value = false
  startElapsedClock(0)
  try {
    if (readonlyMode.value) {
      store.setGame(await gameApi.read(nextGameId))
    }
    else {
      store.setGame(await socket.join(nextGameId))
    }
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
  if (count > previous && tab.value !== 'team')
    unreadTeam.value += count - previous
})
watch(
  () => {
    const list = displayMessages.value
    const last = list[list.length - 1]
    return last ? `${list.length}-${last.sequence}-${last.role}` : ''
  },
  () => {
    const list = displayMessages.value
    const last = list[list.length - 1]
    if (last)
      void scrollMessagesTo(judgeScrollTarget, judgeMessageId(last.sequence))
  },
  { flush: 'post', immediate: true },
)
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
    if (readonlyMode.value) {
      store.setGame(await gameApi.read(gameId.value))
    }
    else {
      try { store.setGame(await socket.join(gameId.value)) }
      catch { store.setGame(await gameApi.read(gameId.value)) }
      if (game.value?.mode === 'multiplayer' && game.value.room_id)
        await socket.roomJoin(game.value.room_id)
    }
    startElapsedClock(0)
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
  const text = question.value.trim()
  if (!text || busy.value || !game.value)
    return
  const messages = game.value.messages || []
  const lastSequence = messages[messages.length - 1]?.sequence ?? 0
  const rawUserId = player.user?.id
  const userId = rawUserId != null && rawUserId !== '' && !Number.isNaN(Number(rawUserId))
    ? Number(rawUserId)
    : null
  // 先展示玩家提问并清空输入，再进入「主持人正在判断」
  pendingPlayerMessage.value = {
    sequence: lastSequence + 1,
    user_id: userId,
    username: player.user?.username || '我',
    avatar_url: player.user?.avatar_url ?? null,
    role: 'player',
    type: 'question',
    content: text,
  }
  question.value = ''
  busy.value = true
  errorMessage.value = ''
  try {
    const next = await socket.ask(game.value.id, text)
    pendingPlayerMessage.value = null
    store.setGame(next)
  }
  catch (error) {
    pendingPlayerMessage.value = null
    question.value = text
    errorMessage.value = (error as Error).message
    uni.showToast({ title: '判定失败，可原样重试', icon: 'none' })
  }
  finally {
    busy.value = false
  }
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

async function hint(level: number) {
  try { store.setGame(await socket.hint(game.value!.id, level)) }
  catch (error) { uni.showToast({ title: (error as Error).message, icon: 'none' }) }
}

function requestHint() {
  if (!game.value || busy.value || readonlyMode.value)
    return
  if (hintLeft.value <= 0) {
    uni.showToast({ title: '提示已用尽', icon: 'none' })
    return
  }
  const next = ([1, 2, 3] as const).find(level => !game.value!.used_hints.includes(level))
  if (next === undefined) {
    uni.showToast({ title: '没有更多提示了', icon: 'none' })
    return
  }
  void hint(next)
}

function openTruthStage() {
  if (!canInput.value)
    return
  inputMode.value = 'bottom'
  notesDrawerOpen.value = false
  mobileSurfaceOpen.value = false
}
function cancelTruthStage() {
  inputMode.value = 'question'
}

function openNotesDrawer(tabName: 'clues' | 'notes' = clueTab.value) {
  clueTab.value = tabName
  notesDrawerOpen.value = true
  // #ifdef H5
  notesPanelFloating.value = typeof window !== 'undefined'
    && window.matchMedia('(min-width: 900px)').matches
  // #endif
  // #ifndef H5
  notesPanelFloating.value = false
  // #endif
  mobileSurfaceOpen.value = false
}
function closeNotesDrawer() {
  notesDrawerOpen.value = false
  notesPanelFloating.value = false
}

function openSurfaceSheet() {
  mobileSurfaceOpen.value = true
  notesDrawerOpen.value = false
  surfaceExpanded.value = true
  nextTick(() => measureMobileSurface())
}
function closeSurfaceSheet() {
  mobileSurfaceOpen.value = false
}

async function sendTeam() {
  if (!teamMessage.value.trim() || !game.value?.room_id)
    return
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
  if (!game.value?.room_id)
    return
  void socket.typing(game.value.room_id, true).catch(() => {})
  if (typingTimer)
    clearTimeout(typingTimer)
  typingTimer = setTimeout(() => { void socket.typing(game.value!.room_id!, false).catch(() => {}) }, 1200)
}
function isTyping(userId: number) { return typingMembers.value.some(item => item.user_id === userId) }
function messageSender(message: { user_id?: number | null, username?: string | null, avatar_url?: string | null }) {
  const member = room.value?.members.find(item => item.user_id === message.user_id)
  return { username: message.username || member?.username || '玩家', avatar_url: message.avatar_url || member?.avatar_url }
}
async function toggleMute(member: { user_id: number, is_muted?: boolean }) {
  if (room.value)
    await socket.roomMute(room.value.id, member.user_id, !member.is_muted)
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
    try { await action() }
    catch (error) { uni.showToast({ title: (error as Error).message || '操作失败，请稍后重试', icon: 'none' }) }
  }
}
function cancelConfirmAction() {
  confirmAction = undefined
}

function kickMember(member: { user_id: number, username: string }) {
  if (!room.value)
    return
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
  if (!room.value)
    return
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

function exitGame() {
  if (isMultiplayer.value && room.value) {
    requestLeaveRoom()
    return
  }
  returnToQuestionLibrary()
}

function measureMobileSurface() {
  const value = mobileSurfaceRef.value
  const element = typeof HTMLElement !== 'undefined' && value instanceof HTMLElement
    ? value
    : (value as { $el?: HTMLElement } | null)?.$el
  mobileSurfaceOverflow.value = Boolean(element && element.scrollHeight > element.clientHeight + 1)
}

function onMobileBarScroll(event: { detail?: { scrollLeft?: number, scrollWidth?: number, clientWidth?: number } }) {
  const detail = event?.detail || {}
  const left = Number(detail.scrollLeft) || 0
  const scrollWidth = Number(detail.scrollWidth) || 0
  const clientWidth = Number(detail.clientWidth) || 0
  mobileBarScrollLeft.value = left
  mobileBarCanLeft.value = left > 2
  if (scrollWidth > 0 && clientWidth > 0)
    mobileBarCanRight.value = left + clientWidth < scrollWidth - 2
  else
    mobileBarCanRight.value = true
}

function scrollMobileBar(direction: -1 | 1) {
  const step = 160
  mobileBarScrollLeft.value = Math.max(0, mobileBarScrollLeft.value + direction * step)
  mobileBarCanLeft.value = mobileBarScrollLeft.value > 2
  // #ifdef H5
  nextTick(() => {
    if (typeof document === 'undefined')
      return
    const el = document.querySelector('.mobile-bar') as HTMLElement | null
    if (!el)
      return
    mobileBarCanRight.value = el.scrollLeft + el.clientWidth < el.scrollWidth - 2
  })
  // #endif
}

async function submitBottom() {
  if (!question.value.trim() || busy.value)
    return
  busy.value = true
  errorMessage.value = ''
  try {
    store.setGame(await socket.guess(game.value!.id, question.value))
    question.value = ''
    inputMode.value = 'question'
    resultOpen.value = true
  }
  catch (error) {
    errorMessage.value = (error as Error).message
    uni.showToast({ title: (error as Error).message, icon: 'none' })
  }
  finally { busy.value = false }
}

function submitJudgeInput() {
  if (readonlyMode.value || !canInput.value)
    return
  if (busy.value)
    return
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
  surfaceExpanded.value = false
  await nextTick()
  measureMobileSurface()
})

function onWindowResize() {
  shellChrome.value = resolveShellChromeMetrics()
  measureMobileSurface()
}

onMounted(async () => {
  shellChrome.value = resolveShellChromeMetrics()
  await player.restore()
  await refresh()
  surfaceExpanded.value = false
  await nextTick()
  measureMobileSurface()
  // 默认假定可向右滚，真实边界在首次 scroll / H5 测量后修正
  mobileBarCanLeft.value = false
  mobileBarCanRight.value = true
  uni.onWindowResize(onWindowResize)
})
onUnmounted(() => {
  if (typingTimer)
    clearTimeout(typingTimer)
  if (elapsedTimer)
    clearInterval(elapsedTimer)
  uni.offWindowResize(onWindowResize)
})
</script>

<template>
  <template v-if="game">
    <view class="game-page" :style="gamePageStyle">
      <!-- PC 隐藏中间导航条，保留站点 Logo 导航；移动端保留轻量顶栏 -->
      <header class="game-topbar">
        <text class="topbar-brand hgt-mono">
          {{ game.title }}
        </text>
        <text class="topbar-title hgt-display">
          DEPTH {{ depthLabel }}
        </text>
        <view class="topbar-actions">
          <button class="topbar-btn weak hgt-mono" @click="exitGame">
            退出
          </button>
        </view>
      </header>

      <!-- 移动端线索笔记只保留底部操作条入口；PC 在左侧面板打开 -->

      <view class="game-body">
        <!-- 左栏：谜题信息 -->
        <aside class="puzzle-panel">
          <button class="back-question hgt-mono" @click="backToQuestion">
            ← 返回题目
          </button>
          <text class="puzzle-kicker hgt-mono">
            谜题
          </text>
          <text class="hgt-display puzzle-title">
            {{ game.title }}
          </text>

          <view class="surface-block">
            <button class="surface-toggle hgt-mono" @click="surfaceExpanded = !surfaceExpanded">
              汤面
              <text>{{ surfaceExpanded ? '▴' : '▾' }}</text>
            </button>
            <text v-if="surfaceExpanded" class="surface" :class="{ expanded: surfaceExpanded }">
              {{ game.surface }}
            </text>
          </view>

          <view class="depth-row">
            <DepthBadge :difficulty="game.difficulty" />
          </view>

          <text class="stat-line hgt-mono">
            {{ statLine }}
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

          <view v-if="isMultiplayer && room" class="room-block">
            <view class="room-privacy-row">
              <view class="room-privacy-copy">
                <text class="hgt-mono">
                  私密房间
                </text>
                <text>{{ !supportsPublicRooms || room.visibility === 'private' ? '仅可通过邀请码加入' : '会展示在公开房间列表' }}</text>
              </view>
              <wd-switch
                v-if="supportsPublicRooms && room.is_owner"
                :model-value="room.visibility === 'private'"
                :loading="roomPrivacyUpdating"
                size="18"
                shape="square"
                active-color="var(--hgt-brand)"
                inactive-color="var(--hgt-border)"
                @change="updateRoomPrivacy"
              />
              <text v-else class="metadata-chip">
                {{ !supportsPublicRooms || room.visibility === 'private' ? '私密' : '公开' }}
              </text>
            </view>
            <view class="team-block">
              <view class="section-row">
                <text class="hgt-mono label">
                  队伍
                </text>
                <text class="hgt-mono label">
                  {{ room.member_count }}/{{ room.max_players }}
                </text>
              </view>
              <view v-for="member in sortedRoomMembers" :key="member.user_id" class="member">
                <view class="member-avatar-wrap">
                  <image v-if="member.avatar_url" :src="member.avatar_url" class="avatar" />
                  <view v-else class="avatar avatar-fallback">
                    {{ member.username.slice(0, 1) }}
                  </view>
                  <!-- #ifdef H5 -->
                  <text v-if="member.is_muted" class="member-muted-badge" aria-label="已禁言" title="已禁言">
                    <wd-icon name="mute" size="9px" />
                  </text>
                  <!-- #endif -->
                </view>
                <view class="member-info">
                  <text>{{ member.username }}</text>
                  <text v-if="isTyping(member.user_id)" class="typing hgt-mono">
                    正在输入中…
                  </text>
                </view>
                <text v-if="member.role === 'owner'" class="member-role hgt-mono">
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
          </view>

          <view class="panel-actions">
            <button class="hgt-mono tool-btn outline" @click="openNotesDrawer()">
              线索笔记
              <text v-if="clueCount" class="tool-badge">
                {{ clueCount }}
              </text>
            </button>
            <button
              v-if="player.user && (!room || room.member_count < room.max_players) && !readonlyMode"
              class="hgt-mono outline"
              :disabled="creatingRoom"
              @click="invite"
            >
              {{ creatingRoom ? '正在创建房间…' : '+ 邀请队友' }}
            </button>
            <button v-if="isMultiplayer && room" class="hgt-mono danger" @click="requestLeaveRoom">
              退出房间
            </button>
          </view>

          <button
            v-if="!readonlyMode && (game.mode === 'single' || room?.is_owner)"
            class="abandon-weak hgt-mono"
            @click="abandon"
          >
            放弃推理
          </button>
        </aside>

        <!-- 中栏：顶部可伸缩 + 底部输入固定 -->
        <main class="panel-center">
          <view class="center-stack">
            <view class="top-zone">
              <view class="mobile-puzzle" :class="{ expanded: mobilePuzzleExpanded }">
                <!-- 用 view 而非 button：uni-app button 高度易被压扁，展开后会盖住标题/收起 -->
                <view
                  class="mobile-puzzle-head"
                  hover-class="mobile-puzzle-head-hover"
                  @click="mobilePuzzleExpanded = !mobilePuzzleExpanded"
                >
                  <view class="mobile-puzzle-copy">
                    <text class="mobile-puzzle-title">
                      {{ game.title }}
                    </text>
                    <text class="mobile-puzzle-meta hgt-mono">
                      DEPTH {{ depthLabel }} · {{ statLine }}
                    </text>
                  </view>
                  <text class="mobile-puzzle-toggle hgt-mono">
                    {{ mobilePuzzleExpanded ? '收起 −' : '题目信息 +' }}
                  </text>
                </view>
                <!-- 题目限高并独立滚动，始终为对话和底部输入保留空间 -->
                <scroll-view v-if="mobilePuzzleExpanded" scroll-y class="mobile-puzzle-scroll">
                  <view class="mobile-puzzle-body">
                    <text class="mobile-puzzle-label hgt-mono">
                      汤面
                    </text>
                    <text class="mobile-puzzle-surface">
                      {{ game.surface }}
                    </text>
                    <DepthBadge :difficulty="game.difficulty" compact />
                    <text v-if="game.tags?.length" class="mobile-puzzle-tags">
                      {{ game.tags.map(tag => tag.name).join(' · ') }}
                    </text>
                  </view>
                </scroll-view>
              </view>

              <!-- 默认：主持人；多人房间时增加队伍讨论 Tab -->
              <view class="center-tabs">
                <button
                  :class="{ active: tab === 'judge' }"
                  @click="tab = 'judge'"
                >
                  {{ isMultiplayer ? '问答主持人' : '主持人' }}
                </button>
                <button
                  v-if="isMultiplayer"
                  :class="{ active: tab === 'team', unread: unreadTeam > 0 }"
                  @click="tab = 'team'"
                >
                  队伍讨论
                  <text v-if="unreadTeam" class="unread-badge">
                    {{ unreadTeam > 99 ? '99+' : unreadTeam }}
                  </text>
                </button>
              </view>

              <view v-if="readonlyMode" class="readonly-banner hgt-mono">
                回放记录 · 仅可查看
              </view>

              <view v-if="showTruthStage" class="truth-stage">
                <view class="truth-stage-inner">
                  <text class="truth-kicker hgt-mono">
                    SUBMIT TRUTH
                  </text>
                  <text class="truth-title hgt-display">
                    提交推理
                  </text>
                  <text class="truth-sub">
                    把你目前推理出的完整故事写下来。提交后，主持人会根据汤底判断你的推理。
                  </text>
                  <textarea
                    v-model="question"
                    class="truth-input"
                    :maxlength="2000"
                    placeholder="人物、事件与关键因果……"
                  />
                  <text class="truth-count hgt-mono">
                    {{ question.length }}/2000
                  </text>
                  <view v-if="errorMessage" class="stage-error">
                    {{ errorMessage }}
                  </view>
                  <view class="truth-actions">
                    <button class="btn-ghost" @click="cancelTruthStage">
                      返回继续提问
                    </button>
                    <button class="btn-brand" :disabled="!question.trim() || busy" @click="submitBottom">
                      {{ busy ? '判断中…' : '提交真相' }}
                    </button>
                  </view>
                  <text class="truth-note hgt-mono">
                    ◇ 不完整也没关系，你可以继续推理后再次提交（若规则允许）。
                  </text>
                </view>
              </view>

              <template v-else>
                <view v-if="isMultiplayer && tab === 'team' && !readonlyMode" class="chat-zone">
                  <scroll-view scroll-y :scroll-into-view="teamScrollTarget" scroll-with-animation class="stage">
                    <view class="stage-inner">
                      <view v-if="!(room?.messages || []).length" class="stage-empty">
                        <text class="empty-mark hgt-mono">
                          ◇
                        </text>
                        <text class="empty-title">
                          队伍讨论
                        </text>
                        <text class="empty-copy">
                          这里的消息仅队友可见，不会进入裁判判定。
                        </text>
                      </view>
                      <view
                        v-for="(message, messageIndex) in room?.messages || []"
                        :id="teamMessageId(message.sequence)"
                        :key="`${messageIndex}-${message.sequence}`"
                        class="msg-row team"
                      >
                        <text class="msg-author hgt-mono">
                          {{ message.username }}
                        </text>
                        <text class="msg-content">
                          {{ message.content }}
                        </text>
                      </view>
                    </view>
                  </scroll-view>
                </view>

                <view v-else class="chat-zone">
                  <!-- 提示灯泡：对话区左侧中部（黄框位置） -->
                  <button
                    v-if="!readonlyMode && canInput && !showTruthStage"
                    class="hint-float"
                    :disabled="busy || hintLeft <= 0"
                    :class="{ disabled: hintLeft <= 0 }"
                    @click="requestHint"
                  >
                    <text class="hint-float-icon" aria-hidden="true">
                      💡
                    </text>
                    <text class="hint-float-count hgt-mono">
                      {{ hintLabel }}
                    </text>
                  </button>
                  <scroll-view scroll-y :scroll-into-view="judgeScrollTarget" scroll-with-animation class="stage">
                    <view class="stage-inner">
                      <view v-if="!displayMessages.length" class="stage-empty">
                        <text class="empty-mark hgt-mono">
                          ◇
                        </text>
                        <text class="empty-host hgt-mono">
                          墨鱼主持人
                        </text>
                        <text class="empty-copy">
                          我已经知道这个故事的真相。你可以开始提问。我只会回答「是」「不是」或「无关」。
                        </text>
                      </view>
                      <view
                        v-for="(message, messageIndex) in displayMessages"
                        :id="judgeMessageId(message.sequence)"
                        :key="judgeMessageKey(message, messageIndex)"
                        class="msg-row"
                        :class="message.role"
                      >
                        <template v-if="message.role === 'host'">
                          <view class="host-bubble">
                            <text class="host-label hgt-mono">
                              ◇ 墨鱼主持人
                            </text>
                            <view class="host-answer">
                              <text
                                v-if="parseHostAnswer(message)?.keyword"
                                class="host-keyword"
                                :style="{ color: parseHostAnswer(message)!.color }"
                              >
                                {{ parseHostAnswer(message)!.keyword }}
                              </text>
                              <text v-if="parseHostAnswer(message)?.explanation" class="host-explain">
                                {{ parseHostAnswer(message)!.explanation }}
                              </text>
                            </view>
                          </view>
                        </template>
                        <template v-else>
                          <view class="player-card">
                            <text class="player-meta hgt-mono">
                              {{ messageSender(message).username }}
                            </text>
                            <text class="player-text">
                              {{ message.content }}
                            </text>
                          </view>
                        </template>
                      </view>
                      <view v-if="busy && !readonlyMode" class="waiting-row">
                        <text class="waiting-text hgt-mono">
                          主持人正在判断…
                          <text class="waiting-dots">
                            · · ·
                          </text>
                        </text>
                      </view>
                    </view>
                  </scroll-view>
                </view>
              </template>
            </view>
            <!-- /top-zone -->

            <view class="bottom-dock">
              <view v-if="!readonlyMode && !showTruthStage" class="mobile-bar-wrap">
                <button
                  class="mobile-bar-nav left"
                  :disabled="!mobileBarCanLeft"
                  aria-label="操作栏向左"
                  @click="scrollMobileBar(-1)"
                >
                  ‹
                </button>
                <scroll-view
                  scroll-x
                  class="mobile-bar"
                  :scroll-left="mobileBarScrollLeft"
                  @scroll="onMobileBarScroll"
                >
                  <view class="mobile-bar-track">
                    <button class="mobile-bar-btn" @click="openSurfaceSheet">
                      汤面
                    </button>
                    <button
                      v-if="canInput"
                      class="mobile-bar-btn"
                      :disabled="busy || hintLeft <= 0"
                      @click="requestHint"
                    >
                      提示 {{ hintLabel }}
                    </button>
                    <button class="mobile-bar-btn" @click="openNotesDrawer()">
                      线索笔记
                    </button>
                    <button v-if="canInput" class="mobile-bar-btn" @click="openTruthStage">
                      提交真相
                    </button>
                    <button
                      v-if="player.user && (!room || room.member_count < room.max_players) && !readonlyMode"
                      class="mobile-bar-btn"
                      :disabled="creatingRoom"
                      @click="invite"
                    >
                      {{ creatingRoom ? '创建中…' : '邀请队友' }}
                    </button>
                    <button
                      v-if="isMultiplayer && room"
                      class="mobile-bar-btn danger"
                      @click="requestLeaveRoom"
                    >
                      退出房间
                    </button>
                    <button
                      v-if="!readonlyMode && (game.mode === 'single' || room?.is_owner)"
                      class="mobile-bar-btn danger"
                      @click="abandon"
                    >
                      放弃推理
                    </button>
                    <button class="mobile-bar-btn weak" @click="returnToQuestionLibrary">
                      返回题库
                    </button>
                  </view>
                </scroll-view>
                <button
                  class="mobile-bar-nav right"
                  :disabled="!mobileBarCanRight"
                  aria-label="操作栏向右"
                  @click="scrollMobileBar(1)"
                >
                  ›
                </button>
              </view>

              <view v-if="isMultiplayer && tab === 'team' && !readonlyMode" class="composer">
                <text v-if="typingMembers.length" class="typing hgt-mono">
                  {{ typingMembers.map(item => item.username).join('、') }} 正在输入…
                </text>
                <view class="composer-inner">
                  <view class="input-row">
                    <input
                      v-model="teamMessage"
                      :disabled="room?.members.find(item => item.is_self)?.is_muted"
                      confirm-type="send"
                      :placeholder="room?.members.find(item => item.is_self)?.is_muted ? '你已被房主禁言' : '队伍内部讨论'"
                      @input="teamTyping"
                      @confirm="sendTeam"
                    >
                    <view
                      class="send-btn"
                      :class="{ disabled: room?.members.find(item => item.is_self)?.is_muted }"
                      @click="sendTeam"
                    >
                      发送
                    </view>
                  </view>
                </view>
              </view>
              <view v-else-if="!showTruthStage && !readonlyMode && canInput" class="composer">
                <view v-if="errorMessage" class="stage-error">
                  <text>主持人暂时没有回应</text>
                  <button class="retry-btn hgt-mono" @click="ask">
                    重新询问
                  </button>
                </view>
                <view class="composer-secondary">
                  <button class="secondary-btn hgt-mono" @click="openTruthStage">
                    我知道真相了 → 提交推理
                  </button>
                </view>
                <view class="composer-inner">
                  <view class="input-row">
                    <input
                      v-model="question"
                      :disabled="game.remaining_questions === 0"
                      confirm-type="send"
                      placeholder="向主持人提一个只能用「是 / 不是 / 无关」回答的问题……"
                      @confirm="submitJudgeInput"
                    >
                    <view
                      class="send-btn"
                      :class="{ disabled: game.remaining_questions === 0 || busy }"
                      @click="submitJudgeInput"
                    >
                      {{ busy ? '判断中…' : '提问' }}
                    </view>
                  </view>
                </view>
              </view>
              <view v-else-if="readonlyMode" class="composer readonly-foot">
                <text class="readonly-copy hgt-mono">
                  <template v-if="game.guess">
                    已提交：{{ game.guess.content }}
                  </template>
                  <template v-else>
                    汤底：{{ game.bottom || '未记录' }}
                  </template>
                </text>
                <button class="secondary-btn hgt-mono" @click="returnToQuestionLibrary">
                  返回题库
                </button>
              </view>
            </view>
          </view>
        </main>
      </view>

      <!-- 右侧线索/笔记：PC 悬浮框 / 移动端底部抽屉 -->
      <view
        v-if="notesDrawerOpen"
        class="drawer-mask"
        :class="{ 'is-floating': notesPanelFloating }"
        @click="closeNotesDrawer"
      >
        <view
          class="notes-drawer"
          :class="{ 'is-floating-panel': notesPanelFloating }"
          @click.stop
        >
          <view class="sheet-handle notes-handle" aria-hidden="true" />
          <view class="drawer-head">
            <text class="drawer-title hgt-display">
              线索笔记
            </text>
            <button class="drawer-close" aria-label="关闭" @click="closeNotesDrawer">
              ×
            </button>
          </view>
          <view class="clue-tabs">
            <button :class="{ active: clueTab === 'clues' }" @click="clueTab = 'clues'">
              线索
              <text class="tab-count">
                {{ clueCount }}
              </text>
            </button>
            <button :class="{ active: clueTab === 'notes' }" @click="clueTab = 'notes'">
              笔记
            </button>
          </view>
          <scroll-view scroll-y class="drawer-body">
            <template v-if="clueTab === 'clues'">
              <view v-if="!discoveredClues.length && !(showCustomClues && customClues.length)" class="clue-empty">
                还没有确认的线索，继续提问吧
              </view>
              <view v-for="(point, index) in discoveredClues" :key="`d-${index}`" class="clue-item found">
                <text class="clue-mark">
                  ◆
                </text>
                <text class="clue-text">
                  {{ point }}
                </text>
              </view>
              <template v-if="showCustomClues">
                <view v-for="(clue, index) in customClues" :key="`c-${index}`" class="clue-item custom">
                  <text class="clue-mark custom-mark">
                    ·
                  </text>
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
          <view v-if="!readonlyMode && canInput" class="drawer-foot">
            <button class="btn-brand drawer-truth" @click="openTruthStage">
              提交真相
            </button>
          </view>
        </view>
      </view>

      <!-- 移动端汤面弹层 -->
      <view v-if="mobileSurfaceOpen" class="sheet-mask" @click="closeSurfaceSheet">
        <view class="bottom-sheet" @click.stop>
          <view class="sheet-handle" />
          <view class="sheet-head">
            <text class="sheet-title hgt-display">
              汤面
            </text>
            <button class="drawer-close" @click="closeSurfaceSheet">
              ×
            </button>
          </view>
          <scroll-view scroll-y class="sheet-body">
            <text ref="mobileSurfaceRef" class="sheet-surface">
              {{ game.surface }}
            </text>
            <view class="sheet-meta">
              <DepthBadge :difficulty="game.difficulty" compact />
              <text class="stat-line hgt-mono">
                {{ statLine }}
              </text>
            </view>
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
            <template v-if="isMultiplayer && room">
              <view v-for="member in sortedRoomMembers" :key="member.user_id" class="member sheet-member">
                <view class="avatar avatar-fallback">
                  {{ member.username.slice(0, 1) }}
                </view>
                <text>{{ member.username }}</text>
                <text v-if="member.role === 'owner'" class="member-role hgt-mono">
                  房主
                </text>
              </view>
            </template>
          </scroll-view>
        </view>
      </view>
    </view>

    <wd-popup v-if="room && inviteOpen" v-model="inviteOpen" position="center" :root-portal="true" custom-class="invite-popup">
      <view class="invite-modal">
        <text class="hgt-mono label">
          邀请队友
        </text>
        <text class="hgt-display invite-heading">
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
          </view>
          <text>{{ member.username }}</text>
          <text class="member-role hgt-mono">
            {{ member.role === 'owner' ? '房主' : '在线' }}
          </text>
        </view>
        <button class="close-invite hgt-mono" @click="inviteOpen = false">
          关闭
        </button>
      </view>
    </wd-popup>

    <!-- 完成：安静的真相浮现 -->
    <wd-popup v-if="resultOpen" v-model="resultOpen" position="center" :close-on-click-modal="true" :root-portal="true" custom-class="result-popup">
      <view class="result-modal">
        <view class="result-content">
          <text class="result-kicker hgt-mono">
            TRUTH SURFACES
          </text>
          <text class="result-heading hgt-display">
            真相浮现
          </text>
          <text class="result-sub">
            汤底已揭开
          </text>
          <view class="result-stats hgt-mono">
            <text>提问 {{ game.question_count }}/{{ game.question_limit }}</text>
            <text>·</text>
            <text>{{ formatDuration(elapsedSeconds) }}</text>
            <text>·</text>
            <text>DEPTH {{ depthLabel }}</text>
          </view>
          <view class="result-bottom-panel">
            <text class="result-bottom-label hgt-mono">
              汤底
            </text>
            <text class="result-bottom">
              {{ game.bottom }}
            </text>
          </view>
          <view v-if="game.guess" class="result-guess">
            <text class="result-points-label hgt-mono">
              你的推理
            </text>
            <text class="result-guess-text">
              {{ game.guess.content }}
            </text>
            <text v-if="game.guess.summary" class="result-guess-summary">
              {{ game.guess.summary }}
            </text>
          </view>
          <view v-if="game.points?.length" class="result-points">
            <text class="result-points-label hgt-mono">
              关键推理点
            </text>
            <text v-for="point in game.points" :key="point.key" class="result-point">
              {{ point.content }}
            </text>
          </view>
          <view class="result-actions">
            <button class="btn-ghost-result" @click="returnToQuestionLibrary">
              返回题库
            </button>
            <button class="btn-primary-result" :disabled="busy" @click="continuePlaying">
              再来一题
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
  <view v-else-if="pageError" class="game-load-state" :style="gamePageStyle">
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
  <view v-else class="game-load-state" :style="gamePageStyle">
    <HgtLoading size="lg" text="" eyebrow />
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
  display: flex;
  box-sizing: border-box;
  width: 100%;
  height: calc(100vh - var(--hgt-header-h, 64px));
  height: calc(100dvh - var(--hgt-header-h, 64px));
  flex-direction: column;
  overflow: hidden;
  background: var(--hgt-bg);
  color: var(--hgt-text);
}

/* 全页只允许对话区内部滚动，避免多层滚动条 */
.game-page :deep(*),
.game-page * {
  scrollbar-width: thin;
  scrollbar-color: rgba(117, 220, 211, 0.25) transparent;
}

/* 移动端题目信息：默认收起条，可展开；拖拽时让出空间给对话区 */
.mobile-puzzle {
  display: none;
  box-sizing: border-box;
  flex: none;
  min-height: 0;
  max-height: 46%;
  overflow: hidden;
  border-bottom: 1px solid rgba(117, 220, 211, 0.12);
  background: rgba(4, 20, 24, 0.72);
  flex-direction: column;
}

.mobile-puzzle.expanded {
  height: 280px;
  max-height: min(52%, 280px);
}

.center-stack {
  display: flex;
  min-height: 0;
  flex: 1 1 0;
  flex-direction: column;
}

.top-zone {
  display: flex;
  width: 100%;
  min-height: 0;
  flex: 1;
  flex-direction: column;
  overflow: hidden;
}

.bottom-dock {
  position: relative;
  display: flex;
  flex: none;
  width: 100%;
  flex-direction: column;
  margin-top: auto;
  border-top: 1px solid rgba(117, 220, 211, 0.08);
  background: var(--hgt-bg-deep);
}

.chat-zone {
  position: relative;
  display: flex;
  box-sizing: border-box;
  width: 100%;
  min-height: 0;
  flex: 1 1 auto;
  flex-direction: column;
  overflow: hidden;
}

.mobile-puzzle-head {
  position: sticky;
  z-index: 2;
  top: 0;
  display: flex;
  box-sizing: border-box;
  width: 100%;
  min-height: 52px;
  margin: 0;
  padding: 10px 12px;
  border: 0;
  flex: none;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  background: rgba(4, 20, 24, 0.96);
  text-align: left;
}

.mobile-puzzle-head-hover {
  background: rgba(15, 53, 57, 0.55);
}

.mobile-puzzle-head::after { border: 0; }

.mobile-puzzle-copy {
  display: flex;
  min-width: 0;
  flex-direction: column;
  gap: 4px;
}

.mobile-puzzle-title {
  overflow: hidden;
  color: var(--hgt-text-bright);
  font-family: var(--hgt-font-display);
  font-size: 15px;
  font-weight: 600;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.mobile-puzzle-meta {
  color: var(--hgt-text-3);
  font-size: 11px;
}

.mobile-puzzle-toggle {
  flex: none;
  color: var(--hgt-brand);
  font-size: 11px;
  white-space: nowrap;
}

.mobile-puzzle-scroll {
  height: 0;
  min-height: 0;
  flex: 1 1 auto;
}

.mobile-puzzle-body {
  display: flex;
  box-sizing: border-box;
  padding: 0 12px 12px;
  gap: 8px;
  flex-direction: column;
}

.mobile-puzzle-label {
  color: var(--hgt-text-3);
  font-size: 10px;
  letter-spacing: 0.2em;
}

.mobile-puzzle-surface {
  color: var(--hgt-text-bright);
  font-family: var(--hgt-font-display);
  font-size: 14px;
  line-height: 1.8;
  white-space: pre-wrap;
}

.mobile-puzzle-tags {
  color: var(--hgt-text-3);
  font-size: 12px;
}

.hint-btn {
  gap: 6px;
}

.hint-icon {
  font-size: 13px;
  line-height: 1;
}

.hint-count {
  color: var(--hgt-brand);
  font-family: var(--hgt-font-mono);
  font-size: 12px;
}

.hint-left {
  color: var(--hgt-text-3);
  font-size: 11px;
}

/* ===== Notes drawer / PC floating ===== */
.drawer-mask {
  position: fixed;
  z-index: 80;
  inset: 0;
  background: rgba(3, 14, 18, 0.55);
}

.drawer-mask.is-floating {
  background: rgba(3, 14, 18, 0.18);
}

.notes-drawer {
  position: absolute;
  top: 0;
  right: 0;
  display: flex;
  box-sizing: border-box;
  width: min(320px, 88vw);
  height: 100%;
  border-left: 1px solid rgba(117, 220, 211, 0.12);
  flex-direction: column;
  background: #061A20;
  color: var(--hgt-text);
}

.notes-handle {
  display: none;
}

.notes-drawer.is-floating-panel,
.drawer-mask.is-floating .notes-drawer {
  position: fixed;
  top: calc(var(--hgt-header-h, 64px) + 16px);
  right: 16px;
  width: 300px;
  height: min(520px, calc(100vh - 120px));
  height: min(520px, calc(100dvh - 120px));
  border: 1px solid rgba(117, 220, 211, 0.18);
  border-radius: var(--hgt-radius-md);
  overflow: hidden;
  box-shadow: none;
  background: #061A20;
}

.notes-drawer.is-floating-panel .notes-handle,
.drawer-mask.is-floating .notes-handle {
  display: none;
}

.drawer-mask.is-floating {
  background: rgba(3, 14, 18, 0.12);
}

/* ===== Topbar：移动端与 PC 都保留，作为线索笔记等入口 ===== */
.game-topbar {
  display: grid;
  grid-template-columns: minmax(0, 1fr) auto minmax(0, 1fr);
  box-sizing: border-box;
  width: 100%;
  height: 48px;
  flex: none;
  padding: 0 12px;
  border-bottom: 1px solid rgba(117, 220, 211, 0.12);
  align-items: center;
  gap: 10px;
  background: var(--hgt-bg-deep);
}

.pc-float-notes {
  display: none;
  position: fixed;
  z-index: 70;
  top: calc(var(--hgt-header-h, 64px) + 16px);
  right: 16px;
  box-sizing: border-box;
  height: 36px;
  margin: 0;
  padding: 0 14px;
  border: 1px solid rgba(117, 220, 211, 0.22);
  border-radius: var(--hgt-radius-sm);
  align-items: center;
  gap: 6px;
  background: rgba(6, 26, 32, 0.88);
  color: var(--hgt-text);
  font-family: var(--hgt-font-mono);
  font-size: 12px;
  line-height: 1;
}

.pc-float-notes::after { border: 0; }

@media (min-width: 900px) {
  .game-topbar {
    display: grid !important;
  }
  .mobile-puzzle {
    display: none !important;
  }
  .center-tabs button {
    flex: 1 1 0;
    min-width: 0;
  }
}

@media (max-width: 899px) {
  .game-topbar {
    display: grid !important;
  }
  .mobile-puzzle {
    display: flex;
  }
  /* 题目名与收起在 mobile-puzzle-head；顶栏只保留局内状态 + 退出，避免双标题叠压 */
  .topbar-brand {
    display: none;
  }
  .game-topbar .topbar-title {
    display: block;
    overflow: hidden;
    flex: 1;
    min-width: 0;
    color: var(--hgt-text-2);
    font-family: var(--hgt-font-mono);
    font-size: 12px;
    font-weight: 400;
    letter-spacing: 0.08em;
    text-align: center;
    text-overflow: ellipsis;
    white-space: nowrap;
  }
}

.pc-float-badge {
  display: inline-flex;
  min-width: 16px;
  height: 16px;
  padding: 0 4px;
  border-radius: 999px;
  align-items: center;
  justify-content: center;
  background: var(--hgt-brand-soft);
  color: var(--hgt-brand);
  font-size: 10px;
}

.hint-float {
  position: absolute;
  z-index: 6;
  left: 10px;
  top: 58%;
  display: flex;
  box-sizing: border-box;
  width: 48px;
  height: 48px;
  margin: 0;
  padding: 0;
  border: 1px solid rgba(117, 220, 211, 0.22);
  border-radius: 50%;
  align-items: center;
  justify-content: center;
  flex-direction: column;
  gap: 2px;
  background: rgba(6, 26, 32, 0.92);
  color: var(--hgt-text-2);
}

.hint-float::after { border: 0; }

.hint-float-icon {
  font-size: 16px;
  line-height: 1;
}

.hint-float-count {
  color: var(--hgt-brand);
  font-size: 10px;
  line-height: 1;
}

.hint-float.disabled,
.hint-float[disabled] {
  opacity: 0.4;
}

.bottom-dock {
  position: relative;
  display: flex;
  flex: none;
  flex-direction: column;
  margin-top: auto;
  border-top: 1px solid rgba(117, 220, 211, 0.08);
  background: var(--hgt-bg-deep);
}
.topbar-brand {
  overflow: hidden;
  min-width: 0;
  text-overflow: ellipsis;
  flex: none;
  color: var(--hgt-text-2);
  font-size: 12px;
  letter-spacing: 0.12em;
  white-space: nowrap;
}
.topbar-title {
  grid-column: 2;
  overflow: hidden;
  flex: 1;
  min-width: 0;
  color: var(--hgt-text);
  font-family: var(--hgt-font-display);
  font-size: 15px;
  font-weight: 600;
  text-overflow: ellipsis;
  white-space: nowrap;
  text-align: center;
}
.topbar-depth {
  flex: none;
  color: var(--hgt-brand);
  font-size: 12px;
  letter-spacing: 0.14em;
  white-space: nowrap;
}
.topbar-actions {
  grid-column: 3;
  justify-self: end;
  display: flex;
  flex: none;
  align-items: center;
  gap: 8px;
}
.topbar-btn {
  display: flex;
  height: 32px;
  margin: 0;
  padding: 0 10px;
  border: 1px solid rgba(117, 220, 211, 0.12);
  border-radius: var(--hgt-radius-xs);
  align-items: center;
  gap: 6px;
  background: transparent;
  color: var(--hgt-text);
  font-size: 12px;
  line-height: 1;
}
.topbar-btn::after { border: 0; }
.topbar-btn.weak {
  border-color: transparent;
  color: var(--hgt-text-3);
}
.topbar-badge {
  display: flex;
  min-width: 16px;
  height: 16px;
  padding: 0 4px;
  border-radius: var(--hgt-radius-full);
  align-items: center;
  justify-content: center;
  background: var(--hgt-brand-soft);
  color: var(--hgt-brand);
  font-size: 10px;
}

/* ===== Body：内容向中间聚拢，左工具 + 中对话 ===== */
.game-body {
  display: grid;
  box-sizing: border-box;
  /* 小程序不支持 min() 时，auto 宽度与左右 auto 外边距会使内容区收缩。 */
  width: 100%;
  max-width: 1200px;
  min-height: 0;
  margin: 0 auto;
  flex: 1;
  overflow: hidden;
  grid-template-columns: 240px minmax(0, 1fr);
  grid-template-rows: minmax(0, 1fr);
}

/* ===== Left panel ===== */
.puzzle-panel {
  display: flex;
  box-sizing: border-box;
  min-height: 0;
  padding: 20px 14px;
  border-right: 1px solid rgba(117, 220, 211, 0.12);
  flex-direction: column;
  overflow-x: hidden;
  overflow-y: auto;
  background: rgba(4, 20, 24, 0.55);
  scrollbar-width: thin;
}
.back-question {
  display: flex;
  width: max-content;
  height: 28px;
  margin: 0 0 12px;
  padding: 0;
  border: 0;
  align-items: center;
  background: transparent;
  color: var(--hgt-text-3);
  font-size: 11px;
}
.back-question::after { border: 0; }
.puzzle-kicker {
  color: var(--hgt-brand);
  font-size: 11px;
  letter-spacing: 0.18em;
}
.puzzle-title {
  margin: 8px 0 12px;
  color: var(--hgt-text);
  font-family: var(--hgt-font-display);
  font-size: 18px;
  font-weight: 600;
  line-height: 1.35;
}
.surface-block {
  display: flex;
  margin-bottom: 12px;
  gap: 8px;
  flex-direction: column;
}
.surface-toggle {
  display: flex;
  width: 100%;
  height: 30px;
  margin: 0;
  padding: 0;
  border: 0;
  align-items: center;
  justify-content: space-between;
  background: transparent;
  color: var(--hgt-text-2);
  font-size: 12px;
}
.surface-toggle::after { border: 0; }
.surface {
  padding: 10px;
  border: 1px solid rgba(117, 220, 211, 0.12);
  border-radius: var(--hgt-radius-xs);
  background: rgba(15, 53, 57, 0.28);
  color: var(--hgt-text-bright);
  font-size: 12px;
  line-height: 1.7;
  white-space: pre-wrap;
}
.depth-row {
  margin-bottom: 10px;
}
.stat-line {
  margin-bottom: 14px;
  color: var(--hgt-text-3);
  font-size: 11px;
  letter-spacing: 0.04em;
  line-height: 1.5;
}
.label,
.metadata-label {
  color: var(--hgt-text-3);
  font-size: 11px;
  letter-spacing: 0.12em;
}
.puzzle-metadata {
  display: flex;
  padding: 10px 0;
  border-top: 1px solid rgba(117, 220, 211, 0.12);
  gap: 10px;
  flex-direction: column;
}
.metadata-group {
  display: flex;
  gap: 6px;
  flex-direction: column;
}
.metadata-items {
  display: flex;
  min-width: 0;
  flex-wrap: nowrap;
  gap: 6px;
  overflow-x: auto;
  overflow-y: hidden;
  -webkit-overflow-scrolling: touch;
  scrollbar-width: none;
}
.metadata-items::-webkit-scrollbar {
  display: none;
}
.metadata-chip {
  flex: none;
  padding: 3px 8px;
  border: 1px solid rgba(117, 220, 211, 0.12);
  border-radius: var(--hgt-radius-xs);
  color: var(--hgt-text-2);
  font-size: 11px;
  white-space: nowrap;
}
.risk-chip {
  border-color: rgba(201, 164, 106, 0.4);
  color: var(--hgt-gold);
}
.room-block {
  padding-top: 10px;
  border-top: 1px solid rgba(117, 220, 211, 0.12);
}
.room-privacy-row {
  display: flex;
  padding: 4px 0 8px;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
}
.room-privacy-copy {
  display: flex;
  min-width: 0;
  gap: 2px;
  flex-direction: column;
}
.room-privacy-copy > text:first-child {
  color: var(--hgt-text);
  font-size: 11px;
}
.room-privacy-copy > text:last-child {
  color: var(--hgt-text-3);
  font-size: 11px;
  line-height: 1.4;
}
.team-block {
  padding: 8px 0 4px;
}
.section-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
}
.member {
  display: flex;
  margin-top: 10px;
  align-items: center;
  gap: 8px;
  font-size: 12px;
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
  width: 28px;
  height: 28px;
  flex: none;
}
.avatar {
  width: 28px;
  height: 28px;
  border-radius: 50%;
}
.avatar-fallback {
  display: flex;
  align-items: center;
  justify-content: center;
  background: rgba(22, 62, 66, 0.5);
  color: var(--hgt-brand);
}
.member-muted-badge {
  position: absolute;
  right: -2px;
  bottom: -2px;
  display: flex;
  box-sizing: border-box;
  width: 14px;
  height: 14px;
  border: 2px solid var(--hgt-bg-deep);
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
  height: 22px;
  margin: 0;
  padding: 0 6px;
  border: 1px solid rgba(117, 220, 211, 0.12);
  border-radius: var(--hgt-radius-xs);
  align-items: center;
  background: transparent;
  color: var(--hgt-text-2);
  font-size: 10px;
}
.member-actions .kick {
  color: var(--hgt-danger);
}
.panel-actions {
  display: flex;
  margin-top: 12px;
  gap: 8px;
  flex-direction: column;
}
.outline,
.danger {
  display: flex;
  box-sizing: border-box;
  width: 100%;
  height: 36px;
  margin: 0;
  padding: 0 12px;
  border: 1px solid rgba(117, 220, 211, 0.12);
  border-radius: var(--hgt-radius-sm);
  align-items: center;
  justify-content: center;
  gap: 6px;
  line-height: 1;
  background: transparent;
  color: var(--hgt-text-2);
  font-size: 12px;
}
.outline::after,
.danger::after { border: 0; }
.danger {
  border-color: rgba(208, 90, 82, 0.35);
  color: var(--hgt-danger);
}
.tool-btn {
  position: relative;
  color: var(--hgt-text);
}
.tool-btn.disabled,
.tool-btn[disabled] {
  opacity: 0.45;
}
.tool-badge {
  display: inline-flex;
  min-width: 16px;
  height: 16px;
  padding: 0 4px;
  border-radius: var(--hgt-radius-full);
  align-items: center;
  justify-content: center;
  background: var(--hgt-brand-soft);
  color: var(--hgt-brand);
  font-size: 10px;
}
.abandon-weak {
  display: flex;
  box-sizing: border-box;
  width: 100%;
  height: 34px;
  margin: 10px 0 0;
  padding: 0 12px;
  border: 1px solid rgba(208, 90, 82, 0.55);
  border-radius: var(--hgt-radius-sm);
  align-items: center;
  justify-content: center;
  line-height: 1;
  background: transparent;
  color: var(--hgt-danger, #D05A52);
  font-size: 12px;
}
.abandon-weak::after { border: 0; }

/* ===== Center ritual ===== */
.panel-center {
  position: relative;
  display: flex;
  min-width: 0;
  min-height: 0;
  height: 100%;
  flex-direction: column;
  overflow: hidden;
  background: var(--hgt-bg);
}
.center-tabs {
  display: flex;
  box-sizing: border-box;
  width: 100%;
  flex: none;
  border-bottom: 1px solid rgba(117, 220, 211, 0.12);
  background: rgba(4, 20, 24, 0.4);
}
.center-tabs button {
  position: relative;
  display: flex;
  box-sizing: border-box;
  flex: 1 1 0;
  width: 100%;
  height: 40px;
  margin: 0;
  padding: 0;
  border: 0;
  align-items: center;
  justify-content: center;
  gap: 6px;
  background: transparent;
  color: var(--hgt-text-2);
  font-size: 13px;
}
.center-tabs button::after { border: 0; }
.center-tabs button.active {
  color: var(--hgt-brand);
}
.unread-badge {
  display: flex;
  min-width: 16px;
  height: 16px;
  padding: 0 4px;
  border-radius: var(--hgt-radius-full);
  align-items: center;
  justify-content: center;
  background: var(--hgt-brand-soft);
  color: var(--hgt-brand);
  font-size: 10px;
}
.readonly-banner {
  flex: none;
  padding: 8px 16px;
  border-bottom: 1px solid rgba(117, 220, 211, 0.08);
  background: rgba(4, 20, 24, 0.5);
  color: var(--hgt-text-3);
  font-size: 11px;
  letter-spacing: 0.12em;
  text-align: center;
}
.stage {
  box-sizing: border-box;
  width: 100%;
  height: 0;
  flex: 1;
  min-height: 0;
  padding: 20px 16px 12px;
  overflow-x: hidden !important;
  overflow-y: scroll !important;
  scrollbar-gutter: stable;
  /* 收窄系统滚动条，避免多层滚动条抢视觉 */
  scrollbar-width: thin;
  scrollbar-color: rgba(117, 220, 211, 0.28) transparent;
}
.stage::-webkit-scrollbar,
.chat-zone :deep(.uni-scroll-view)::-webkit-scrollbar {
  width: 6px;
  height: 0;
}
.stage::-webkit-scrollbar-thumb,
.chat-zone :deep(.uni-scroll-view)::-webkit-scrollbar-thumb {
  border-radius: 3px;
  background: rgba(117, 220, 211, 0.28);
}
.stage::-webkit-scrollbar-track,
.chat-zone :deep(.uni-scroll-view)::-webkit-scrollbar-track {
  background: transparent;
}
.stage-inner {
  box-sizing: border-box;
  width: 100%;
  max-width: 640px;
  min-height: 100%;
  margin: 0 auto;
  overflow-x: hidden;
}
.chat-zone :deep(.uni-scroll-view),
.chat-zone :deep(.uni-scroll-view-container),
.chat-zone :deep(.uni-scroll-view-content) {
  width: 100% !important;
  max-width: 100% !important;
  overflow-x: hidden !important;
}
.chat-zone :deep(.uni-scroll-view) {
  height: 100% !important;
  min-height: 0 !important;
  overflow-y: scroll !important;
  scrollbar-gutter: stable;
  scrollbar-width: thin;
}
.chat-zone :deep(.uni-scroll-view-content) {
  overflow: visible !important;
}
.stage-empty {
  display: flex;
  min-height: 240px;
  padding: 32px 12px;
  align-items: center;
  justify-content: center;
  gap: 10px;
  flex-direction: column;
  text-align: center;
}
.empty-mark {
  color: var(--hgt-brand);
  font-size: 22px;
}
.empty-host {
  color: var(--hgt-text-2);
  font-size: 12px;
  letter-spacing: 0.16em;
}
.empty-title {
  color: var(--hgt-text);
  font-family: var(--hgt-font-display);
  font-size: 18px;
}
.empty-copy {
  max-width: 420px;
  color: var(--hgt-text-2);
  font-size: 13px;
  line-height: 1.75;
}
.msg-row {
  margin-bottom: 16px;
}
.msg-row.host {
  display: flex;
  justify-content: flex-start;
}
.host-bubble {
  display: flex;
  box-sizing: border-box;
  width: fit-content;
  max-width: min(420px, 92%);
  padding: 12px 16px;
  border: 1px solid rgba(117, 220, 211, 0.16);
  border-radius: var(--hgt-radius-sm);
  gap: 8px;
  flex-direction: column;
  background: rgba(8, 28, 32, 0.55);
  text-align: left;
}
.host-label {
  display: block;
  color: var(--hgt-text-3);
  font-size: 10px;
  letter-spacing: 0.08em;
}
.host-answer {
  display: flex;
  gap: 6px 12px;
  flex-wrap: wrap;
  align-items: baseline;
  flex-direction: row;
}
.host-keyword {
  flex: none;
  font-family: var(--hgt-font-display);
  font-size: 28px;
  font-weight: 600;
  letter-spacing: 0.08em;
  line-height: 1.2;
}
.host-explain {
  min-width: 0;
  flex: 1 1 160px;
  color: var(--hgt-text-2);
  font-size: 14px;
  line-height: 1.75;
  opacity: 0.86;
  white-space: pre-wrap;
}
.msg-row.player {
  display: flex;
  justify-content: flex-end;
}
.player-card {
  display: flex;
  box-sizing: border-box;
  width: fit-content;
  max-width: min(360px, 88%);
  padding: 10px 14px;
  border: 1px solid rgba(117, 220, 211, 0.12);
  border-radius: var(--hgt-radius-sm);
  gap: 6px;
  flex-direction: column;
  background: rgba(15, 53, 57, 0.38);
  text-align: right;
}
.player-meta {
  color: var(--hgt-text-3);
  font-size: 10px;
  letter-spacing: 0.08em;
}
.player-text {
  color: var(--hgt-text);
  font-size: 14px;
  line-height: 1.65;
}
.msg-row.team {
  display: flex;
  gap: 4px;
  flex-direction: column;
}
.msg-author {
  color: var(--hgt-brand);
  font-size: 11px;
}
.msg-content {
  color: var(--hgt-text);
  font-size: 14px;
  line-height: 1.65;
}
.waiting-row {
  padding: 8px 0 16px;
}
.waiting-text {
  color: var(--hgt-text-3);
  font-size: 12px;
  letter-spacing: 0.08em;
}
.waiting-dots {
  margin-left: 4px;
  color: var(--hgt-brand);
}

/* Truth stage */
.truth-stage {
  display: flex;
  flex: 1;
  min-height: 0;
  padding: 24px 16px;
  box-sizing: border-box;
  overflow-y: auto;
  align-items: flex-start;
  justify-content: center;
}
.truth-stage-inner {
  display: flex;
  box-sizing: border-box;
  width: min(680px, 100%);
  padding: 28px 22px;
  border: 1px solid rgba(117, 220, 211, 0.12);
  border-radius: var(--hgt-radius-md);
  gap: 10px;
  flex-direction: column;
  background: rgba(4, 20, 24, 0.45);
}
.truth-kicker {
  color: var(--hgt-brand);
  font-size: 11px;
  letter-spacing: 0.28em;
}
.truth-title {
  color: var(--hgt-text);
  font-family: var(--hgt-font-display);
  font-size: 26px;
  font-weight: 600;
}
.truth-sub {
  color: var(--hgt-text-2);
  font-size: 13px;
  line-height: 1.7;
}
.truth-input {
  box-sizing: border-box;
  width: 100%;
  min-height: 160px;
  margin-top: 6px;
  padding: 12px;
  border: 1px solid rgba(117, 220, 211, 0.12);
  border-radius: var(--hgt-radius-sm);
  background: rgba(15, 53, 57, 0.28);
  color: var(--hgt-text);
  font-size: 14px;
  line-height: 1.65;
}
.truth-count {
  color: var(--hgt-text-3);
  font-size: 11px;
  text-align: right;
}
.truth-actions {
  display: flex;
  margin-top: 8px;
  gap: 10px;
  flex-wrap: wrap;
}
.btn-ghost,
.btn-brand {
  display: flex;
  box-sizing: border-box;
  min-width: 120px;
  height: 42px;
  margin: 0;
  padding: 0 18px;
  border-radius: var(--hgt-radius-sm);
  align-items: center;
  justify-content: center;
  font-size: 14px;
  font-weight: 600;
}
.btn-ghost {
  border: 1px solid rgba(117, 220, 211, 0.16);
  background: transparent;
  color: var(--hgt-text);
}
.btn-brand {
  border: 0;
  background: var(--hgt-brand);
  color: var(--hgt-on-brand);
}
.btn-ghost::after,
.btn-brand::after { border: 0; }
.btn-brand:disabled {
  opacity: 0.5;
}
.truth-note {
  color: var(--hgt-text-3);
  font-size: 11px;
  line-height: 1.6;
}

/* Composer */
.mobile-bar-wrap,
.mobile-bar {
  display: none;
}
.composer {
  flex: none;
  padding: 10px 16px 14px;
  border-top: 1px solid rgba(117, 220, 211, 0.12);
  background: rgba(4, 20, 24, 0.72);
}
.composer-inner {
  width: min(680px, 100%);
  margin: 0 auto;
}
.stage-error {
  display: flex;
  box-sizing: border-box;
  width: min(680px, 100%);
  margin: 0 auto 8px;
  padding: 8px 10px;
  border: 1px solid rgba(208, 90, 82, 0.35);
  border-radius: var(--hgt-radius-xs);
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  background: rgba(208, 90, 82, 0.1);
  color: var(--hgt-danger);
  font-size: 12px;
}
.retry-btn {
  display: flex;
  height: 26px;
  margin: 0;
  padding: 0 10px;
  border: 1px solid rgba(208, 90, 82, 0.4);
  border-radius: var(--hgt-radius-xs);
  align-items: center;
  background: transparent;
  color: var(--hgt-danger);
  font-size: 11px;
}
.retry-btn::after { border: 0; }
.input-row {
  display: flex;
  width: 100%;
  gap: 8px;
  align-items: stretch;
}
.input-row input {
  box-sizing: border-box;
  min-width: 0;
  flex: 1;
  height: 48px;
  padding: 0 14px;
  border: 1px solid rgba(117, 220, 211, 0.14);
  border-radius: var(--hgt-radius-sm);
  background: rgba(15, 53, 57, 0.35);
  color: var(--hgt-text);
  font-size: 14px;
}
/* 整块可点的发送按钮 */
.send-btn {
  display: flex;
  box-sizing: border-box;
  width: 92px;
  flex: none;
  height: 48px;
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
  line-height: 1;
  cursor: pointer;
  user-select: none;
  -webkit-tap-highlight-color: transparent;
}
.send-btn.disabled {
  opacity: 0.5;
  pointer-events: none;
}
.composer-secondary {
  display: flex;
  margin-bottom: 8px;
  gap: 14px;
  flex-wrap: wrap;
}
.secondary-btn {
  display: flex;
  height: 28px;
  margin: 0;
  padding: 0;
  border: 0;
  align-items: center;
  background: transparent;
  color: var(--hgt-text-2);
  font-size: 12px;
}
.secondary-btn::after { border: 0; }
.secondary-btn:disabled {
  opacity: 0.5;
}
.typing {
  display: block;
  width: min(680px, 100%);
  margin: 0 auto 6px;
  color: var(--hgt-text-3);
  font-size: 11px;
}
.readonly-foot {
  display: flex;
  width: min(680px, 100%);
  margin: 0 auto;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}
.readonly-copy {
  color: var(--hgt-text-2);
  font-size: 12px;
  line-height: 1.5;
}

/* ===== Notes drawer head ===== */
.drawer-head {
  display: flex;
  height: 56px;
  flex: none;
  padding: 0 14px;
  border-bottom: 1px solid rgba(117, 220, 211, 0.12);
  align-items: center;
  justify-content: space-between;
  background: #041418;
}
.drawer-title {
  font-family: var(--hgt-font-display);
  font-size: 16px;
  font-weight: 600;
}
.drawer-close {
  display: flex;
  flex: none;
  width: 30px;
  height: 30px;
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
.drawer-close::after { border: 0; }
.clue-tabs {
  display: flex;
  flex: none;
  border-bottom: 1px solid rgba(117, 220, 211, 0.12);
}
.clue-tabs button {
  position: relative;
  display: flex;
  box-sizing: border-box;
  flex: 1;
  height: 44px;
  margin: 0;
  padding: 0;
  border: 0;
  align-items: center;
  justify-content: center;
  gap: 6px;
  background: transparent;
  color: var(--hgt-text-2);
  font-size: 14px;
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
  background: var(--hgt-brand);
  content: '';
}
.tab-count {
  color: var(--hgt-brand);
  font-size: 11px;
}
.drawer-body {
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
  border: 1px solid rgba(117, 220, 211, 0.12);
  border-radius: var(--hgt-radius-sm);
  align-items: flex-start;
  gap: 8px;
  background: rgba(15, 53, 57, 0.28);
  font-size: 13px;
  line-height: 1.5;
}
.clue-item.found {
  border-color: rgba(94, 196, 184, 0.28);
}
.clue-mark {
  flex: none;
  color: var(--hgt-brand);
}
.custom-mark {
  color: var(--hgt-text-3);
}
.clue-text {
  flex: 1;
  color: var(--hgt-text);
}
.clue-remove {
  display: flex;
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
  border: 1px solid rgba(117, 220, 211, 0.12);
  border-radius: var(--hgt-radius-sm);
  background: rgba(4, 20, 24, 0.5);
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
.notes-area {
  box-sizing: border-box;
  width: 100%;
  min-height: 240px;
  padding: 12px;
  border: 1px solid rgba(117, 220, 211, 0.12);
  border-radius: var(--hgt-radius-sm);
  background: rgba(4, 20, 24, 0.45);
  color: var(--hgt-text);
  font-size: 13px;
  line-height: 1.65;
}
.drawer-foot {
  flex: none;
  padding: 12px 14px;
  border-top: 1px solid rgba(117, 220, 211, 0.12);
  background: #041418;
}
.drawer-truth {
  width: 100%;
}

/* ===== Bottom sheets ===== */
.sheet-mask {
  position: fixed;
  z-index: 70;
  inset: 0;
  display: flex;
  align-items: flex-end;
  background: rgba(3, 14, 18, 0.55);
}
.bottom-sheet {
  display: flex;
  box-sizing: border-box;
  width: 100%;
  max-height: min(72vh, calc(var(--hgt-viewport-h, 100dvh) - var(--hgt-shell-top, 56px) - var(--hgt-shell-bottom, 0px) - 16px));
  margin-bottom: var(--hgt-shell-bottom, 0px);
  padding: 8px 0 16px;
  border-top: 1px solid rgba(117, 220, 211, 0.12);
  border-radius: var(--hgt-radius-lg) var(--hgt-radius-lg) 0 0;
  flex-direction: column;
  background: #061A20;
}
.sheet-handle {
  width: 36px;
  height: 3px;
  margin: 4px auto 8px;
  border-radius: 2px;
  background: rgba(117, 220, 211, 0.2);
}
.sheet-head {
  display: flex;
  flex: none;
  padding: 4px 16px 10px;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}
.sheet-title {
  flex: 1;
  min-width: 0;
  color: var(--hgt-text);
  font-family: var(--hgt-font-display);
  font-size: 16px;
  font-weight: 600;
  white-space: nowrap;
}
.sheet-body {
  flex: 1;
  min-height: 0;
  max-height: calc(var(--hgt-viewport-h, 100dvh) - var(--hgt-shell-top, 56px) - var(--hgt-shell-bottom, 0px) - 120px);
  padding: 0 16px 12px;
  box-sizing: border-box;
  overflow-y: auto;
  -webkit-overflow-scrolling: touch;
}
.sheet-surface {
  display: block;
  margin-bottom: 14px;
  color: var(--hgt-text-bright);
  font-size: 14px;
  line-height: 1.75;
  white-space: pre-wrap;
}
.sheet-meta {
  display: flex;
  margin-bottom: 12px;
  gap: 12px;
  flex-direction: column;
}
.sheet-member {
  padding: 6px 0;
  border-top: 1px solid rgba(117, 220, 211, 0.08);
}

/* ===== Invite ===== */
.invite-modal {
  display: flex;
  box-sizing: border-box;
  width: 100%;
  padding: 28px;
  gap: 12px;
  flex-direction: column;
  background: #061A20;
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
  border: 1px dashed rgba(117, 220, 211, 0.2);
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
  border-bottom: 1px solid rgba(117, 220, 211, 0.12);
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

/* ===== Result · quiet ===== */
.result-modal {
  position: relative;
  display: flex;
  box-sizing: border-box;
  width: min(520px, calc(100vw - 32px));
  max-height: 86vh;
  border: 1px solid rgba(117, 220, 211, 0.12);
  border-radius: var(--hgt-radius-lg);
  overflow: hidden;
  flex-direction: column;
  background: #041418;
  color: var(--hgt-text);
}
.result-content {
  display: flex;
  padding: 28px 24px;
  gap: 12px;
  flex-direction: column;
  overflow-y: auto;
}
.result-kicker {
  color: var(--hgt-brand);
  font-size: 11px;
  letter-spacing: 0.28em;
}
.result-heading {
  color: var(--hgt-text);
  font-family: var(--hgt-font-display);
  font-size: 26px;
  font-weight: 600;
  letter-spacing: 0.06em;
}
.result-sub {
  color: var(--hgt-text-2);
  font-size: 13px;
}
.result-stats {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
  color: var(--hgt-text-3);
  font-size: 11px;
  letter-spacing: 0.04em;
}
.result-bottom-panel {
  display: flex;
  margin-top: 4px;
  padding: 16px;
  border: 1px solid rgba(117, 220, 211, 0.12);
  border-radius: var(--hgt-radius-md);
  gap: 8px;
  flex-direction: column;
  background: rgba(15, 53, 57, 0.28);
}
.result-bottom-label {
  color: var(--hgt-text-3);
  font-size: 11px;
  letter-spacing: 0.24em;
}
.result-bottom {
  color: var(--hgt-text-bright);
  font-family: var(--hgt-font-display);
  font-size: 15px;
  line-height: 1.85;
  white-space: pre-wrap;
}
.result-guess,
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
.result-guess-text,
.result-guess-summary,
.result-point {
  color: var(--hgt-text-2);
  font-size: 13px;
  line-height: 1.55;
}
.result-point {
  padding: 6px 0;
  border-bottom: 1px solid rgba(117, 220, 211, 0.08);
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
  border: 1px solid rgba(117, 220, 211, 0.16);
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

:deep(.invite-popup) {
  box-sizing: border-box;
  width: min(480px, calc(100vw - 32px));
  border: 1px solid rgba(117, 220, 211, 0.12);
  border-radius: var(--hgt-radius-lg);
  background: #061A20;
  color: var(--hgt-text);
  overflow: hidden;
}
:deep(.result-popup) {
  width: min(520px, calc(100vw - 32px));
  background: transparent;
  border: 0;
}
:deep(.result-popup) .result-modal {
  width: 100%;
}

/* ===== Load states ===== */
.game-load-state {
  display: flex;
  box-sizing: border-box;
  min-height: calc(var(--hgt-viewport-h, 100vh) - var(--hgt-shell-top, 56px) - var(--hgt-shell-bottom, 0px));
  min-height: calc(var(--hgt-viewport-h, 100dvh) - var(--hgt-shell-top, 56px) - var(--hgt-shell-bottom, 0px));
  padding: 48px 24px;
  align-items: center;
  justify-content: center;
  flex-direction: column;
  text-align: center;
  background: var(--hgt-bg);
}
.game-load-eyebrow {
  color: var(--hgt-text-3);
  font-size: 11px;
  letter-spacing: 0.22em;
}
.game-load-title {
  margin-top: 16px;
  color: var(--hgt-text);
  font-family: var(--hgt-font-display);
  font-size: 26px;
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

/* ===== Mobile-first responsive ===== */
@media (max-width: 899px) {
  /* ≤767px：高度由 shell 沉浸布局绝对定位撑满，此处不重复扣减 tabbar */
  .game-page {
    height: calc(var(--hgt-viewport-h, 100vh) - var(--hgt-shell-top, 56px) - var(--hgt-shell-bottom, 0px));
    height: calc(var(--hgt-viewport-h, 100dvh) - var(--hgt-shell-top, 56px) - var(--hgt-shell-bottom, 0px));
  }

  .game-body {
    display: flex;
    flex-direction: column;
    height: 0;
  }
  .puzzle-panel {
    display: none;
  }
  .panel-center {
    height: auto;
    flex: 1 1 0;
  }
  .center-stack {
    min-height: 0;
  }
  .topbar-depth {
    font-size: 11px;
  }
  .mobile-bar-wrap {
    display: flex;
    flex: none;
    align-items: stretch;
    border-top: 1px solid rgba(117, 220, 211, 0.12);
    background: rgba(4, 20, 24, 0.72);
  }
  .mobile-bar-nav {
    display: flex;
    box-sizing: border-box;
    width: 34px;
    flex: none;
    height: auto;
    min-height: 46px;
    margin: 0;
    padding: 0;
    border: 0;
    align-items: center;
    justify-content: center;
    background: rgba(4, 20, 24, 0.96);
    color: var(--hgt-text-2);
    font-size: 20px;
    line-height: 1;
  }
  .mobile-bar-nav::after { border: 0; }
  .mobile-bar-nav.left {
    border-right: 1px solid rgba(117, 220, 211, 0.1);
  }
  .mobile-bar-nav.right {
    border-left: 1px solid rgba(117, 220, 211, 0.1);
  }
  .mobile-bar-nav[disabled] {
    opacity: 0.28;
  }
  .mobile-bar {
    display: block;
    flex: 1;
    min-width: 0;
    white-space: nowrap;
    overflow: hidden;
  }
  .mobile-bar-track {
    display: inline-flex;
    box-sizing: border-box;
    width: max-content;
    padding: 6px 8px;
    gap: 6px;
    vertical-align: middle;
  }
  .mobile-bar-btn {
    display: inline-flex;
    box-sizing: border-box;
    width: max-content !important;
    max-width: none;
    flex: 0 0 auto;
    height: 34px;
    margin: 0;
    padding: 0 12px;
    border: 1px solid rgba(117, 220, 211, 0.12);
    border-radius: var(--hgt-radius-xs);
    align-items: center;
    justify-content: center;
    background: transparent;
    color: var(--hgt-text);
    font-size: 12px;
    line-height: 1;
    white-space: nowrap;
  }
  .mobile-bar-btn::after { border: 0; }
  .mobile-bar-btn.danger {
    color: var(--hgt-danger);
  }
  .mobile-bar-btn.weak {
    color: var(--hgt-text-3);
  }
  .mobile-bar-btn[disabled] {
    opacity: 0.45;
  }

  /* 移动端线索笔记改为底部抽屉，落在 tabbar 上方 */
  .notes-drawer:not(.is-floating-panel) {
    top: auto;
    right: 0;
    bottom: var(--hgt-shell-bottom, 0px);
    left: 0;
    width: 100%;
    height: auto;
    max-height: min(70vh, calc(var(--hgt-viewport-h, 100dvh) - var(--hgt-shell-top, 56px) - var(--hgt-shell-bottom, 0px) - 48px));
    border-left: 0;
    border-top: 1px solid rgba(117, 220, 211, 0.16);
    border-radius: var(--hgt-radius-lg) var(--hgt-radius-lg) 0 0;
  }
  .notes-drawer:not(.is-floating-panel) .notes-handle {
    display: block;
  }
  .notes-drawer:not(.is-floating-panel) .drawer-body {
    min-height: 160px;
    height: min(42vh, calc(var(--hgt-viewport-h, 100dvh) - var(--hgt-shell-top, 56px) - var(--hgt-shell-bottom, 0px) - 180px));
  }

  .bottom-sheet .sheet-body {
    height: min(48vh, calc(var(--hgt-viewport-h, 100dvh) - var(--hgt-shell-top, 56px) - var(--hgt-shell-bottom, 0px) - 140px));
  }
  .stage {
    padding: 18px 12px 8px;
  }
  .host-bubble {
    max-width: 92%;
    padding: 10px 12px;
  }
  .host-keyword {
    font-size: 22px;
  }
  .player-card {
    max-width: 92%;
  }
  /* safe-area 由 shell tabbar 自己吃掉，composer 底边贴齐内容区底边 */
  .composer {
    padding: 8px 10px 10px;
  }
  .input-row .send-btn {
    width: 80px;
    height: 44px;
  }
  .input-row input {
    height: 44px;
  }
  .composer-secondary {
    gap: 10px;
  }
}

@media (min-width: 900px) {
  .mobile-bar-wrap,
  .mobile-bar {
    display: none !important;
  }
  .hint-float {
    display: flex !important;
  }
}

@media (max-width: 899px) {
  .hint-float {
    display: none !important;
  }
}
</style>
