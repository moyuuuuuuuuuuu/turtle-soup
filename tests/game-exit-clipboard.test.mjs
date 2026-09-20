import assert from 'node:assert/strict'
import test from 'node:test'
import { loadModule } from './helpers/runtime.mjs'

function roomExit(room) {
  const calls = []
  const roomApi = {
    read: async (id) => {
      calls.push(['read', id])
      return room
    },
    close: async (id) => { calls.push(['close', id]) },
    leave: async (id) => { calls.push(['leave', id]) },
  }
  return { ...loadModule('src/utils/abandonedRoom.ts', { '@/api/turtle': { roomApi } }), calls, roomApi }
}
const abandoned = { status: 'abandoned', mode: 'multiplayer', room_id: 'room-1' }

test('owner returning after abandonment closes the room, members only leave', async () => {
  for (const isOwner of [true, false]) {
    const runtime = roomExit({ id: 'room-1', status: 'finished', is_owner: isOwner })
    assert.equal(await runtime.exitAbandonedRoom(abandoned), true)
    assert.deepEqual(runtime.calls, [['read', 'room-1'], [isOwner ? 'close' : 'leave', 'room-1']])
  }
})

test('single player, ongoing games and solved games do not close rooms', async () => {
  const runtime = roomExit({})
  for (const game of [null, { ...abandoned, mode: 'single' }, { ...abandoned, status: 'playing' }, { ...abandoned, status: 'solved' }])
    assert.equal(await runtime.exitAbandonedRoom(game), false)
  assert.equal(runtime.calls.length, 0)
})

test('closed rooms require no second close, while close failures propagate for retry', async () => {
  const runtime = roomExit({ id: 'room-1', status: 'closed', is_owner: true })
  await runtime.exitAbandonedRoom(abandoned)
  assert.equal(runtime.calls.length, 1)
  runtime.roomApi.read = async () => ({ id: 'room-1', status: 'finished', is_owner: true })
  runtime.roomApi.close = async () => {
    throw new Error('offline')
  }
  await assert.rejects(runtime.exitAbandonedRoom(abandoned), /offline/)
})

test('clipboard success and denial settle so the invitation can display feedback', async () => {
  let callbacks
  const runtime = loadModule('src/utils/clipboard.ts', {}, {
    uni: {
      setClipboardData: (options) => {
        callbacks = options
      },
    },
  })
  const success = runtime.copyText('https://example.com/invite')
  assert.equal(callbacks.data, 'https://example.com/invite')
  assert.equal(callbacks.showToast, false)
  callbacks.success()
  await success
  const failure = runtime.copyText('https://example.com/invite')
  callbacks.fail()
  await assert.rejects(failure, /clipboard.failed/)
})

test('route leave, cached-page hide and unmount clear every game overlay', () => {
  for (const lifecycle of ['route', 'deactivated', 'unmount']) {
    const hooks = {}
    const refs = Object.fromEntries(['pageActive', 'resultOpen', 'inviteOpen', 'confirmOpen', 'notesDrawerOpen', 'notesPanelFloating', 'mobileSurfaceOpen'].map(name => [name, { value: true }]))
    loadModule('src/pages/game/index.vue', {}, {
      ...refs,
      pageVersion: 0,
      confirmAction: () => {},
      route: { path: '/pages/game/index' },
      watch: (_source, callback) => { hooks.route = () => callback('/pages/questions/index') },
      onDeactivated: (callback) => { hooks.deactivated = callback },
      onBeforeUnmount: (callback) => { hooks.unmount = callback },
    }, source => source.slice(source.indexOf('function closePageOverlays()'), source.indexOf('\nonShow(() =>')))
    hooks[lifecycle]()
    for (const ref of Object.values(refs))
      assert.equal(ref.value, false)
  }
})
