import type { GameSnapshot, RoomSnapshot } from '@/types/game'
/* eslint-disable style/max-statements-per-line */
import { ensurePlayerAccessToken } from '@/api/player'
import { ensureAnonymousSession, roomApi } from '@/api/turtle'
import { resolveWebSocketUrl } from '@/config/endpoints'
import { GameSocketError } from '@/utils/serviceError'

interface PendingRequest { resolve: (value: unknown) => void, reject: (reason: Error) => void }
interface SocketEnvelope { event?: string, request_id?: string, data?: Record<string, unknown> }

const wsUrl = resolveWebSocketUrl()
const connected = ref(false)
const reconnecting = ref(false)
const gameSnapshot = shallowRef<GameSnapshot | null>(null)
const roomSnapshot = shallowRef<RoomSnapshot | null>(null)
const typingMembers = ref<Array<{ user_id: number, username: string, expiresAt: number }>>([])
const kickedRoomId = ref('')
const memberLeftNotice = shallowRef<{ room_id: string, user_id: number, username: string, reason: 'manual' | 'switch_question', nonce: number } | null>(null)
const roomNextStarted = shallowRef<{ room_id: string, question_id: string, game_id: string, nonce: number } | null>(null)
const gameNextStarted = shallowRef<{ room_id: string, question_id: string, game_id: string, nonce: number } | null>(null)
/** 多人房间线索板实时同步（仅 Socket，不落库） */
const roomClueBoard = shallowRef<{ room_id: string, clues: string[], user_id: number, username: string, nonce: number } | null>(null)
const pending = new Map<string, PendingRequest>()
let socket: UniApp.SocketTask | null = null
let connecting: Promise<void> | null = null
let heartbeat: ReturnType<typeof setInterval> | null = null
let reconnectTimer: ReturnType<typeof setTimeout> | null = null
let cancelConnect: (() => void) | null = null
let connectionGeneration = 0
let attempts = 0
let needsSnapshotRecovery = false
const maxReconnectAttempts = 5

const createRequestId = () => `${Date.now()}-${Math.random().toString(36).slice(2)}`
function stopHeartbeat() {
  if (heartbeat)
    clearInterval(heartbeat); heartbeat = null
}
function failPending(reason: Error) { pending.forEach(job => job.reject(reason)); pending.clear() }
function updateTyping(data: Record<string, unknown>) {
  const userId = Number(data.user_id)
  typingMembers.value = typingMembers.value.filter(item => item.user_id !== userId && item.expiresAt > Date.now())
  if (data.is_typing)
    typingMembers.value.push({ user_id: userId, username: String(data.username || '玩家'), expiresAt: Date.now() + Number(data.expires_in_ms || 4000) })
}

export function useGameSocket() {
  async function connect() {
    if (connecting)
      return connecting
    if (connected.value)
      return
    if (reconnectTimer) {
      clearTimeout(reconnectTimer)
      reconnectTimer = null
      reconnecting.value = false
    }
    const generation = connectionGeneration
    // Publish the shared promise before token restoration can yield.
    const connection = Promise.resolve().then(async () => {
      const token = await ensurePlayerAccessToken() || await ensureAnonymousSession()
      if (generation !== connectionGeneration)
        throw new Error('websocket.disconnected')
      return openSocket(token)
    }).then(async () => {
      await recoverSnapshots()
      if (generation !== connectionGeneration || !connected.value)
        throw new Error('websocket.disconnected')
      attempts = 0
    }).catch((error) => {
      if (generation === connectionGeneration) {
        cancelConnect?.()
        if (needsSnapshotRecovery)
          reconnect()
      }
      throw error
    }).finally(() => {
      if (connecting === connection)
        connecting = null
    })
    connecting = connection
    return connection
  }
  function openSocket(token: string) {
    return new Promise<void>((resolve, reject) => {
      const authRequestId = createRequestId()
      const currentSocket = uni.connectSocket({ url: wsUrl, complete: () => {} })
      socket = currentSocket
      let authenticated = false
      let authTimer: ReturnType<typeof setTimeout>
      const failConnection = (error: Error, close = true) => {
        clearTimeout(authTimer)
        reject(error)
        if (socket !== currentSocket)
          return
        needsSnapshotRecovery ||= authenticated
        socket = null
        connected.value = false
        cancelConnect = null
        stopHeartbeat()
        failPending(error)
        if (close)
          currentSocket.close({ code: 1000, reason: 'connection.failed' })
        if (needsSnapshotRecovery)
          reconnect()
      }
      authTimer = setTimeout(() => failConnection(new Error('websocket.timeout')), 15000)
      cancelConnect = () => failConnection(new Error('websocket.disconnected'))
      currentSocket.onOpen(() => {
        if (socket !== currentSocket)
          return
        currentSocket.send({
          data: JSON.stringify({ event: 'v1.auth', request_id: authRequestId, data: { token } }),
          fail: () => failConnection(new Error('websocket.disconnected')),
        })
      })
      currentSocket.onMessage(({ data }) => {
        if (socket !== currentSocket)
          return
        let message: SocketEnvelope
        try { message = JSON.parse(String(data)) }
        catch { return }
        if (message.request_id === authRequestId && !authenticated) {
          if (message.event === 'v1.game.error') {
            failConnection(new GameSocketError(String(message.data?.code || 'system.error')))
            return
          }
          if (message.event !== 'v1.authenticated')
            return
          clearTimeout(authTimer)
          authenticated = true
          connected.value = true
          heartbeat = setInterval(() => currentSocket.send({ data: JSON.stringify({ event: 'v1.ping', request_id: createRequestId(), data: {} }) }), 25000)
          resolve()
          return
        }
        if (message.event === 'v1.room.member.typing') { updateTyping(message.data || {}); return }
        if (message.event === 'v1.room.member.kicked') {
          const data = message.data || {}
          const selfId = roomSnapshot.value?.members.find(item => item.is_self)?.user_id
          if (Number(data.user_id) === selfId) {
            kickedRoomId.value = String(data.room_id || '')
            roomSnapshot.value = null
          }
        }
        if (message.event === 'v1.room.left') {
          const data = message.data || {}
          if (String(data.room_id || '') === roomSnapshot.value?.id)
            roomSnapshot.value = null
        }
        if (message.event === 'v1.room.member.left') {
          const data = message.data || {}
          memberLeftNotice.value = {
            room_id: String(data.room_id || ''),
            user_id: Number(data.user_id),
            username: String(data.username || '玩家'),
            reason: data.reason === 'switch_question' ? 'switch_question' : 'manual',
            nonce: Date.now(),
          }
        }
        if (message.event === 'v1.room.next.started') {
          const data = message.data || {}
          roomNextStarted.value = {
            room_id: String(data.room_id || ''),
            question_id: String(data.question_id || ''),
            game_id: String(data.game_id || ''),
            nonce: Date.now(),
          }
        }
        if (message.event === 'v1.game.next.started') {
          const data = message.data || {}
          gameNextStarted.value = {
            room_id: String(data.room_id || ''),
            question_id: String(data.question_id || ''),
            game_id: String(data.game_id || ''),
            nonce: Date.now(),
          }
        }
        // 与 v1.room.* 信封一致：{ event, request_id?, data }
        // 服务端广播他人线索板；本端发送也用同一 event（fire-and-forget，不持久化）
        if (message.event === 'v1.room.clue.sync') {
          const data = message.data || {}
          roomClueBoard.value = {
            room_id: String(data.room_id || ''),
            clues: Array.isArray(data.clues) ? data.clues.map(item => String(item)) : [],
            user_id: Number(data.user_id || 0),
            username: String(data.username || '队友'),
            nonce: Date.now(),
          }
        }
        if (message.event === 'v1.room.snapshot')
          roomSnapshot.value = message.data as unknown as RoomSnapshot
        if (['v1.game.snapshot', 'v1.game.answer', 'v1.game.solved', 'v1.game.finished'].includes(String(message.event)))
          gameSnapshot.value = message.data as unknown as GameSnapshot
        if (!message.request_id)
          return
        const job = pending.get(message.request_id)
        if (!job)
          return
        pending.delete(message.request_id)
        if (message.event === 'v1.game.error')
          job.reject(new GameSocketError(String(message.data?.code || 'system.error')))
        else job.resolve(message.data)
      })
      currentSocket.onClose(() => failConnection(new Error('websocket.disconnected'), false))
      currentSocket.onError(() => failConnection(new Error('websocket.disconnected')))
    })
  }
  async function recoverSnapshots() {
    if (!needsSnapshotRecovery)
      return
    const roomId = roomSnapshot.value?.id
    let gameId = gameSnapshot.value?.id
    if (roomId) {
      const room = await sendConnected<RoomSnapshot>('v1.room.join', { room_id: roomId }, true, 15000)
      gameId = room.game_id || gameId
    }
    if (gameId)
      await sendConnected<GameSnapshot>('v1.game.join', { game_id: gameId }, true, 15000)
    needsSnapshotRecovery = false
  }
  function reconnect() {
    if (reconnecting.value)
      return
    if (attempts >= maxReconnectAttempts) {
      const roomId = roomSnapshot.value?.id
      if (roomId)
        roomApi.leave(roomId).catch(() => {})
      roomSnapshot.value = null
      typingMembers.value = []
      return
    }
    reconnecting.value = true
    reconnectTimer = setTimeout(async () => {
      reconnectTimer = null
      reconnecting.value = false; try { await connect() }
      catch {}
    }, Math.min(1000 * 2 ** attempts++, 15000))
  }
  function disconnectAndClear() {
    connectionGeneration++
    attempts = 0
    needsSnapshotRecovery = false
    if (reconnectTimer)
      clearTimeout(reconnectTimer)
    reconnectTimer = null
    stopHeartbeat()
    failPending(new Error('websocket.disconnected'))
    const currentSocket = socket
    socket = null
    cancelConnect?.()
    cancelConnect = null
    connecting = null
    connected.value = false
    reconnecting.value = false
    gameSnapshot.value = null
    roomSnapshot.value = null
    typingMembers.value = []
    kickedRoomId.value = ''
    memberLeftNotice.value = null
    roomNextStarted.value = null
    gameNextStarted.value = null
    roomClueBoard.value = null
    currentSocket?.close({ code: 1000, reason: 'player.logout' })
  }
  async function send<T>(event: string, data: Record<string, unknown>, waitForResponse = true): Promise<T> {
    await connect()
    return sendConnected<T>(event, data, waitForResponse)
  }
  async function sendConnected<T>(event: string, data: Record<string, unknown>, waitForResponse = true, timeoutMs = 0): Promise<T> {
    const currentSocket = socket
    if (!currentSocket || !connected.value)
      throw new Error('websocket.disconnected')
    const request_id = createRequestId()
    if (!waitForResponse) {
      currentSocket.send({ data: JSON.stringify({ event, request_id, data }) })
      return undefined as T
    }
    return new Promise<T>((resolve, reject) => {
      const timer = timeoutMs > 0
        ? setTimeout(() => {
            pending.delete(request_id)
            reject(new Error('websocket.timeout'))
          }, timeoutMs)
        : undefined
      const cleanup = () => {
        if (timer)
          clearTimeout(timer)
        pending.delete(request_id)
      }
      pending.set(request_id, {
        resolve: (value) => { cleanup(); resolve(value as T) },
        reject: (error) => { cleanup(); reject(error) },
      })
      currentSocket.send({ data: JSON.stringify({ event, request_id, data }), fail: () => {
        pending.get(request_id)?.reject(new Error('websocket.disconnected'))
      } })
    })
  }
  return {
    connected,
    reconnecting,
    gameSnapshot,
    roomSnapshot,
    typingMembers,
    kickedRoomId,
    memberLeftNotice,
    roomNextStarted,
    gameNextStarted,
    roomClueBoard,
    connect,
    disconnectAndClear,
    join: (game_id: string) => send<GameSnapshot>('v1.game.join', { game_id }),
    ask: (game_id: string, question: string) => send<GameSnapshot>('v1.game.question', { game_id, question }),
    hint: (game_id: string, level: number) => send<GameSnapshot>('v1.game.hint', { game_id, level }),
    guess: (game_id: string, guess: string) => send<GameSnapshot>('v1.game.guess', { game_id, guess }),
    abandon: (game_id: string) => send<GameSnapshot>('v1.game.abandon', { game_id }),
    next: (game_id: string) => send<{ room_id: string, question_id: string, game_id: string }>('v1.game.next', { game_id }),
    roomJoin: (room_id: string) => send<RoomSnapshot>('v1.room.join', { room_id }),
    roomChat: (room_id: string, content: string) => send<RoomSnapshot>('v1.room.chat', { room_id, content }),
    roomReady: (room_id: string, ready: boolean) => send<RoomSnapshot>('v1.room.ready', { room_id, ready }),
    roomStart: (room_id: string) => send<RoomSnapshot>('v1.room.start', { room_id }),
    roomNext: (room_id: string) => send<{ room_id: string, question_id: string, game_id: string }>('v1.room.next', { room_id }),
    roomNextSync: (room_id: string) => send<{ room_id: string, question_id: string, game_id: string }>('v1.room.next.sync', { room_id }),
    roomLeave: (room_id: string, reason: 'manual' | 'switch_question' = 'manual') => send<void>('v1.room.leave', { room_id, reason }),
    roomMute: (room_id: string, user_id: number, muted: boolean) => send<RoomSnapshot>('v1.room.member.mute', { room_id, user_id, muted }),
    roomKick: (room_id: string, user_id: number) => send<void>('v1.room.member.kick', { room_id, user_id }),
    roomVisibility: (room_id: string, visibility: 'private' | 'public') => send<RoomSnapshot>('v1.room.visibility.update', { room_id, visibility }),
    typing: (room_id: string, active: boolean) => send<void>(active ? 'v1.room.typing.start' : 'v1.room.typing.stop', { room_id }, false),
    /** 线索板广播：与 typing 同级 fire-and-forget，服务端只转发不落库 */
    roomClueSync: (room_id: string, clues: string[]) => send<void>('v1.room.clue.sync', { room_id, clues }, false),
    adoptRoom: (room: RoomSnapshot) => { roomSnapshot.value = room },
    clearRoom: () => { roomSnapshot.value = null; typingMembers.value = []; roomClueBoard.value = null },
  }
}
