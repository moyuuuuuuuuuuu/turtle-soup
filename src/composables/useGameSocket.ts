import type { GameSnapshot } from '@/types/game'
import { ensureAnonymousSession } from '@/api/turtle'

interface PendingRequest {
  resolve: (value: GameSnapshot) => void
  reject: (reason: Error) => void
}

const wsUrl = import.meta.env.VITE_WS_BASE_URL || 'ws://hgt.test:8790'
const connected = ref(false)
const reconnecting = ref(false)
const pending = new Map<string, PendingRequest>()
let socket: UniApp.SocketTask | null = null
let connecting: Promise<void> | null = null
let heartbeat: ReturnType<typeof setInterval> | null = null
let attempts = 0

function createRequestId() {
  return `${Date.now()}-${Math.random().toString(36).slice(2)}`
}

function stopHeartbeat() {
  if (heartbeat)
    clearInterval(heartbeat)
  heartbeat = null
}

function failPending(reason: Error) {
  pending.forEach(job => job.reject(reason))
  pending.clear()
}

export function useGameSocket() {
  async function connect() {
    if (connected.value)
      return
    if (connecting)
      return connecting

    const token = await ensureAnonymousSession()
    connecting = new Promise<void>((resolve, reject) => {
      const authRequestId = createRequestId()
      socket = uni.connectSocket({ url: wsUrl, complete: () => {} })

      socket.onOpen(() => {
        socket?.send({ data: JSON.stringify({ event: 'v1.auth', request_id: authRequestId, data: { token } }) })
      })
      socket.onMessage(({ data }) => {
        let message: { event?: string, request_id?: string, data?: GameSnapshot & { code?: string } }
        try {
          message = JSON.parse(String(data))
        }
        catch {
          return
        }
        if (message.event === 'v1.authenticated' && message.request_id === authRequestId) {
          attempts = 0
          connected.value = true
          heartbeat = setInterval(() => {
            socket?.send({ data: JSON.stringify({ event: 'v1.ping', request_id: createRequestId(), data: {} }) })
          }, 25000)
          resolve()
          return
        }
        if (!message.request_id)
          return
        const job = pending.get(message.request_id)
        if (!job)
          return
        pending.delete(message.request_id)
        if (message.event === 'v1.game.error')
          job.reject(new Error(message.data?.code || 'system.error'))
        else
          job.resolve(message.data as GameSnapshot)
      })
      socket.onClose(() => {
        stopHeartbeat()
        connected.value = false
        connecting = null
        failPending(new Error('websocket.disconnected'))
        reconnect()
      })
      socket.onError(() => {
        connected.value = false
        connecting = null
        reject(new Error('websocket.disconnected'))
      })
    }).finally(() => {
      connecting = null
    })
    return connecting
  }

  function reconnect() {
    if (reconnecting.value)
      return
    reconnecting.value = true
    const delay = Math.min(1000 * 2 ** attempts++, 15000)
    setTimeout(async () => {
      reconnecting.value = false
      try {
        await connect()
      }
      catch {}
    }, delay)
  }

  async function send(event: string, data: Record<string, unknown>) {
    await connect()
    const request_id = createRequestId()
    return new Promise<GameSnapshot>((resolve, reject) => {
      pending.set(request_id, { resolve, reject })
      socket?.send({
        data: JSON.stringify({ event, request_id, data }),
        fail: () => {
          pending.delete(request_id)
          reject(new Error('websocket.disconnected'))
        },
      })
    })
  }

  return {
    connected,
    reconnecting,
    connect,
    join: (game_id: string) => send('v1.game.join', { game_id }),
    ask: (game_id: string, question: string) => send('v1.game.question', { game_id, question }),
    hint: (game_id: string, level: number) => send('v1.game.hint', { game_id, level }),
    guess: (game_id: string, guess: string) => send('v1.game.guess', { game_id, guess }),
  }
}
