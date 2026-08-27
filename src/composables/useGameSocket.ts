import type { GameSnapshot, RoomSnapshot } from '@/types/game'
/* eslint-disable style/max-statements-per-line */
import { ensurePlayerAccessToken } from '@/api/player'
import { ensureAnonymousSession, roomApi } from '@/api/turtle'

interface PendingRequest { resolve: (value: unknown) => void, reject: (reason: Error) => void }
interface SocketEnvelope { event?: string, request_id?: string, data?: Record<string, unknown> }

const wsUrl = import.meta.env.VITE_WS_BASE_URL || 'ws://hgt.test:8790'
const connected = ref(false)
const reconnecting = ref(false)
const gameSnapshot = shallowRef<GameSnapshot | null>(null)
const roomSnapshot = shallowRef<RoomSnapshot | null>(null)
const typingMembers = ref<Array<{ user_id: number, username: string, expiresAt: number }>>([])
const kickedRoomId = ref('')
const pending = new Map<string, PendingRequest>()
let socket: UniApp.SocketTask | null = null
let connecting: Promise<void> | null = null
let heartbeat: ReturnType<typeof setInterval> | null = null
let attempts = 0
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
    if (connected.value)
      return
    if (connecting)
      return connecting
    const token = await ensurePlayerAccessToken() || await ensureAnonymousSession()
    connecting = new Promise<void>((resolve, reject) => {
      const authRequestId = createRequestId()
      socket = uni.connectSocket({ url: wsUrl, complete: () => {} })
      socket.onOpen(() => socket?.send({ data: JSON.stringify({ event: 'v1.auth', request_id: authRequestId, data: { token } }) }))
      socket.onMessage(({ data }) => {
        let message: SocketEnvelope
        try { message = JSON.parse(String(data)) }
        catch { return }
        if (message.event === 'v1.authenticated' && message.request_id === authRequestId) {
          attempts = 0; connected.value = true
          heartbeat = setInterval(() => socket?.send({ data: JSON.stringify({ event: 'v1.ping', request_id: createRequestId(), data: {} }) }), 25000)
          resolve(); return
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
          job.reject(new Error(String(message.data?.code || 'system.error')))
        else job.resolve(message.data)
      })
      socket.onClose(() => { stopHeartbeat(); connected.value = false; connecting = null; failPending(new Error('websocket.disconnected')); reconnect() })
      socket.onError(() => { connected.value = false; connecting = null; reject(new Error('websocket.disconnected')) })
    }).finally(() => { connecting = null })
    return connecting
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
    setTimeout(async () => {
      reconnecting.value = false; try { await connect() }
      catch {}
    }, Math.min(1000 * 2 ** attempts++, 15000))
  }
  async function send<T>(event: string, data: Record<string, unknown>, waitForResponse = true): Promise<T> {
    await connect()
    const request_id = createRequestId()
    if (!waitForResponse) {
      socket?.send({ data: JSON.stringify({ event, request_id, data }) })
      return undefined as T
    }
    return new Promise<T>((resolve, reject) => {
      pending.set(request_id, { resolve: value => resolve(value as T), reject })
      socket?.send({ data: JSON.stringify({ event, request_id, data }), fail: () => { pending.delete(request_id); reject(new Error('websocket.disconnected')) } })
    })
  }
  return {
    connected,
    reconnecting,
    gameSnapshot,
    roomSnapshot,
    typingMembers,
    kickedRoomId,
    connect,
    join: (game_id: string) => send<GameSnapshot>('v1.game.join', { game_id }),
    ask: (game_id: string, question: string) => send<GameSnapshot>('v1.game.question', { game_id, question }),
    hint: (game_id: string, level: number) => send<GameSnapshot>('v1.game.hint', { game_id, level }),
    guess: (game_id: string, guess: string) => send<GameSnapshot>('v1.game.guess', { game_id, guess }),
    abandon: (game_id: string) => send<GameSnapshot>('v1.game.abandon', { game_id }),
    roomJoin: (room_id: string) => send<RoomSnapshot>('v1.room.join', { room_id }),
    roomChat: (room_id: string, content: string) => send<RoomSnapshot>('v1.room.chat', { room_id, content }),
    roomReady: (room_id: string, ready: boolean) => send<RoomSnapshot>('v1.room.ready', { room_id, ready }),
    roomStart: (room_id: string) => send<RoomSnapshot>('v1.room.start', { room_id }),
    roomLeave: (room_id: string) => send<void>('v1.room.leave', { room_id }),
    roomMute: (room_id: string, user_id: number, muted: boolean) => send<RoomSnapshot>('v1.room.member.mute', { room_id, user_id, muted }),
    roomKick: (room_id: string, user_id: number) => send<void>('v1.room.member.kick', { room_id, user_id }),
    typing: (room_id: string, active: boolean) => send<void>(active ? 'v1.room.typing.start' : 'v1.room.typing.stop', { room_id }, false),
  }
}
