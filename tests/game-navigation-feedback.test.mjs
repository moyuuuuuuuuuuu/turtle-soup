import assert from 'node:assert/strict'
import test from 'node:test'
import { initPreContext, preJs } from '@dcloudio/uni-cli-shared/dist/preprocess/index.js'
import { loadModule } from './helpers/runtime.mjs'

function navigation(pages) {
  const calls = []
  const runtime = loadModule('src/utils/gameRoute.ts', {}, {
    getCurrentPages: () => pages,
    uni: {
      navigateBack: options => calls.push({ type: 'back', ...options }),
      redirectTo: options => calls.push({ type: 'replace', ...options }),
    },
  })
  return { ...runtime, calls }
}

test('return to room reuses the matching game page and coalesces repeated taps', async () => {
  const runtime = navigation([
    { route: 'pages/game/index', options: { id: 'old' } },
    { route: 'pages/game/index', options: { id: 'current' } },
    { route: 'pages/account/index' },
  ])
  const first = runtime.returnToGame('current')
  assert.equal(runtime.returnToGame('current'), first)
  assert.equal(runtime.calls.length, 1)
  assert.equal(runtime.calls[0].type, 'back')
  assert.equal(runtime.calls[0].delta, 1)
  runtime.calls[0].success()
  await first
})

test('missing game replaces the current page; failed navigation can be retried', async () => {
  const runtime = navigation([{ route: 'pages/account/index' }])
  const first = runtime.returnToGame('a/b')
  assert.equal(runtime.calls[0].type, 'replace')
  assert.equal(runtime.calls[0].url, '/pages/game/index?id=a%2Fb')
  runtime.calls[0].fail()
  await assert.rejects(first)
  const retry = runtime.returnToGame('a/b')
  runtime.calls[1].success()
  await retry
})

test('returning while already in the room does not navigate', async () => {
  const runtime = navigation([{ route: 'pages/game/index', options: { id: 'current' } }])
  await runtime.returnToGame('current')
  assert.equal(runtime.calls.length, 0)
})

function feedback() {
  const { useFeedbackStore } = loadModule('src/store/feedbackStore.ts', {
    pinia: {
      defineStore: (_id, options) => () => {
        const store = options.state()
        for (const [name, action] of Object.entries(options.actions))
          store[name] = action.bind(store)
        return store
      },
    },
  }, { setTimeout, clearTimeout })
  return useFeedbackStore()
}

test('visible page takes ownership of feedback, and hidden page cleanup cannot release it', async () => {
  const store = feedback()
  store.claimHost('home')
  store.claimHost('detail')
  store.releaseHost('home')
  assert.equal(store.hostId, 'detail')
  const confirmation = store.openConfirm({ title: 'Risk' })
  store.settleConfirm(true)
  store.settleConfirm(false)
  assert.equal(await confirmation, true)
})

test('leaving the confirmation page cancels pending work and returning can claim the host again', async () => {
  const store = feedback()
  store.claimHost('detail')
  const confirmation = store.openConfirm({ title: 'Risk' })
  store.releaseHost('detail')
  assert.equal(await confirmation, false)
  assert.equal(store.confirm.show, false)
  assert.equal(store.hostId, null)
  store.claimHost('home')
  assert.equal(store.hostId, 'home')
})

test('confirm dialog delivers confirmation before its v-model close event', async () => {
  const store = feedback()
  const confirmation = store.openConfirm({ title: 'Risk' })
  const dialog = loadModule('src/components/HgtConfirmDialog.vue', {}, {
    defineProps: () => ({}),
    withDefaults: props => props,
    defineEmits: () => (event) => {
      store.settleConfirm(event === 'confirm')
    },
  }, source => `${source.match(/<script setup lang="ts">([\s\S]*?)<\/script>/)[1]}\nexport { close }`)
  dialog.close(true)
  assert.equal(await confirmation, true)
})

test('mini programs default to hiding multiplayer invitations while H5 retains them', () => {
  for (const platform of ['mp-weixin', 'mp-toutiao', 'mp-alipay', 'h5']) {
    initPreContext(platform)
    const runtime = loadModule('src/utils/platform.ts', {}, {}, source => preJs(source, 'platform.ts'))
    assert.equal(runtime.supportsPublicRooms, platform === 'h5')
  }
})
