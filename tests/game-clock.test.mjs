import assert from 'node:assert/strict'
import test from 'node:test'
import { effectScope, nextTick, ref, watch } from 'vue'
import { flushPromises, loadModule } from './helpers/runtime.mjs'

function clockRuntime() {
  const game = ref({ id: 'one', status: 'playing' })
  const gameId = ref('one')
  const active = ref(true)
  const scope = effectScope()
  const requests = []
  const timers = new Map()
  let timerId = 0
  const clock = scope.run(() => loadModule('src/pages/game/index.vue', {}, {
    ref,
    watch,
    game,
    gameId,
    pageActive: active,
    readonlyMode: ref(false),
    gameApi: { history: () => {} },
    readGameDuration: id => new Promise(resolve => requests.push({ id, resolve })),
    setInterval: (callback) => {
      timers.set(++timerId, callback)
      return timerId
    },
    clearInterval: id => timers.delete(id),
  }, source => `
    const elapsedSeconds = ref(null)
    let elapsedTimer
    let elapsedBase = Date.now()
    ${source.slice(source.indexOf('function startElapsedClock('), source.indexOf('watch(socket.gameSnapshot'))}
    export { elapsedSeconds }
  `))
  return { ...clock, game, gameId, active, scope, requests, timers }
}

test('ordinary snapshots do not restart timing; ended games freeze server duration', async () => {
  const runtime = clockRuntime()
  try {
    runtime.requests[0].resolve(53)
    await flushPromises()
    assert.equal(runtime.elapsedSeconds.value, 53)
    assert.equal(runtime.timers.size, 1)
    runtime.game.value = { id: 'one', status: 'playing', question_count: 2 }
    await nextTick()
    assert.equal(runtime.requests.length, 1)
    runtime.game.value = { id: 'one', status: 'solved' }
    await nextTick()
    assert.equal(runtime.timers.size, 0)
    runtime.requests[1].resolve(59)
    await flushPromises()
    assert.equal(runtime.elapsedSeconds.value, 59)
    assert.equal(runtime.timers.size, 0)
  }
  finally {
    runtime.scope.stop()
  }
})

test('late timing from a previous game is ignored and new games restore their own duration', async () => {
  const runtime = clockRuntime()
  try {
    runtime.game.value = { id: 'two', status: 'playing' }
    runtime.gameId.value = 'two'
    await nextTick()
    runtime.requests[0].resolve(53)
    await flushPromises()
    assert.equal(runtime.elapsedSeconds.value, null)
    runtime.requests[1].resolve(6)
    await flushPromises()
    assert.equal(runtime.elapsedSeconds.value, 6)
    runtime.active.value = false
    await nextTick()
    assert.equal(runtime.timers.size, 0)
  }
  finally {
    runtime.scope.stop()
  }
})
