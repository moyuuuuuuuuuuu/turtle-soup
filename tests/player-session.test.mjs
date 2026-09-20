import assert from 'node:assert/strict'
import test from 'node:test'
import { loadModule } from './helpers/runtime.mjs'

function setup() {
  const storage = new Map([['turtle_player_refresh_token', 'valid-refresh'], ['turtle_device_id', 'test-device']])
  let respond = options => options.fail()
  const player = loadModule('src/api/player.ts', {
    '@/utils/serviceError': loadModule('src/utils/serviceError.ts'),
    '@/config/endpoints': { resolveApiBaseUrl: () => '/api/v1' },
  }, {
    uni: {
      getStorageSync: key => storage.get(key),
      setStorageSync: (key, value) => storage.set(key, value),
      removeStorageSync: key => storage.delete(key),
      getSystemInfoSync: () => ({}),
      request: options => respond(options),
    },
  })
  return {
    ...player,
    storage,
    respond: (fn) => { respond = fn },
  }
}

test('network and server errors preserve the refresh token and a later restore succeeds', async () => {
  const runtime = setup()
  await assert.rejects(runtime.playerApi.restore(), /网络请求失败/)
  assert.equal(runtime.storage.get('turtle_player_refresh_token'), 'valid-refresh')
  runtime.respond(options => options.success({ data: { code: 'system.error', message: 'temporary failure' } }))
  await assert.rejects(runtime.playerApi.restore(), { code: 'system.error' })
  assert.equal(runtime.storage.get('turtle_player_refresh_token'), 'valid-refresh')
  runtime.respond(options => options.success({ data: { code: 'success', data: { access_token: 'access', refresh_token: 'rotated', user: { id: 'user-1' } } } }))
  const result = await runtime.playerApi.restore()
  assert.equal(result.user.id, 'user-1')
  assert.equal(runtime.currentAccessToken(), 'access')
  assert.equal(runtime.storage.get('turtle_player_refresh_token'), 'rotated')
})

test('only definitive refresh rejection clears credentials, regardless of localized message', async () => {
  for (const code of ['auth.token_invalid', 'auth.refresh_token_reused', 'auth.user_disabled']) {
    const runtime = setup()
    runtime.respond(options => options.success({ data: { code, message: 'localized message' } }))
    assert.equal(await runtime.playerApi.restore(), null)
    assert.equal(runtime.storage.has('turtle_player_refresh_token'), false)
  }
})

test('refresh transport errors propagate to authenticated API callers', async () => {
  const runtime = setup()
  runtime.respond((options) => {
    if (options.url.endsWith('/auth/token/refresh'))
      options.fail()
    else options.success({ data: { code: 'auth.token_invalid' } })
  })
  await assert.rejects(runtime.playerApi.me(), /网络请求失败/)
  assert.equal(runtime.storage.has('turtle_player_refresh_token'), true)
})

test('store remains retryable after initial restore failure and preserves an existing user', async () => {
  const runtime = setup()
  const storeModule = loadModule('src/store/playerStore.ts', {
    'pinia': { defineStore: (_id, create) => create },
    '@/api/player': runtime,
    '@/composables/useGameSocket': {},
    '@/store/gameStore': {},
  }, { ref: value => ({ value }) })
  const store = storeModule.usePlayerStore()
  await assert.rejects(store.restore())
  assert.equal(store.ready.value, false)
  store.accept({ user: { id: 'user-1' } })
  await assert.rejects(store.restore())
  assert.equal(store.user.value.id, 'user-1')
})
