import assert from 'node:assert/strict'
import test from 'node:test'
import { createClock, flushPromises, loadModule } from './helpers/runtime.mjs'

function setup(ensureToken = async () => 'test-token') {
  const sockets = []
  const clock = createClock()
  const module = loadModule('src/composables/useGameSocket.ts', {
    '@/utils/serviceError': loadModule('src/utils/serviceError.ts'),
    '@/api/player': { ensurePlayerAccessToken: ensureToken },
    '@/api/turtle': { ensureAnonymousSession: async () => 'test-anonymous', roomApi: { leave: async () => {} } },
    '@/config/endpoints': { resolveWebSocketUrl: () => 'ws://test.invalid' },
  }, {
    ...clock,
    ref: value => ({ value }),
    shallowRef: value => ({ value }),
    uni: {
      connectSocket() {
        const socket = {
          sent: [],
          closed: false,
          send({ data }) { this.sent.push(JSON.parse(data)) },
          close() {
            this.closed = true
            this.closeHandler?.()
          },
          onOpen(fn) { this.open = fn },
          onMessage(fn) { this.message = fn },
          onClose(fn) { this.closeHandler = fn },
          onError(fn) { this.error = fn },
          reply(event, data = {}) {
            this.message({ data: JSON.stringify({ event, request_id: this.sent.at(-1).request_id, data }) })
          },
          authenticate() {
            this.open()
            this.reply('v1.authenticated')
          },
        }
        sockets.push(socket)
        return socket
      },
    },
  })
  return { api: module.useGameSocket(), sockets, clock }
}

async function connect(runtime) {
  const pending = runtime.api.connect()
  await flushPromises()
  runtime.sockets.at(-1).authenticate()
  await pending
}

test('reconnect restores room membership and the latest game before allowing commands', async () => {
  const runtime = setup()
  const { api, sockets, clock } = runtime
  await connect(runtime)
  api.adoptRoom({ id: 'room-1', game_id: 'old-game', members: [] })
  api.gameSnapshot.value = { id: 'old-game' }
  sockets[0].closeHandler()
  clock.run(1000)
  await flushPromises()
  const current = sockets[1]
  current.authenticate()
  await flushPromises()
  assert.equal(current.sent.at(-1).event, 'v1.room.join')
  const action = api.ask('new-game', 'question')
  await flushPromises()
  assert.equal(current.sent.at(-1).event, 'v1.room.join')
  current.reply('v1.room.snapshot', { id: 'room-1', game_id: 'new-game', members: [] })
  await flushPromises()
  assert.equal(current.sent.at(-1).event, 'v1.game.join')
  assert.equal(current.sent.at(-1).data.game_id, 'new-game')
  current.reply('v1.game.snapshot', { id: 'new-game', status: 'playing' })
  await flushPromises()
  assert.equal(api.gameSnapshot.value.id, 'new-game')
  assert.equal(current.sent.at(-1).event, 'v1.game.question')
  current.reply('v1.game.answer', { id: 'new-game' })
  await action
})

test('single-player reconnect requests a fresh game snapshot', async () => {
  const runtime = setup()
  await connect(runtime)
  runtime.api.gameSnapshot.value = { id: 'solo-game' }
  runtime.sockets[0].closeHandler()
  runtime.clock.run(1000)
  await flushPromises()
  runtime.sockets[1].authenticate()
  await flushPromises()
  assert.equal(runtime.sockets[1].sent.at(-1).event, 'v1.game.join')
  runtime.sockets[1].reply('v1.game.snapshot', { id: 'solo-game', status: 'solved' })
  await flushPromises()
  assert.equal(runtime.api.gameSnapshot.value.status, 'solved')
})

test('authentication rejection settles the connection and allows a retry', async () => {
  const runtime = setup()
  const result = assert.rejects(runtime.api.connect(), /auth.token_invalid/)
  await flushPromises()
  runtime.sockets[0].open()
  runtime.sockets[0].reply('v1.game.error', { code: 'auth.token_invalid' })
  await result
  assert.equal(runtime.sockets[0].closed, true)
  await connect(runtime)
  assert.equal(runtime.api.connected.value, true)
})

test('handshake timeout and early close reject instead of hanging', async () => {
  for (const failure of ['timeout', 'close']) {
    const runtime = setup()
    const result = assert.rejects(runtime.api.connect(), /websocket\.(timeout|disconnected)/)
    await flushPromises()
    if (failure === 'timeout')
      runtime.clock.run(15000)
    else runtime.sockets[0].closeHandler()
    await result
    assert.equal(runtime.api.connected.value, false)
  }
})

test('simultaneous calls share token restoration and exactly one connection', async () => {
  let restoreCount = 0
  const runtime = setup(async () => {
    restoreCount++
    return 'token'
  })
  const first = runtime.api.connect()
  const second = runtime.api.connect()
  await flushPromises()
  assert.equal(restoreCount, 1)
  assert.equal(runtime.sockets.length, 1)
  runtime.sockets[0].authenticate()
  await Promise.all([first, second])
  assert.equal(runtime.sockets[0].sent.length, 1)
})

test('logout cancels scheduled reconnect and ignores late events from the old socket', async () => {
  const runtime = setup()
  await connect(runtime)
  const old = runtime.sockets[0]
  old.closeHandler()
  runtime.api.disconnectAndClear()
  runtime.clock.run(1000)
  await flushPromises()
  assert.equal(runtime.sockets.length, 1)
  await connect(runtime)
  old.error()
  old.reply('v1.game.snapshot', { id: 'stale-game' })
  assert.equal(runtime.api.connected.value, true)
  assert.equal(runtime.api.gameSnapshot.value, null)
})

test('logout while restoring a token prevents opening a socket', async () => {
  let resolveToken
  const runtime = setup(() => new Promise((resolve) => {
    resolveToken = resolve
  }))
  const result = assert.rejects(runtime.api.connect(), /websocket.disconnected/)
  await flushPromises()
  runtime.api.disconnectAndClear()
  resolveToken('token')
  await result
  assert.equal(runtime.sockets.length, 0)
})

test('a snapshot timeout closes the incomplete recovery and schedules another attempt', async () => {
  const runtime = setup()
  await connect(runtime)
  runtime.api.gameSnapshot.value = { id: 'game-1' }
  runtime.sockets[0].closeHandler()
  runtime.clock.run(1000)
  await flushPromises()
  runtime.sockets[1].authenticate()
  await flushPromises()
  runtime.clock.run(15000)
  await flushPromises()
  assert.equal(runtime.sockets[1].closed, true)
  assert.equal(runtime.api.connected.value, false)
  runtime.clock.run(2000)
  await flushPromises()
  assert.equal(runtime.sockets.length, 3)
})
