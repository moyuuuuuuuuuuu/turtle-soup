import assert from 'node:assert/strict'
import test from 'node:test'
import { loadModule } from './helpers/runtime.mjs'

function requestRuntime(kind) {
  let resolve
  let reject
  const pending = new Promise((done, fail) => {
    resolve = done
    reject = fail
  })
  const game = { value: { id: 'old', messages: [] } }
  const gameId = { value: 'old' }
  const applied = []
  const toasts = []
  const state = loadModule('src/pages/game/index.vue', {}, {
    ref: value => ({ value }),
    game,
    gameId,
    pageVersion: 1,
    pageActive: { value: true },
    canInput: { value: true },
    player: { user: null },
    pendingPlayerMessage: { value: null },
    socket: { ask: () => pending, guess: () => pending },
    store: { setGame: snapshot => applied.push(snapshot) },
    uni: { showToast: toast => toasts.push(toast) },
  }, (source) => {
    const declarations = source.match(/const (?:question|truthDraft|inputMode|busy|errorMessage|resultOpen) = ref[^\n]+/g).join('\n')
    const method = source.match(new RegExp(`async function ${kind}\\(\\) \\{[\\s\\S]*?\\n\\}`))[0]
    return `${declarations}\n${method}\nexport { question, truthDraft, busy, resultOpen, ${kind} }`
  })
  return { ...state, gameId, applied, toasts, resolve, reject }
}

for (const kind of ['ask', 'submitBottom']) {
  test(`${kind}: late responses do not overwrite a different game`, async () => {
    const runtime = requestRuntime(kind)
    runtime.question.value = '问题'
    runtime.truthDraft.value = '推理'
    const request = runtime[kind]()
    runtime.gameId.value = 'new'
    runtime.question.value = '新问题'
    runtime.truthDraft.value = '新推理'
    runtime.resolve({ id: 'old', status: 'finished' })
    await request
    assert.equal(runtime.applied.length, 0)
    assert.equal(runtime.question.value, '新问题')
    assert.equal(runtime.truthDraft.value, '新推理')
    assert.equal(runtime.resultOpen.value, false)
  })

  test(`${kind}: failure retains the draft typed while waiting`, async () => {
    const runtime = requestRuntime(kind)
    runtime.question.value = '原问题'
    runtime.truthDraft.value = '原推理'
    const request = runtime[kind]()
    runtime.question.value = '修改后的问题'
    runtime.truthDraft.value = '修改后的推理'
    runtime.reject(new Error('offline'))
    await request
    assert.equal(runtime.question.value, '修改后的问题')
    assert.equal(runtime.truthDraft.value, '修改后的推理')
    assert.equal(runtime.busy.value, false)
  })
}

test('a successful submission does not clear a newer truth draft', async () => {
  const runtime = requestRuntime('submitBottom')
  runtime.truthDraft.value = '原推理'
  const request = runtime.submitBottom()
  runtime.truthDraft.value = '新增的推理'
  runtime.resolve({ id: 'old', status: 'finished' })
  await request
  assert.equal(runtime.applied.length, 1)
  assert.equal(runtime.truthDraft.value, '新增的推理')
})
